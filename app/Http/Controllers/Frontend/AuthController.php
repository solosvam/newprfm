<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Product\ProductReview;
use App\Services\SmsService;
use App\Services\RegistrationOtpService;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;
use App\Support\LocalizedValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** SMS kodu üçün səhv cəhd limiti (bundan sonra yeni kod istənməlidir) */
    private const OTP_MAX_ATTEMPTS = 5;

    public function login()
    {
        return view('frontend.login');
    }

    public function checkMobile(Request $request, SmsService $sms)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));

        if (strlen($mobile) !== 12) {
            throw ValidationException::withMessages(['mobile' => __('validation_enter_a_valid_phone_number')]);
        }

        $customer = Customer::where('mobile', $mobile)->first();

        if (!$customer) {
            return response()->json(['status' => 'not_found']);
        }

        if (!$customer->active) {
            return response()->json(['status' => 'inactive', 'mobile' => $this->maskedMobile($mobile)]);
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

    public function verifyInactive(Request $request, RegistrationOtpService $registrationOtp)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));
        $request->merge(['mobile' => $mobile]);
        $request->validate([
            'mobile' => ['required', 'regex:/^994\\d{9}$/'],
            'otp' => ['required', 'digits:6'],
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        $customer = Customer::where('mobile', $mobile)->where('active', false)->firstOrFail();

        if (!$registrationOtp->verify($customer, (string) $request->input('otp'))) {
            throw ValidationException::withMessages(['otp' => __('validation_incorrect_or_expired_otp_code')]);
        }

        if (filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::to($customer->email)->queue(new WelcomeMail($customer->name, app()->getLocale()));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        Auth::login($customer, true); // "Məni xatırla" seçimi yoxdur — müştəri həmişə xatırlanır
        $request->session()->regenerate();

        return response()->json(['status' => 'success', 'redirect' => route('home')]);
    }

    public function resendInactive(Request $request, SmsService $sms, RegistrationOtpService $registrationOtp)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));
        $request->merge(['mobile' => $mobile]);
        $request->validate(['mobile' => ['required', 'regex:/^994\\d{9}$/']], LocalizedValidation::messages(), LocalizedValidation::attributes());

        $customer = Customer::where('mobile', $mobile)->where('active', false)->firstOrFail();
        $registrationOtp->send($customer, $sms);

        return response()->json(['status' => 'sent']);
    }

    public function passwordLogin(Request $request)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));

        $request->merge(['mobile' => $mobile]);
        $request->validate([
            'mobile' => ['required', 'digits:12'],
            'password' => ['required', 'string'],
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        $customer = Customer::where('mobile', $mobile)->where('active', 1)->first();

        if (!$customer || !$customer->password || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages(['password' => __('validation_incorrect_phone_number_or_password')]);
        }

        Auth::login($customer, true);
        $request->session()->regenerate();

        return response()->json(['status' => 'success', 'redirect' => route('home')]);
    }

    /** "Şifrəni unutdum": şifrəsi olan aktiv müştəriyə SMS kod → verifyOtp → setPassword (şifrəsiz müştəri ilə eyni axın) */
    public function forgotPassword(Request $request, SmsService $sms)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));
        $customer = Customer::where('mobile', $mobile)->where('active', 1)->whereNotNull('password')->first();
        if (!$customer) {
            throw ValidationException::withMessages(['mobile' => __('auth_no_account_found_for_this_number_please_register')]);
        }

        $this->sendOtp($mobile, $sms);

        return response()->json(['status' => 'otp', 'mobile' => $this->maskedMobile($mobile)]);
    }

    public function verifyOtp(Request $request)
    {
        $mobile = $this->normalizeMobile($request->input('mobile'));

        $request->merge(['mobile' => $mobile]);
        $request->validate([
            'mobile' => ['required', 'digits:12'],
            'otp' => ['required', 'digits:6'],
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        $data = Cache::get($this->otpKey($mobile));

        if (!$data || !Hash::check((string) $request->otp, $data['code'])) {
            // Kodu təxmin etmək olmasın: 5 səhv cəhddən sonra kod etibarsızdır, yenisi istənməlidir
            if ($data && ($data['attempts'] = ($data['attempts'] ?? 0) + 1) >= self::OTP_MAX_ATTEMPTS) {
                Cache::forget($this->otpKey($mobile));
                throw ValidationException::withMessages(['otp' => __('auth_too_many_otp_attempts')]);
            }
            if ($data) {
                Cache::put($this->otpKey($mobile), $data, now()->addSeconds(max(1, ($data['expires'] ?? time() + 60) - time())));
            }
            throw ValidationException::withMessages(['otp' => __('validation_incorrect_or_expired_otp_code')]);
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
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        if (!Cache::pull($this->verifiedKey($mobile))) {
            throw ValidationException::withMessages(['otp' => __('validation_otp_verification_not_found_please_try_again')]);
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
        $customer = Customer::where('mobile', $mobile)->where('active', 1)->firstOrFail(); // şifrəsiz və ya "Şifrəni unutdum"

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
        $customer = auth()->user();
        $with = ['status', 'items.product.images'];

        $latestOrder = $customer->orders()->with($with)->withSum('itemCancellations as cancelled_amount', 'amount')->latest()->first();

        // Aktiv: təhvil verilməyib və ləğv edilməyib
        $activeQuery = $customer->orders()
            ->whereHas('status', fn ($q) => $q->whereNotIn('code', ['delivered', 'cancelled']));
        $activeCount = (clone $activeQuery)->count();
        $activeOrder = $activeCount ? (clone $activeQuery)->with('status')->latest()->first() : null;

        return view('frontend.profile', compact('latestOrder', 'activeOrder', 'activeCount'));
    }

    public function personal()
    {
        return view('frontend.personal');
    }

    public function updatePersonal(Request $request)
    {
        $customer = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'surname' => ['required', 'string', 'max:30'],
            'email' => ['required', 'email', 'max:50', 'unique:customers,email,'.$customer->id],
            'password' => ['nullable', 'string'],
            'new_password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        if (!empty($data['new_password'])) {
            if (empty($data['password']) || !Hash::check($data['password'], $customer->password)) {
                throw ValidationException::withMessages(['password' => __('validation_incorrect_current_password')]);
            }
            $customer->password = Hash::make($data['new_password']);
        }

        $customer->name = $data['name'];
        $customer->surname = $data['surname'];
        $customer->email = $data['email'];
        $customer->save();

        return back()->with('success', __('validation_your_details_have_been_updated'));
    }

    public function bonus()
    {
        $transactions = auth()->user()->bonusTransactions()
            ->with('order.customer')
            ->latest()
            ->paginate(20);

        // sonsuz scroll: növbəti səhifənin sətirləri (frontend/js/infinite-list.js)
        if (request()->ajax()) {
            return response()->json([
                'html' => view('frontend.partials.bonus-rows', compact('transactions'))->render(),
                'next' => $transactions->nextPageUrl(),
            ]);
        }

        return view('frontend.bonus', compact('transactions'));
    }


    public function reviews()
    {
        $reviews = ProductReview::with(['product.brand', 'product.images'])
            ->where('customer_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('frontend.reviews', compact('reviews'));
    }

    public function destroyReview(ProductReview $review)
    {
        abort_unless($review->customer_id === auth()->id(), 403);
        abort_if($review->active, 403);

        $review->delete();

        return back()->with('success', __('reviews_deleted'));
    }

    private function sendOtp(string $mobile, SmsService $sms): void
    {
        $throttleKey = 'login-otp-throttle:'.$mobile;

        if (Cache::has($throttleKey)) {
            throw ValidationException::withMessages(['mobile' => __('validation_an_otp_has_already_been_sent_please_try_again_shortly')]);
        }

        $code = (string) random_int(100000, 999999);

        $expires = now()->addMinutes(3);
        Cache::put($this->otpKey($mobile), ['code' => Hash::make($code), 'attempts' => 0, 'expires' => $expires->timestamp], $expires);
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
