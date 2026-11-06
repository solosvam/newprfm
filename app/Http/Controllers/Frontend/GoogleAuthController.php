<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\Customer\Customer;
use App\Services\Auth\GoogleLogin;
use App\Services\BonusService;
use App\Services\Referral\ReferralService;
use App\Services\SmsService;
use App\Support\LocalizedValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Google ilə giriş. Google-dan qayıdanda:
 *  1) Google hesabı artıq bağlıdır → daxil olur;
 *  2) Google email-i saytdakı hesabın email-i ilə eynidir → həmin hesabın nömrəsinə SMS kodu, təsdiqdən sonra bağlanır;
 *  3) yenidir → nömrə, cins (ad / soyad) soruşulur, nömrəyə SMS kodu; nömrə ilə hesab varsa ona bağlanır, yoxsa hesab yaranır.
 * Hesab yalnız SMS təsdiqindən sonra yaranır / bağlanır. Daxil olmuş müştəri profildən Google hesabını bağlaya / ayıra bilər.
 * Sessiya: google.profile — Google-dan gələn məlumat; google.target — kod göndərilən nömrə və hədəf (mövcud hesab və ya yeni).
 */
class GoogleAuthController extends Controller
{
    public function __construct(private GoogleLogin $google) {}

    /** Sınaq: düyməni yalnız bu brauzerdə göstər (30 gün) */
    public function preview(string $key): RedirectResponse
    {
        abort_unless($this->google->configured() && $this->google->previewKeyMatches($key), 404);

        return redirect()->route('front.login')
            ->withCookie(cookie(GoogleLogin::PREVIEW_COOKIE, $key, 60 * 24 * 30));
    }

    public function redirect()
    {
        abort_unless($this->google->visible(), 404);

        return $this->google->redirect();
    }

    public function callback(Request $request, SmsService $sms): RedirectResponse
    {
        abort_unless($this->google->configured(), 404);
        $fallback = Auth::check() ? 'profile.personal' : 'front.login';
        if ($request->filled('error')) { // müştəri Google pəncərəsində imtina etdi
            return redirect()->route($fallback);
        }
        try {
            $profile = $this->google->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route($fallback)->with('error', __('auth_google_failed'));
        }
        $owner = Customer::where('google_id', $profile['id'])->first();

        // Profildən bağlama
        if ($me = Auth::user()) {
            if ($owner && $owner->id !== $me->id) {
                return redirect()->route('profile.personal')->with('error', __('auth_google_linked_to_other'));
            }
            $me->forceFill(['google_id' => $profile['id']])->save();

            return redirect()->route('profile.personal')->with('success', __('auth_google_connected'));
        }

        if ($owner && $owner->active) {
            return $this->login($request, $owner);
        }

        $request->session()->put('google.profile', $profile);
        $request->session()->forget('google.target');

        $byEmail = $profile['email'] && $profile['email_verified']
            ? Customer::whereRaw('lower(email) = ?', [$profile['email']])->orderByDesc('active')->first()
            : null;
        if ($owner || $byEmail) {
            return $this->startLink($request, $owner ?? $byEmail, $sms, 'email');
        }

        return redirect()->route('auth.google.complete');
    }

    /** Yeni müştəri: nömrə, cins, ad / soyad */
    public function complete(Request $request): View|RedirectResponse
    {
        if (!$profile = $request->session()->get('google.profile')) {
            return redirect()->route('front.login');
        }

        return view('frontend.google-complete', ['profile' => $profile]);
    }

