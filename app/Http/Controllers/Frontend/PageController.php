<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Credit\CreditPeriod;
use App\Models\Credit\CreditTermItem;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Setting;
use App\Services\BonusService;
use App\Services\ShopPricing;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Məlumat səhifələri (front.page.{key}) və FAQ (front.faq).
 * "Çatdırılma və ödəniş" səhifəsində çatdırılma haqqı və ödəniş üsulları ayarlardan avtomatik göstərilir.
 * Bonus proqramı (front.bonus) və hissə-hissə ödəniş (front.installment) — mətni öz admin bölmələrindən:
 * Ayarlar → Bonuslar ("Bonus şərtləri") və Ayarlar → Kredit (Faizlər, Şərtlər və qaydalar).
 */
class PageController extends Controller
{
    public function show(string $key): View
    {
        $page = Page::findByKey($key);
        abort_unless($page, 404);

        $extra = [];
        if ($key === 'delivery') {
            $extra['delivery'] = app(ShopPricing::class)->delivery();
            $extra['paymentMethods'] = DB::table('payment_methods')->where('active', 1)->orderBy('sort_order')->get();
        }

        return view('frontend.page', ['page' => $page, 'nav' => $this->nav()] + $extra);
    }

    public function bonus(BonusService $bonus): View
    {
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $expiry = $bonus->expiryDays('earn');
        $facts = [
            ['label' => __('bonus_fact_percent'), 'value' => $num($bonus->currentPercent()).'%'],
        ];
        if ((int) Setting::valueOf('registration_bonus_enabled', 1) === 1) {
            $facts[] = ['label' => __('bonus_fact_registration'), 'value' => $num(Setting::valueOf('registration_bonus_amount', 10)).' ₼'];
        }
        $facts[] = ['label' => __('bonus_fact_expiry'), 'value' => $expiry ? __('bonus_expiry_days', ['days' => $expiry]) : __('bonus_expiry_none')];

        return view('frontend.info', [
            'active' => 'bonus',
            'title' => __('page_bonus_title'),
            'meta' => __('page_bonus_meta'),
            'facts' => $facts,
            'html' => self::textToHtml($bonus->terms(app()->getLocale())),
            'nav' => $this->nav(),
        ]);
    }

    public function referral(): View
    {
        return view('frontend.referral-program', [
            'settings' => app(\App\Services\Referral\ReferralSettings::class),
            'nav' => $this->nav(),
        ]);
    }

    public function installment(): View
    {
        $locale = app()->getLocale();

        return view('frontend.installment', [
            'periods' => CreditPeriod::where('active', 1)->orderBy('month')->get(),
            'rules' => CreditTermItem::orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($item) => $item->{'content_'.$locale} ?: $item->content_az)->filter()->values(),
            'nav' => $this->nav(),
        ]);
    }

    /**
     * Admin mətn sahəsindəki sadə mətn → HTML: boş sətirlə ayrılan bloklar; blokun ilk sətri başlıq,
     * "•" ilə başlayan sətirlər siyahı. Hər şey escape olunur.
     */
    public static function textToHtml(string $text): string
    {
        $html = '';
        foreach (preg_split('/\R{2,}/u', trim($text)) as $block) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $block)), 'strlen'));
            if (!$lines) {
                continue;
            }
            if (count($lines) > 1 && !str_starts_with($lines[0], '•')) {
                $html .= '<h2>'.e(array_shift($lines)).'</h2>';
            }
            $list = [];
            foreach ($lines as $line) {
                if (str_starts_with($line, '•')) {
                    $list[] = '<li>'.e(trim(mb_substr($line, 1))).'</li>';
                    continue;
                }
                if ($list) {
                    $html .= '<ul>'.implode('', $list).'</ul>';
                    $list = [];
                }
                $html .= '<p>'.e($line).'</p>';
            }
            if ($list) {
                $html .= '<ul>'.implode('', $list).'</ul>';
            }
        }

        return $html;
    }

    public function faq(): View
    {
        return view('frontend.faq', ['faqs' => Faq::orderBy('id')->get(), 'nav' => $this->nav()]);
    }

    /** Yan menyu: bütün məlumat səhifələri + FAQ */
    private function nav(): array
    {
        $titles = Page::pluck('title_'.app()->getLocale(), 'key');
        $fallback = Page::pluck('title_az', 'key');
        $items = [];
        foreach (array_keys(Page::PAGES) as $key) {
            if (isset($fallback[$key])) {
                $items[] = ['key' => $key, 'title' => $titles[$key] ?: $fallback[$key], 'url' => route('front.page.'.$key)];
            }
            // hissə-hissə və bonus — çatdırılmadan sonra
            if ($key === 'delivery') {
                $items[] = ['key' => 'installment', 'title' => __('page_installment_title'), 'url' => route('front.installment')];
                $items[] = ['key' => 'bonus', 'title' => __('page_bonus_title'), 'url' => route('front.bonus')];
                $items[] = ['key' => 'referral', 'title' => __('referral_title'), 'url' => route('front.referral')];
            }
        }
        $items[] = ['key' => 'faq', 'title' => __('faq_title'), 'url' => route('front.faq')];

        return $items;
    }
}
