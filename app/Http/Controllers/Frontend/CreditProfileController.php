<?php
namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreditProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('frontend.credit-profile', ['profile' => $request->user()->creditProfile]);
    }

    public function update(Request $request)
    {
        $profile = $request->user()->creditProfile;
        $request->merge(['fin' => strtoupper(trim((string) $request->input('fin')))]);
        $data = $request->validate([
            'father_name' => ['required','string','max:100'],
            'fin' => ['required','regex:/^[A-Z0-9]{7}$/',Rule::unique('customer_credit_profiles','fin')->ignore($profile?->id)],
            'relative_1_name' => ['required','string','max:100'],
            'relative_1_phone' => ['required','regex:/^\\+?[0-9 ]{9,16}$/'],
            'relative_2_name' => ['required','string','max:100'],
            'relative_2_phone' => ['required','regex:/^\\+?[0-9 ]{9,16}$/'],
            'id_card_front' => [$profile?->id_card_front ? 'nullable':'required','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'id_card_back' => [$profile?->id_card_back ? 'nullable':'required','image','mimes:jpg,jpeg,png,webp','max:5120'],
            'workplace_name' => ['required','string','max:255'],
            'salary' => ['required','numeric','gt:0','max:99999999.99'],
            'position' => ['required','string','max:150'],
        ], [
            'required' => ':attribute mütləq doldurulmalıdır.',
            'string' => ':attribute düzgün daxil edilməlidir.',
            'max' => ':attribute icazə verilən həddi aşır.',
            'numeric' => ':attribute rəqəm olmalıdır.',
            'gt' => ':attribute sıfırdan böyük olmalıdır.',
            'regex' => ':attribute düzgün formatda deyil.',
            'image' => ':attribute şəkil olmalıdır.',
            'mimes' => ':attribute JPG, PNG və ya WEBP olmalıdır.',
            'fin.unique' => 'Bu FİN artıq başqa müştəriyə aiddir.',
        ], [
            'father_name'=>'Ata adı','fin'=>'FİN','relative_1_name'=>'Birinci qohumun adı',
            'relative_1_phone'=>'Birinci qohumun nömrəsi','relative_2_name'=>'İkinci qohumun adı',
            'relative_2_phone'=>'İkinci qohumun nömrəsi','id_card_front'=>'Vəsiqənin ön şəkli',
            'id_card_back'=>'Vəsiqənin arxa şəkli','workplace_name'=>'İş yeri',
            'salary'=>'Əmək haqqı','position'=>'Vəzifə',
        ]);
        $images = [];
        $uploadDirectory = public_path('backend/uploads/customers/'.$request->user()->id);

        foreach (['id_card_front', 'id_card_back'] as $field) {
            unset($data[$field]);

            if (!$request->hasFile($field)) {
                continue;
            }

            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                throw new \RuntimeException('Şəkil qovluğu yaradıla bilmədi.');
            }

            $filename = Str::uuid().'.webp';
            $absolutePath = $uploadDirectory.'/'.$filename;

            try {
                // GD is used directly to avoid Intervention v2/v3 facade conflicts.
                if (!extension_loaded('gd') || !function_exists('imagewebp')) {
                    throw new \RuntimeException('Serverdə GD/WebP dəstəyi aktiv deyil.');
                }

                $sourcePath = $request->file($field)->getRealPath();
                $source = @imagecreatefromstring(file_get_contents($sourcePath));
                if (!$source) {
                    throw new \RuntimeException('Şəkil oxuna bilmədi.');
                }

                try {
                    $width = imagesx($source);
                    $height = imagesy($source);
                    $ratio = min(1, 1024 / max($width, $height));
                    $targetWidth = max(1, (int) round($width * $ratio));
                    $targetHeight = max(1, (int) round($height * $ratio));
                    $target = imagecreatetruecolor($targetWidth, $targetHeight);
                    if (!$target) {
                        throw new \RuntimeException('Şəkil emal edilə bilmədi.');
                    }

                    try {
                        // A white background keeps transparent PNG documents readable.
                        $white = imagecolorallocate($target, 255, 255, 255);
                        imagefill($target, 0, 0, $white);
                        if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)
                            || !imagewebp($target, $absolutePath, 80)
                            || !is_file($absolutePath)
                            || filesize($absolutePath) === 0) {
                            throw new \RuntimeException('WEBP şəkli saxlanıla bilmədi.');
                        }
                    } finally {
                        imagedestroy($target);
                    }
                } finally {
                    imagedestroy($source);
                }
            } catch (\Throwable $e) {
                foreach ($images as $saved) {
                    @unlink(public_path($saved));
                }
                throw $e;
            }

            $images[$field] = 'backend/uploads/customers/'.$request->user()->id.'/'.$filename;
        }

        try {
            $request->user()->creditProfile()->updateOrCreate([], array_merge($data, $images));
        } catch (\Throwable $e) {
            foreach ($images as $saved) {
                @unlink(public_path($saved));
            }
            throw $e;
        }

        foreach ($images as $field => $saved) {
            if (!$profile?->{$field}) {
                continue;
            }

            $previous = $profile->{$field};
            // Delete only files from the customer's own upload folder.
            $prefix = 'backend/uploads/customers/'.$request->user()->id.'/';
            if (str_starts_with($previous, $prefix) && basename($previous) === substr($previous, strlen($prefix))) {
                @unlink(public_path($previous));
            } elseif (str_starts_with($previous, 'credit-profiles/'.$request->user()->id.'/')) {
                Storage::disk('local')->delete($previous);
            }
        }

        return response()->json(['message'=>'Hissəli ödəniş məlumatları yadda saxlanıldı.']);
    }
}
