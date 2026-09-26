<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Product\ProductReview;
use App\Services\SmsService;
use App\Support\LocalizedValidation;
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
            throw ValidationException::withMessages(['mobile' => __('validation_enter_a_valid_phone_number')]);
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
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        $customer = Customer::where('mobile', $mobile)->where('active', 1)->first();

        if (!$customer || !$customer->password || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages(['password' => __('validation_incorrect_phone_number_or_password')]);
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
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        $data = Cache::get($this->otpKey($mobile));

        if (!$data || !Hash::check((string) $request->otp, $data['code'])) {
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

    public function orders()
    {
        $orders = Order::with([
            'items.product.brand',
            'items.product.images',
            'items.variant.size',
            'paymentMethod',
            'status',
        ])
            ->where('customer_id', auth()->id())
            ->latest()
            ->paginate(10);

        return view('frontend.orders', compact('orders'));
    }

    public function order(Order $order)
    {
        abort_unless($order->customer_id === auth()->id(), 403);

        $order->load([
            'items.product.brand',
            'items.product.images',
            'items.variant.size',
            'paymentMethod',
            'status',
            'address',
        ]);

        return view('frontend.order-detail', compact('order'));
    }

    public function bonuses()
    {
        $transactions = auth()->user()->bonusTransactions()
            ->with('order')
            ->latest()
            ->paginate(20);

        return view('frontend.bonuses', compact('transactions'));
    }

    public function wishlist()
    {
        return view('frontend.wishlist');
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