    public function storeComplete(Request $request, SmsService $sms): RedirectResponse
    {
        if (!$profile = $request->session()->get('google.profile')) {
            return redirect()->route('front.login');
        }
        $request->merge(['mobile' => $this->normalizeMobile($request->input('mobile'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'surname' => ['required', 'string', 'max:30'],
            'mobile' => ['required', 'regex:/^994\\d{9}$/'],
            'gender' => ['required', Rule::in(['0', '1'])],
        ], LocalizedValidation::messages(), LocalizedValidation::attributes());

        // Nömrə ilə hesab var — Google ona bağlanır (ad / cins dəyişmir)
        if ($existing = Customer::where('mobile', $data['mobile'])->first()) {
            return $this->startLink($request, $existing, $sms, 'mobile');
        }

        $this->send($data['mobile'], $sms);
        $request->session()->put('google.target', ['mobile' => $data['mobile'], 'create' => [
            'name' => trim($data['name']), 'surname' => trim($data['surname']), 'gender' => (int) $data['gender'],
        ]]);

        return redirect()->route('auth.google.verify');
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        [$profile, $target] = [$request->session()->get('google.profile'), $request->session()->get('google.target')];
        if (!$profile || !$target) {
            return redirect()->route('front.login');
        }

        return view('frontend.google-verify', [
            'maskedMobile' => $this->mask($target['mobile']),
            'reason' => $target['reason'] ?? null,
        ]);
    }

    public function verify(Request $request, BonusService $bonus, ReferralService $referrals): RedirectResponse
    {
        [$profile, $target] = [$request->session()->get('google.profile'), $request->session()->get('google.target')];
        if (!$profile || !$target) {
            return redirect()->route('front.login');
        }
        $data = $request->validate(['otp' => ['required', 'digits:6']], LocalizedValidation::messages(), LocalizedValidation::attributes());
        $this->google->verifyOtp($target['mobile'], $data['otp']);

        if (Customer::where('google_id', $profile['id'])->where('id', '!=', $target['customer_id'] ?? 0)->exists()) {
            $request->session()->forget(['google.profile', 'google.target']);

            return redirect()->route('front.login')->with('error', __('auth_google_linked_to_other'));
        }

        if (isset($target['customer_id'])) {
            $customer = Customer::findOrFail($target['customer_id']);
            $wasInactive = !$customer->active;
            $customer->forceFill([
                'google_id' => $profile['id'], 'active' => true,
                'registration_otp_hash' => null, 'registration_otp_expires_at' => null,
            ])->save();
            if ($wasInactive) { // qeydiyyatı yarımçıq qalmış hesab — indi təsdiqləndi
                $bonus->grantRegistration($customer);
            }
        } else {
            if (Customer::where('mobile', $target['mobile'])->exists()) { // arada başqa yolla qeydiyyat olub
                throw ValidationException::withMessages(['otp' => __('auth_google_mobile_taken')]);
            }
            $emailFree = $profile['email'] && !Customer::whereRaw('lower(email) = ?', [$profile['email']])->exists();
            $customer = DB::transaction(fn () => Customer::forceCreate($target['create'] + [
                'email' => $emailFree ? $profile['email'] : null,
                'mobile' => $target['mobile'],
                'google_id' => $profile['id'],
                'active' => true,
                'source' => 'google',
            ]));
            if ($referrals->settings()->enabled() && ($code = $referrals->normalize($request->cookie(ReferralService::COOKIE))) && $referrals->isUsableCode($code)) {
                $referrals->attach($customer, $code);
                Cookie::queue(Cookie::forget(ReferralService::COOKIE));
            }
            $bonus->grantRegistration($customer);
            if ($customer->email) {
                rescue(fn () => Mail::to($customer->email)->queue(new WelcomeMail($customer->name, app()->getLocale())));
            }
        }
        $request->session()->forget(['google.profile', 'google.target']);

        return $this->login($request, $customer);
    }

    public function resend(Request $request, SmsService $sms): RedirectResponse
    {
        if (!$target = $request->session()->get('google.target')) {
            return redirect()->route('front.login');
        }
        $this->send($target['mobile'], $sms);

        return back()->with('success', __('auth_an_otp_was_sent_to_mobile', ['mobile' => $this->mask($target['mobile'])]));
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['google_id' => null])->save();

        return back()->with('success', __('auth_google_disconnected'));
    }

    /** Mövcud hesaba bağlamaq: həmin hesabın nömrəsinə kod */
    private function startLink(Request $request, Customer $customer, SmsService $sms, string $reason): RedirectResponse
    {
        $profile = $request->session()->get('google.profile');
        if ($customer->google_id && $customer->google_id !== $profile['id']) {
            $request->session()->forget(['google.profile', 'google.target']);

            return redirect()->route('front.login')->with('error', __('auth_account_linked_to_other_google'));
        }
        try {
            $this->send($customer->mobile, $sms);
        } catch (ValidationException) {
            // son 60 saniyədə kod artıq göndərilib — o kod keçərlidir
        }
        $request->session()->put('google.target', ['mobile' => $customer->mobile, 'customer_id' => $customer->id, 'reason' => $reason]);

        return redirect()->route('auth.google.verify');
    }

    private function send(string $mobile, SmsService $sms): void
    {
        try {
            $this->google->sendOtp($mobile, $sms);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['otp' => __('auth_something_went_wrong')]);
        }
    }

    private function login(Request $request, Customer $customer): RedirectResponse
    {
        Auth::login($customer, true); // müştəri həmişə xatırlanır
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function mask(string $mobile): string
    {
        return '+994 '.substr($mobile, 3, 2).' *** ** '.substr($mobile, -2);
    }

    private function normalizeMobile(?string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', (string) $mobile);
        if (str_starts_with($digits, '994')) {
            $digits = substr($digits, 3);
        }

        return '994'.ltrim($digits, '0');
    }
}
