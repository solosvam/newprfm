<?php

namespace App\Services\Crm;

use App\Models\Order\Order;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\BonusService;
use App\Services\SmsService;
use Throwable;

/**
 * Operator CRM-də sifariş yaradanda müştəriyə SMS (şablon: crm_order_accepted).
 * {bonus} — təhvil veriləndə yazılacaq bonus (BonusService::pendingFor); bonus 0-dırsa {bonus} olan cümlə göndərilmir. Xəta sifarişi pozmur.
 * true — göndərildi, false — alınmadı, null — şablon söndürülüb və ya nömrə yoxdur.
 */
class OrderAcceptedSms
{
    public function __construct(private SmsService $sms, private BonusService $bonus) {}

    public function send(Order $order, ?int $by = null): ?bool
    {
        $order->loadMissing(['customer', 'status', 'paymentMethod']);
        $mobile = $order->customer?->mobile;
        $template = SmsTemplate::where('code', 'crm_order_accepted')->where('active', 1)->first();
        if (!$mobile || !$template) {
            return null;
        }
        $bonus = $this->bonus->pendingFor($order);
        if ($bonus <= 0) { // bonus yazılmayacaq (məs. bonusla ödəniş) — {bonus} olan cümlə çıxarılır
            $template->template = trim(preg_replace('/[^.!?]*\{bonus\}[^.!?]*[.!?]?/u', '', $template->template));
        }
        $text = $template->render([
            'name' => $order->customer?->name ?? '',
            'fullname' => $order->customer?->fullname ?? '',
            'bonus' => number_format($bonus, 2, '.', ''),
            'order_no' => $order->order_no,
        ]);
        try {
            SmsLog::deliver($this->sms, $mobile, $text, 'crm_order_accepted', $order, $by);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
