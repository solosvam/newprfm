<?php

namespace App\Services\Auth;

use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Google ilə giriş: OAuth (Socialite), düymənin görünməsi və hesabı bağlamaq üçün SMS kodu.
 * Görünmə: GOOGLE_LOGIN_PUBLIC=true və ya /google-preview/{açar} ilə cookie qoyulmuş brauzer.
 */
class GoogleLogin
{
    public const PREVIEW_COOKIE = 'google_preview';

    private const OTP_MAX_ATTEMPTS = 5;

    public function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function visible(?Request $request = null): bool
    {
        if (!$this->configured()) {
            return false;
        }
        if (config('services.google.login_public')) {
            return true;
        }
        $cookie = ($request ?? request())->cookie(self::PREVIEW_COOKIE);

        return is_string($cookie) && $this->previewKeyMatches($cookie);
    }

    public function previewKeyMatches(string $key): bool
    {
        $expected = (string) config('services.google.preview_key');

        return strlen($expected) >= 8 && hash_equals($expected, $key);
    }

    public function redirect(): RedirectResponse
    {
        return $this->driver()->redirect();
    }

    /** @return array{id: string, email: ?string, email_verified: bool, name: string, surname: string} */
    public function user(): array
    {
        $user = $this->driver()->user();
        $raw = (array) $user->getRaw();

        return [
            'id' => (string) $user->getId(),
            'email' => $user->getEmail() ? mb_strtolower(trim($user->getEmail())) : null,
            'email_verified' => filter_var($raw['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'name' => mb_substr(trim((string) ($raw['given_name'] ?? $user->getName() ?? '')), 0, 30),
            'surname' => mb_substr(trim((string) ($raw['family_name'] ?? '')), 0, 30),
        ];
    }

    /** SMS kodu (nömrəni / hesabı təsdiq etmək üçün) — 60 saniyədə bir, 5 dəqiqə etibarlı */
    public function sendOtp(string $mobile, SmsService $sms): void
    {
        if (!Cache::add('google-otp-throttle:'.$mobile, true, now()->addSeconds(60))) {
            throw ValidationException::withMessages(['otp' => __('validation_an_otp_has_already_been_sent_please_try_again_shortly')]);
        }
        $code = (string) random_int(100000, 999999);
        Cache::put($this->otpKey($mobile), ['code' => Hash::make($code), 'attempts' => 0], now()->addMinutes(5));
        SmsLog::deliver($sms, $mobile, 'ParfumShop.az tesdiq kodunuz: '.$code, 'otp', null, null, [$code]);
    }

    /** 5 səhv cəhddən sonra kod etibarsızdır — yenisi istənməlidir */
    public function verifyOtp(string $mobile, string $code): void
    {
        $data = Cache::get($this->otpKey($mobile));
        if ($data && Hash::check($code, $data['code'])) {
            Cache::forget($this->otpKey($mobile));

            return;
        }
        if ($data && ++$data['attempts'] >= self::OTP_MAX_ATTEMPTS) {
            Cache::forget($this->otpKey($mobile));
            throw ValidationException::withMessages(['otp' => __('auth_too_many_otp_attempts')]);
        }
        if ($data) {
            Cache::put($this->otpKey($mobile), $data, now()->addMinutes(5));
        }
        throw ValidationException::withMessages(['otp' => __('validation_incorrect_or_expired_otp_code')]);
    }

    private function driver()
    {
        return Socialite::driver('google')->redirectUrl(route('auth.google.callback'))->scopes(['openid', 'email', 'profile']);
    }

    private function otpKey(string $mobile): string
    {
        return 'google-otp:'.$mobile;
    }
}
