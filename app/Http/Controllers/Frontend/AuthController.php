<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login()
    {
        return view('frontend.login');
    }

    public function checkMobile(Request $request, SmsService $sms)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));

        if (strlen($mobile) !== 12) {
            throw ValidationException::withMessages(['mobile' => 'Telefon nömrəsini düzgün daxil edin.']);
        }

        $customer = Customer::where('mobile', $mobile)->first();

        if (!$customer) {
            return response()->json(['status' => 'not_found']);
        }

        if (!$customer->active) {
            return response()->json(['status' => 'inactive'], 403);
        }

        if ($customer->password) {
            return response()->json(['status' => 'password']);
        }

        $this->sendOtp($mobile, $sms);

        return response()->json([
            'status' => 'otp',
            'mobile' => $this->maskedMobile($mobile),
        ]);
    }

    public function passwordLogin(Request $request)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));

        $request->merge(['mobile' => $mobile]);
        $request->validate([
            'mobile' => ['required', 'digits:12'],
            'password' => ['required', 'string'],
        ]);

        $customer = Customer::where('mobile', $mobile)->where('active', 1)->first();

        if (!$customer || !$customer->password || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages(['password' => 'Telefon nömrəsi və ya şifrə yanlışdır.']);
        }

        Auth::login($customer, true);
        $request->session()->regenerate();

        return response()->json(['status' => 'success', 'redirect' => route('home')]);
    }

    public function verifyOtp(Request $request)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));

        $request->merge(['mobile' => $mobile]);
        $request->validate([
            'mobile' => ['required', 'digits:12'],
            'otp' => ['required', 'digits:6'],
        ]);

        $data = Cache::get($this->otpKey($mobile));

        if (!$data || !Hash::check((string) $request->otp, $data['code'])) {
            throw ValidationException::withMessages(['otp' => 'OTP kod yanlışdır və ya vaxtı bitib.']);
        }

        Cache::put($this->verifiedKey($mobile), true, now()->addMinutes(10));
        Cache::forget($this->otpKey($mobile));

        return response()->json(['status' => 'verified']);
    }

    public function setPassword(Request $request)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));

        $request->merge(['mobile' => $mobile]);
        $request->validate([
            'mobile' => ['required', 'digits:12'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (!Cache::pull($this->verifiedKey($mobile))) {
            throw ValidationException::withMessages(['otp' => 'OTP təsdiqi tapılmadı. Yenidən cəhd edin.']);
        }

        $customer = Customer::where('mobile', $mobile)->where('active', 1)->firstOrFail();
        $customer->password = Hash::make($request->password);
        $customer->save();

        Auth::login($customer, true);
        $request->session()->regenerate();

        return response()->json(['status' => 'success', 'redirect' => route('home')]);
    }

    public function resendOtp(Request $request, SmsService $sms)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));
        $customer = Customer::where('mobile', $mobile)->whereNull('password')->where('active', 1)->firstOrFail();

        $this->sendOtp($customer->mobile, $sms);

        return response()->json(['status' => 'sent']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function profile()
    {
        return view('frontend.profile');
    }

    private function sendOtp(string $mobile, SmsService $sms): void
    {
        $throttleKey = 'login-otp-throttle:'.$mobile;

        if (Cache::has($throttleKey)) {
            throw ValidationException::withMessages(['mobile' => 'OTP artıq göndərilib. Bir az sonra yenidən cəhd edin.']);
        }

        $code = (string) random_int(100000, 999999);

        Cache::put($this->otpKey($mobile), ['code' => Hash::make($code)], now()->addMinutes(3));
        Cache::put($throttleKey, true, now()->addSeconds(60));

        $sms->send($mobile, 'ParfumShop.az tesdiq kodunuz: '.$code);
    }

    private function normalizeMobile(?string $mobile): string
    {
        $mobile = preg_replace('/\D+/', '', (string) $mobile);

        if (str_starts_with($mobile, '994')) {
            $mobile = substr($mobile, 3);
        }

        $mobile = ltrim($mobile, '0');

        return '994'.substr($mobile, 0, 9);
    }

    private function maskedMobile(string $mobile): string
    {
        return '+994 '.substr($mobile, 3, 2).' *** ** '.substr($mobile, -2);
    }

    private function otpKey(string $mobile): string
    {
        return 'login-otp:'.$mobile;
    }

    private function verifiedKey(string $mobile): string
    {
        return 'login-otp-verified:'.$mobile;
    }
}
