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
 * {bonus} — təhvil veriləndə yazılacaq bonus (BonusService::pendingFor). Xəta sifarişi pozmur.
 * true — göndərildi, false — alınmadı, null — şablon söndürülüb və ya nömrə yoxdur.
 */
class OrderAcceptedSms
{
    public function __construct(private SmsService $sms, private BonusService $bonus) {}

    public function send(Order $order, ?int $by = null): ?bool
    {
        $order->loadMissing(['customer', 'status', 'paymentMethod']);
        $mobile = $order->customer?->mobile;
        $text = SmsTemplate::message('crm_order_accepted', [
            'name' => $order->customer?->name ?? '',
            'fullname' => $order->customer?->fullname ?? '',
            'bonus' => number_format($this->bonus->pendingFor($order), 2, '.', ''),
            'order_no' => $order->order_no,
        ]);
        if (!$mobile || !$text) {
            return null;
        }

        try {
            SmsLog::deliver($this->sms, $mobile, $text, 'crm_order_accepted', $order, $by);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
