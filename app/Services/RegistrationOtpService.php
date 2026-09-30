<?php

namespace App\Services;

use App\Models\Customer\Customer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegistrationOtpService
{
    public function send(Customer $customer, SmsService $sms): void
    {
        if ($customer->active) {
            abort(403);
        }

        $key = 'registration-otp-send:'.$customer->id;
        if (!Cache::add($key, true, now()->addSeconds(60))) {
            throw ValidationException::withMessages(['otp' => __('validation_an_otp_has_already_been_sent_please_try_again_shortly')]);
        }

        $code = (string) random_int(100000, 999999);

        try {
            $sms->send($customer->mobile, 'ParfumShop.az tesdiq kodunuz: '.$code);
        } catch (Throwable $e) {
            Cache::forget($key);
            report($e);
            throw ValidationException::withMessages(['otp' => __('auth_something_went_wrong')]);
        }

        $customer->forceFill([
            'registration_otp_hash' => Hash::make($code),
            'registration_otp_expires_at' => now()->addDays(5),
        ])->save();

        RateLimiter::clear($this->attemptKey($customer));
    }

    public function verify(Customer $customer, string $code): bool
    {
        $key = $this->attemptKey($customer);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['otp' => __('validation_too_many_attempts')]);
        }

        if (!$customer->registration_otp_hash
            || !$customer->registration_otp_expires_at
            || $customer->registration_otp_expires_at->isPast()
            || !Hash::check($code, $customer->registration_otp_hash)) {
            RateLimiter::hit($key, 900);
            return false;
        }

        $customer->forceFill([
            'active' => true,
            'registration_otp_hash' => null,
            'registration_otp_expires_at' => null,
        ])->save();

        // Hesab aktivləşdi — qeydiyyat bonusu (ayar açıqdırsa, bir dəfə)
        app(BonusService::class)->grantRegistration($customer);

        RateLimiter::clear($key);

        return true;
    }

    private function attemptKey(Customer $customer): string
    {
        return 'registration-otp-attempts:'.$customer->id;
    }
}
