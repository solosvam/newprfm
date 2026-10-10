<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\BonusService;
use App\Services\ContactInfo;
use App\Services\Referral\ReferralOgImage;
use App\Services\Referral\ReferralSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin → Ayarlar. Hər bölmə ayrıca səhifədir və yalnız öz sahələrini yoxlayıb saxlayır.
 */
class SettingsController extends Controller
{
    /** Bölmə => başlıq (menyu config/admin_menu.php-dədir) */
    public const SECTIONS = [
        'bonuses' => 'Bonuslar',
        'referral' => 'Referal',
        'orders' => 'Sifariş və çatdırılma',
        'banners' => 'Bannerlər',
        'contact' => 'Əlaqə məlumatları',
    ];

    /** Mətn kimi saxlanan açarlar (boş olanda 0 yox, '' yazılır) */
    private const TEXT_KEYS = ['bonus_terms_az', 'bonus_terms_en', 'bonus_terms_ru', 'referral_share_text_az', 'referral_share_text_en', 'referral_share_text_ru',
        'contact_phone', 'contact_whatsapp', 'contact_email', 'contact_hours_az', 'contact_hours_en', 'contact_hours_ru',
        'contact_address_az', 'contact_address_en', 'contact_address_ru', 'contact_instagram', 'contact_facebook', 'contact_youtube'];

    public function index(): RedirectResponse
    {
        return redirect()->route('admin.settings.bonuses');
    }

    public function show(string $section): View
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        return view('backend.settings.index', [
            'section' => $section,
            'sectionTitle' => self::SECTIONS[$section],
        ] + $this->data($section));
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        abort_unless(isset(self::SECTIONS[$section]), 404);

        $data = $request->validate($this->rules($section), [], $section === 'referral' ? $this->referralAttributes() : []);
        unset($data['referral_og_image_file'], $data['referral_og_image_remove']);

        foreach ($data as $key => $value) {
            Setting::set($key, in_array($key, self::TEXT_KEYS, true) ? ($value ?? '') : ($value ?? 0));
        }

        if ($section === 'referral') {
            // 1200×630-a kəsilir, ≤300 KB JPEG (ReferralOgImage); köhnə fayl silinir
            $og = app(ReferralOgImage::class);
            $previous = (string) Setting::valueOf('referral_og_image', '');
            if ($request->hasFile('referral_og_image_file')) {
                Setting::set('referral_og_image', $og->store($request->file('referral_og_image_file')->getRealPath()));
                $og->delete($previous);
            } elseif ($request->boolean('referral_og_image_remove')) {
                Setting::set('referral_og_image', '');
                $og->delete($previous);
            }
        }

