<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer\CustomerCreditProfile;
use App\Services\IdCard\IdCardReader;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreditProfileController extends Controller
{
    private const UPLOAD_PATH = 'frontend/uploads/customers/';

    public function edit(Request $request)
    {
        $return = $request->query('return');
        $returnUrl = is_string($return) && parse_url($return, PHP_URL_HOST) === $request->getHost()
            ? $return : null;

        return view('frontend.credit-profile', [
            'returnUrl' => $returnUrl,
            'profile' => $request->user()->creditProfile,
        ]);
    }

    /**
     * Vəsiqənin ön üzünün şəklindən (kəsilmiş JPEG) ata adı, FİN, seriya, nömrəni oxuyur — formanı doldurmaq üçün.
     * Şəkil saxlanmır; yalnız Google Vision-a göndərilir. Ad-soyad hesabdakı ilə müqayisə olunur (bloklamır).
     */
    public function ocr(Request $request, IdCardReader $reader)
    {
        return $reader->read($request, $request->user());
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $profile = $user->creditProfile;

        $request->merge([
            'fin' => strtoupper(trim((string) $request->input('fin'))),
            'id_card_number' => strtoupper(preg_replace('/\s+/', '', (string) $request->input('id_card_number'))),
        ]);

        $data = $request->validate(
            $this->validationRules($profile),
            $this->validationMessages(),
            $this->validationAttributes()
        );

        $images = [];

        try {
            foreach (['id_card_front', 'id_card_back'] as $field) {
                unset($data[$field]);

                if (!$request->hasFile($field)) {
                    continue;
                }

                $images[$field] = $this->saveImage(
                    $request->file($field)
                );
            }

            $user->creditProfile()->updateOrCreate(
                [],
                array_merge($data, $images)
            );
        } catch (\Throwable $e) {
            foreach ($images as $filename) {
                @unlink(public_path(self::UPLOAD_PATH . $filename));
            }

            throw $e;
        }

        // Yeni şəkillər saxlanıldıqdan sonra köhnələri sil.
        foreach ($images as $field => $path) {
            $previous = $profile?->{$field};

            if ($previous) {
                $this->deleteImage($previous);
            }
        }

        $profile = $user->creditProfile()->first();

        return response()->json([
            'message' => __('credit_saved'),
            'complete' => (bool) $profile?->isComplete(),
            'images' => [
                'id_card_front' => $profile?->id_card_front
                    ? asset(self::UPLOAD_PATH . basename($profile->id_card_front))
                    : null,

                'id_card_back' => $profile?->id_card_back
                    ? asset(self::UPLOAD_PATH . basename($profile->id_card_back))
                    : null,
            ],
        ]);
    }

    private function validationRules($profile): array
    {
        return [
            'father_name' => [
                'required',
                'string',
                'max:100',
            ],

            'fin' => [
                'required',
                'regex:/^[A-Z0-9]{7}$/',
                Rule::unique('customer_credit_profiles', 'fin')
                    ->ignore($profile?->id),
            ],

            'id_card_series' => [
                'required',
                Rule::in(CustomerCreditProfile::ID_CARD_SERIES),
            ],

            'id_card_number' => [
                'required',
                'regex:/^[A-Z0-9]{1,8}$/',
            ],

            'relative_1_name' => [
                'required',
                'string',
                'max:100',
            ],

            'relative_1_phone' => [
                'required',
                'regex:/^\+?[0-9 ]{9,16}$/',
            ],

            'relative_2_name' => [
                'required',
                'string',
                'max:100',
            ],

            'relative_2_phone' => [
                'required',
                'regex:/^\+?[0-9 ]{9,16}$/',
            ],

            'id_card_front' => [
                $profile?->id_card_front ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            // arxa üz yalnız AZE (köhnə vəsiqə) üçün məcburidir
            'id_card_back' => [
                Rule::requiredIf(fn () => !$profile?->id_card_back
                    && CustomerCreditProfile::needsBackSide(request()->input('id_card_series'))),
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'workplace_name' => [
                'required',
                'string',
                'max:255',
            ],

            'salary' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999.99',
            ],

        ];
    }

    private function validationMessages(): array
    {
        return [
            'required' => __('credit_required'),
            'string' => __('credit_string'),
            'max' => __('credit_max'),
            'numeric' => __('credit_numeric'),
            'gt' => __('credit_gt'),
            'regex' => __('credit_regex'),
            'image' => __('credit_image'),
            'mimes' => __('credit_mimes'),
            'fin.unique' => __('credit_fin_unique'),
            'id_card_series.in' => __('credit_id_card_series_invalid'),
        ];
    }

    private function validationAttributes(): array
    {
        $fields = [
            'father_name',
            'fin',
            'id_card_series',
            'id_card_number',
            'relative_1_name',
            'relative_1_phone',
            'relative_2_name',
            'relative_2_phone',
            'id_card_front',
            'id_card_back',
            'workplace_name',
            'salary',
        ];

        $attributes = [];

        foreach ($fields as $field) {
            $attributes[$field] = __("credit_{$field}");
        }

        return $attributes;
    }

    private function saveImage($file): string
    {
        $directory = public_path(self::UPLOAD_PATH);

        if (
            !is_dir($directory) &&
            !mkdir($directory, 0755, true) &&
            !is_dir($directory)
        ) {
            throw new \RuntimeException(
                __('credit_directory_error')
            );
        }

        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            throw new \RuntimeException(
                __('credit_gd_error')
            );
        }

        $filename = Str::uuid() . '.webp';
        $absolutePath = $directory . $filename;

        try {
            $source = @imagecreatefromstring(
                file_get_contents($file->getRealPath())
            );

            if (!$source) {
                throw new \RuntimeException(
                    __('credit_read_error')
                );
            }

            try {
                $width = imagesx($source);
                $height = imagesy($source);

                $ratio = min(1, 1024 / max($width, $height));

                $targetWidth = max(1, (int) round($width * $ratio));
                $targetHeight = max(1, (int) round($height * $ratio));

                $target = imagecreatetruecolor(
                    $targetWidth,
                    $targetHeight
                );

                if (!$target) {
                    throw new \RuntimeException(
                        __('credit_process_error')
                    );
                }

                try {
                    // Şəffaf PNG üçün ağ fon.
                    $white = imagecolorallocate(
                        $target,
                        255,
                        255,
                        255
                    );

                    imagefill($target, 0, 0, $white);

                    $resampled = imagecopyresampled(
                        $target,
                        $source,
                        0,
                        0,
                        0,
                        0,
                        $targetWidth,
                        $targetHeight,
                        $width,
                        $height
                    );

                    if (
                        !$resampled ||
                        !imagewebp($target, $absolutePath, 80) ||
                        !is_file($absolutePath) ||
                        filesize($absolutePath) === 0
                    ) {
                        throw new \RuntimeException(
                            __('credit_webp_error')
                        );
                    }
                } finally {
                    imagedestroy($target);
                }
            } finally {
                imagedestroy($source);
            }
        } catch (\Throwable $e) {
            // Uğursuz çevrilmədən qalan faylı təmizlə.
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }

            throw $e;
        }

        return $filename;
    }

    private function resolveImagePath(string $filename): ?string
    {
        $filename = basename(str_replace('\\\\', '/', $filename));

        if (!preg_match(
            '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.webp$/i',
            $filename
        )) {
            return null;
        }
        return public_path(self::UPLOAD_PATH . $filename);
    }

    private function deleteImage(string $filename): void
    {
        $file = $this->resolveImagePath($filename);

        if ($file && is_file($file)) {
            @unlink($file);
        }
    }
}
