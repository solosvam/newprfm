<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Services\SmsService;
use App\Support\LocalizedValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegisterController extends Controller
{
    public function create()
    {
        return view('frontend.register');
    }

    public function store(Request $request, SmsService $sms)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));
        $request->merge(['mobile' => $mobile]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'surname' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:50', Rule::unique('customers', 'email')],
            'mobile' => ['required', 'regex:/^994\\d{9}$/', Rule::unique('customers', 'mobile')],
            'gender' => ['required', Rule::in(['0', '1'])],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        $customer = Customer::create([
            'name' => $data['name'],
            'surname' => $data['surname'],
            'email' => $data['email'],
            'mobile' => $mobile,
            'gender' => (int) $data['gender'],
            'password' => Hash::make($data['password']),
            'active' => false,
        ]);

        $request->session()->put('register.customer_id', $customer->id);
        $this->sendOtp($customer, $sms);

        return redirect()->route('front.register.verify');
    }

    public function showVerify(Request $request)
    {
        $customer = $this->pendingCustomer($request);

        return view('frontend.register-verify', [
            'maskedMobile' => '+994 '.substr($customer->mobile, 3, 2).' *** ** '.substr($customer->mobile, -2),
        ]);
    }

    public function verify(Request $request)
    {
        $customer = $this->pendingCustomer($request);
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        $attemptKey = 'register-otp-attempts:'.$customer->id;
        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            throw ValidationException::withMessages(['otp' => __('validation_too_many_attempts')]);
        }

        $otp = Cache::get($this->otpKey($customer));
        if (!$otp || !Hash::check($data['otp'], $otp)) {
            RateLimiter::hit($attemptKey, 180);
            throw ValidationException::withMessages(['otp' => __('validation_incorrect_or_expired_otp_code')]);
        }

        $customer->active = true;
        $customer->save();

        Cache::forget($this->otpKey($customer));
        RateLimiter::clear($attemptKey);
        $request->session()->forget('register.customer_id');

        Auth::login($customer);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function resend(Request $request, SmsService $sms)
    {
        $customer = $this->pendingCustomer($request);
        $this->sendOtp($customer, $sms);

        return back()->with('success', __('auth_an_otp_was_sent_to_mobile'));
    }

    private function pendingCustomer(Request $request): Customer
    {
        $id = $request->session()->get('register.customer_id');
        if (!$id) {
            abort(404);
        }

        return Customer::whereKey($id)->where('active', false)->firstOrFail();
    }

    private function sendOtp(Customer $customer, SmsService $sms): void
    {
        $throttleKey = 'register-otp-throttle:'.$customer->id;
        if (!Cache::add($throttleKey, true, now()->addSeconds(60))) {
            throw ValidationException::withMessages([
                'otp' => __('validation_an_otp_has_already_been_sent_please_try_again_shortly'),
            ]);
        }

        $code = (string) random_int(100000, 999999);

        try {
            $sms->send($customer->mobile, 'ParfumShop.az tesdiq kodunuz: '.$code);
        } catch (Throwable $e) {
            Cache::forget($throttleKey);
            report($e);
            throw ValidationException::withMessages(['otp' => __('auth_something_went_wrong')]);
        }

        Cache::put($this->otpKey($customer), Hash::make($code), now()->addMinutes(3));
        RateLimiter::clear('register-otp-attempts:'.$customer->id);
    }

    private function otpKey(Customer $customer): string
    {
        return 'register-otp:'.$customer->id;
    }

    private function normalizeMobile(?string $mobile): string
    {
        $digits = preg_replace('/\\D+/', '', (string) $mobile);
        if (str_starts_with($digits, '994')) {
            $digits = substr($digits, 3);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return '994'.$digits;
    }
}
