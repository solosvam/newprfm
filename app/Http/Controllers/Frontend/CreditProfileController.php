<?php
namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
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
                Image::read($request->file($field)->getRealPath())
                    ->scaleDown(width: 1024, height: 1024)
                    ->toWebp(quality: 80)
                    ->save($absolutePath);
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
