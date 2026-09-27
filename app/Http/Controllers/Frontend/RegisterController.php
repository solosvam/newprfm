<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Mail\WelcomeMail;
use App\Services\SmsService;
use App\Services\RegistrationOtpService;
use App\Support\LocalizedValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        app(RegistrationOtpService::class)->send($customer, $sms);

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

        if (!app(RegistrationOtpService::class)->verify($customer, (string) $data['otp'])) {
            throw ValidationException::withMessages(['otp' => __('validation_incorrect_or_expired_otp_code')]);
        }

        $request->session()->forget('register.customer_id');

        if (filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            Mail::to($customer->email)->queue(new WelcomeMail($customer->name, app()->getLocale()));
        }

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
