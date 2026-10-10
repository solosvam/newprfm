<?php
namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SmsTemplateController extends Controller
{
    /**
     * Şablonun kodda harada göndərildiyi. null — şablon bazada var, amma heç bir hadisəyə qoşulmayıb
     * (dəyişsə də, aktiv olsa da SMS getmir).
     */
    public const USAGE = [
        'crm_order_accepted' => null,
        'website_order_accepted' => null,
        'order_sent' => 'Kuryer "Yola çıxdım" basanda müştəriyə (sifarişə bir dəfə)',
        'crm_order_cancelled' => 'Operator CRM-də sifarişi ləğv edəndə müştəriyə',
        'easy_order_registration_bonus' => 'Asan sifariş təsdiqlənəndə yeni yaranan müştəriyə (qeydiyyat bonusu varsa)',
        'easy_order_registration' => 'Asan sifariş təsdiqlənəndə yeni yaranan müştəriyə (bonus yoxdursa)',
        'order_payment_link' => 'Operator ödəniş linkini SMS ilə göndərəndə',
        'crm_customer_created' => 'CRM-də müştəri yaradılıb şifrə göndəriləndə',
        'warehouse_request' => 'Anbara yeni sorğu göndəriləndə',
        'warehouse_selected' => 'Anbar seçiləndə (rezerv xahişi)',
        'warehouse_cancelled' => 'Anbar seçimi ləğv ediləndə',
        'bonus_expiring' => 'Bonusun bitməsinə 3 gün qalmış (bonus:remind-expiring cədvəli)',
    ];

    public function index(Request $request, SmsService $sms)
    {
        $tab = in_array($request->query('tab'), ['log', 'problems'], true) ? $request->query('tab') : 'templates';
        $templates = SmsTemplate::orderBy('id')->get();

        $logs = null;
        if ($tab !== 'templates') {
            $logs = SmsLog::query()
                ->when($tab === 'problems', fn ($q) => $q->problems())
                ->when($request->filled('q'), fn ($q) => $q->where('msisdn', 'like', '%'.preg_replace('/\D+/', '', (string) $request->query('q')).'%'))
                ->when($request->filled('context'), fn ($q) => $q->where('context', $request->query('context')))
                ->orderByDesc('id')->paginate(50)->withQueryString();
        }

        return view('backend.sms_templates.index', [
            'tab' => $tab,
            'templates' => $templates,
            'usage' => self::USAGE,
            'logs' => $logs,
            'contexts' => $templates->pluck('name', 'code')->all() + SmsLog::CONTEXTS,
            'problemCount' => SmsLog::problems()->where('created_at', '>=', now()->subDays(7))->count(),
            'balance' => $this->balance($sms, $request->boolean('refresh')),
        ]);
    }

    public function update(Request $request, SmsTemplate $smsTemplate)
    {
        $data=$request->validate(['template'=>['required','string','max:1000'],'active'=>['nullable','boolean']]);
        $smsTemplate->update(['template'=>$data['template'],'active'=>$request->boolean('active')]);
        return back()->with('success','SMS şablonu yeniləndi !');
    }

    /**
     * lsim balansı — hər səhifə açılışında provayderə getməmək üçün 5 dəqiqə yadda qalır.
     *
     * @return array{value: ?float, error: ?string, at: \Illuminate\Support\Carbon}
     */
    private function balance(SmsService $sms, bool $refresh): array
    {
        if ($refresh) {
            Cache::forget('sms-balance');
        }

        return Cache::remember('sms-balance', now()->addMinutes(5), function () use ($sms) {
            try {
                return ['value' => $sms->balance(), 'error' => null, 'at' => now()];
            } catch (Throwable $e) {
                return ['value' => null, 'error' => $e->getMessage(), 'at' => now()];
            }
        });
    }
}