        return redirect()->route('admin.settings.'.$section)->with('success', 'Ayarlar yeniləndi!');
    }

    private function data(string $section): array
    {
        return match ($section) {
            'bonuses' => [
                'bonusPercent' => Setting::valueOf('order_bonus_percent', 5),
                'registrationBonusEnabled' => (bool) Setting::valueOf('registration_bonus_enabled', 1),
                'registrationBonusAmount' => Setting::valueOf('registration_bonus_amount', 10),
                'bonusExpiryEnabled' => (bool) Setting::valueOf('bonus_expiry_enabled', 0),
                'bonusOrderExpiryDays' => Setting::valueOf('bonus_order_expiry_days', 365),
                'bonusRegistrationExpiryDays' => Setting::valueOf('bonus_registration_expiry_days', 90),
                'bonusTerms' => collect(BonusService::TERMS_DEFAULTS)
                    ->map(fn ($default, $locale) => Setting::valueOf('bonus_terms_'.$locale) ?: $default)->all(),
            ],
            'referral' => ['referral' => app(ReferralSettings::class)],
            'orders' => [
                'deliveryMode' => Setting::valueOf('delivery_mode', 'free'),
                'deliveryFee' => Setting::valueOf('delivery_fee', 0),
                'freeDeliveryFrom' => Setting::valueOf('free_delivery_from', 0),
                'giftWrapMode' => Setting::valueOf('gift_wrap_mode', 'free'),
                'giftWrapFee' => Setting::valueOf('gift_wrap_fee', 0),
                'payLinkHours' => Setting::valueOf('pay_link_hours', \App\Services\OrderPayLinkService::DEFAULT_HOURS),
            ],
            'banners' => [
                'bannerSizes' => collect(Setting::BANNER_DIMENSIONS)->map(fn ($size, $key) => [
                    'width' => Setting::valueOf("{$key}_width", $size[0]),
                    'height' => Setting::valueOf("{$key}_height", $size[1]),
                ])->all(),
                'bannerSlideInterval' => Setting::valueOf('banner_slide_interval', 5),
            ],
            'contact' => [
                'contact' => collect(ContactInfo::DEFAULTS)->map(fn ($default, $key) => app(ContactInfo::class)->raw($key))->all(),
            ],
        };
    }

    private function rules(string $section): array
    {
        return match ($section) {
            'bonuses' => [
                'order_bonus_percent' => ['required', 'numeric', 'min:0', 'max:100'],
                'registration_bonus_enabled' => ['required', 'boolean'],
                'registration_bonus_amount' => ['required_if:registration_bonus_enabled,1', 'nullable', 'numeric', 'min:0', 'max:10000'],
                // Qazanılan bonusun istifadə müddəti (gün); söndürülübsə müddətsiz
                'bonus_expiry_enabled' => ['required', 'boolean'],
                'bonus_order_expiry_days' => ['required_if:bonus_expiry_enabled,1', 'nullable', 'integer', 'min:1', 'max:3650'],
                'bonus_registration_expiry_days' => ['required_if:bonus_expiry_enabled,1', 'nullable', 'integer', 'min:1', 'max:3650'],
                'bonus_terms_az' => ['required', 'string', 'max:5000'],
                'bonus_terms_en' => ['nullable', 'string', 'max:5000'],
                'bonus_terms_ru' => ['nullable', 'string', 'max:5000'],
            ],
            'referral' => $this->referralRules(),
            'orders' => [
                'delivery_mode' => ['required', 'in:free,paid,threshold'],
                'delivery_fee' => ['required_unless:delivery_mode,free', 'nullable', 'numeric', 'min:0'],
                'free_delivery_from' => ['required_if:delivery_mode,threshold', 'nullable', 'numeric', 'min:0.01'],
                'gift_wrap_mode' => ['required', 'in:free,paid'],
                'gift_wrap_fee' => ['required_if:gift_wrap_mode,paid', 'nullable', 'numeric', 'min:0'],
                // SMS ödəniş linkinin müddəti (saat): 1 saatdan 30 günədək
                'pay_link_hours' => ['required', 'integer', 'min:1', 'max:720'],
            ],
            'banners' => collect(array_keys(Setting::BANNER_DIMENSIONS))
                ->flatMap(fn ($key) => [
                    "{$key}_width" => ['required', 'integer', 'min:1', 'max:10000'],
                    "{$key}_height" => ['required', 'integer', 'min:1', 'max:10000'],
                ])
                // Eyni yerdə bir neçə banner olanda hər slaydın göstərilmə müddəti (saniyə)
                ->put('banner_slide_interval', ['required', 'integer', 'min:2', 'max:60'])
                ->all(),
            'contact' => [
                'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
                'contact_whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
                'contact_email' => ['nullable', 'email', 'max:120'],
                'contact_hours_az' => ['nullable', 'string', 'max:120'],
                'contact_hours_en' => ['nullable', 'string', 'max:120'],
                'contact_hours_ru' => ['nullable', 'string', 'max:120'],
                'contact_address_az' => ['nullable', 'string', 'max:200'],
                'contact_address_en' => ['nullable', 'string', 'max:200'],
                'contact_address_ru' => ['nullable', 'string', 'max:200'],
                'contact_instagram' => ['nullable', 'url:https', 'max:300'],
                'contact_facebook' => ['nullable', 'url:https', 'max:300'],
                'contact_youtube' => ['nullable', 'url:https', 'max:300'],
            ],
        };
    }

    /** "Dostunu dəvət et": söndürülü bölmələrin sahələri yoxlanılmır (dəyərləri yadda qalır) */
    private function referralRules(): array
    {
        $on = 'required_if:referral_enabled,1';

        return [
            'referral_enabled' => ['required', 'boolean'],
            'referral_referrer_amount' => [$on, 'nullable', 'numeric', 'min:0', 'max:10000'],
            'referral_invitee_amount' => [$on, 'nullable', 'numeric', 'min:0', 'max:10000'],
            'referral_invitee_mode' => ['required', 'in:'.ReferralSettings::MODE_DISCOUNT.','.ReferralSettings::MODE_BALANCE],
            'referral_discount_with_promo' => ['required', 'boolean'],
            'referral_min_order_enabled' => ['required', 'boolean'],
            'referral_min_order_amount' => ['required_if:referral_min_order_enabled,1', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'referral_installment_allowed' => ['required', 'boolean'],
            'referral_expiry_enabled' => ['required', 'boolean'],
            'referral_referrer_expiry_days' => ['required_if:referral_expiry_enabled,1', 'nullable', 'integer', 'min:1', 'max:3650'],
            'referral_invitee_expiry_days' => ['required_if:referral_expiry_enabled,1', 'nullable', 'integer', 'min:1', 'max:3650'],
            'referral_limit_enabled' => ['required', 'boolean'],
            'referral_limit_count' => ['required_if:referral_limit_enabled,1', 'nullable', 'integer', 'min:1', 'max:100000'],
            'referral_limit_mode' => ['required', 'in:'.ReferralSettings::LIMIT_BLOCK.','.ReferralSettings::LIMIT_NO_REWARD],
            'referral_inviter_requires_order' => ['required', 'boolean'],
            'referral_cookie_days' => ['required', 'integer', 'min:1', 'max:365'],
            'referral_share_text_az' => ['nullable', 'string', 'max:300'],
            'referral_share_text_en' => ['nullable', 'string', 'max:300'],
            'referral_share_text_ru' => ['nullable', 'string', 'max:300'],
            // Sosial şəbəkələr üçün tövsiyə olunan ölçü 1200×630
            'referral_og_image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'referral_og_image_remove' => ['nullable', 'boolean'],
        ];
    }

    private function referralAttributes(): array
    {
        return [
            'referral_referrer_amount' => 'dəvət edənin bonusu',
            'referral_invitee_amount' => 'dəvət olunanın bonusu',
            'referral_min_order_amount' => 'minimum sifariş məbləği',
            'referral_referrer_expiry_days' => 'dəvət edənin bonus müddəti',
            'referral_invitee_expiry_days' => 'dəvət olunanın bonus müddəti',
            'referral_limit_count' => 'dəvət limiti',
            'referral_cookie_days' => 'linkin yadda qalma müddəti',
            'referral_og_image_file' => 'paylaşma şəkli',
        ];
    }
}
