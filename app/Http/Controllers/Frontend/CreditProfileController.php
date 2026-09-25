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

    public function image(Request $request, string $side)
    {
        abort_unless(in_array($side, ['front', 'back'], true), 404);
        $field = $side === 'front' ? 'id_card_front' : 'id_card_back';
        $path = $request->user()->creditProfile?->{$field};
        abort_unless($path, 404);

        $prefix = 'backend/uploads/customers/';
        $legacyPrefix = $prefix.$request->user()->id.'/';
        if ((str_starts_with($path, $prefix) && basename($path) === substr($path, strlen($prefix)))
            || (str_starts_with($path, $legacyPrefix) && basename($path) === substr($path, strlen($legacyPrefix)))) {
            $file = public_path($path);
        } elseif (str_starts_with($path, 'credit-profiles/'.$request->user()->id.'/') && basename($path) === substr($path, strlen('credit-profiles/'.$request->user()->id.'/'))) {
            $file = Storage::disk('local')->path($path);
        } else {
            abort(404);
        }
        abort_unless(is_file($file), 404);
        return response()->file($file, ['Cache-Control' => 'private, no-store, max-age=0', 'X-Content-Type-Options' => 'nosniff']);
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
            'required' => __('credit_required'),
            'string' => __('credit_string'),
            'max' => __('credit_max'),
            'numeric' => __('credit_numeric'),
            'gt' => __('credit_gt'),
            'regex' => __('credit_regex'),
            'image' => __('credit_image'),
            'mimes' => __('credit_mimes'),
            'fin.unique' => __('credit_fin_unique'),
        ], [
            'father_name'=>__('credit_father_name'),'fin'=>__('credit_fin'),'relative_1_name'=>__('credit_relative_1_name'),
            'relative_1_phone'=>__('credit_relative_1_phone'),'relative_2_name'=>__('credit_relative_2_name'),
            'relative_2_phone'=>__('credit_relative_2_phone'),'id_card_front'=>__('credit_id_card_front'),
            'id_card_back'=>__('credit_id_card_back'),'workplace_name'=>__('credit_workplace_name'),
            'salary'=>__('credit_salary'),'position'=>__('credit_position'),
        ]);
        $images = [];
        $uploadDirectory = public_path('backend/uploads/customers');

        foreach (['id_card_front', 'id_card_back'] as $field) {
            unset($data[$field]);

            if (!$request->hasFile($field)) {
                continue;
            }

            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                throw new \RuntimeException(__('credit_directory_error'));
            }

            $filename = Str::uuid().'.webp';
            $absolutePath = $uploadDirectory.'/'.$filename;

            try {
                // GD is used directly to avoid Intervention v2/v3 facade conflicts.
                if (!extension_loaded('gd') || !function_exists('imagewebp')) {
                    throw new \RuntimeException(__('credit_gd_error'));
                }

                $sourcePath = $request->file($field)->getRealPath();
                $source = @imagecreatefromstring(file_get_contents($sourcePath));
                if (!$source) {
                    throw new \RuntimeException(__('credit_read_error'));
                }

                try {
                    $width = imagesx($source);
                    $height = imagesy($source);
                    $ratio = min(1, 1024 / max($width, $height));
                    $targetWidth = max(1, (int) round($width * $ratio));
                    $targetHeight = max(1, (int) round($height * $ratio));
                    $target = imagecreatetruecolor($targetWidth, $targetHeight);
                    if (!$target) {
                        throw new \RuntimeException(__('credit_process_error'));
                    }

                    try {
                        // A white background keeps transparent PNG documents readable.
                        $white = imagecolorallocate($target, 255, 255, 255);
                        imagefill($target, 0, 0, $white);
                        if (!imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)
                            || !imagewebp($target, $absolutePath, 80)
                            || !is_file($absolutePath)
                            || filesize($absolutePath) === 0) {
                            throw new \RuntimeException(__('credit_webp_error'));
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

            $images[$field] = 'backend/uploads/customers/'.$filename;
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
            // Delete only the file referenced by this customer's existing profile.
            $prefix = 'backend/uploads/customers/';
            $legacyPrefix = $prefix.$request->user()->id.'/';
            if ((str_starts_with($previous, $prefix) && basename($previous) === substr($previous, strlen($prefix)))
                || (str_starts_with($previous, $legacyPrefix) && basename($previous) === substr($previous, strlen($legacyPrefix)))) {
                @unlink(public_path($previous));
            } elseif (str_starts_with($previous, 'credit-profiles/'.$request->user()->id.'/')) {
                Storage::disk('local')->delete($previous);
            }
        }

        return response()->json(['message'=>__('credit_saved'),
            'images' => [
                'id_card_front' => $request->user()->creditProfile?->id_card_front ? route('profile.credit.image', ['side' => 'front']) : null,
                'id_card_back' => $request->user()->creditProfile?->id_card_back ? route('profile.credit.image', ['side' => 'back']) : null,
            ],
        ]);
    }
}
