<?php
namespace App\Services;
use App\Models\Customer\Customer;
use App\Models\Order\Order;
use App\Models\Setting;

class BonusService {
 /** Sifarişə düşən bonus: (məhsullar − endirim) × faiz. Ödənişdən əvvəl göstərmək üçün də (SMS ödəniş linki) */
 public function amountForOrder(Order $order): float {
  $percent=(float) Setting::valueOf('order_bonus_percent',5);
  $eligibleAmount = max(0, (float)$order->subtotal - (float)$order->discount);
  return round($eligibleAmount*($percent/100),2);
 }

 public function earnForOrder(Customer $customer, Order $order, float $paidAmount): float {
  $bonus=$this->amountForOrder($order);
  if($bonus<=0)return 0;
  $customer->increment('bonus_balance',$bonus);
  $customer->bonusTransactions()->create(['order_id'=>$order->id,'type'=>'earn','amount'=>$bonus,'note'=>'Sifariş bonusu']);
  $order->update(['bonus_earned'=>$bonus]);
  return $bonus;
 }

 /**
  * Qeydiyyat bonusu (Ayarlar → "Qeydiyyat bonusu"): saytda SMS kodu təsdiqlənəndə və Asan sifarişdə yeni müştəriyə.
  * Hər müştəriyə bir dəfə: artıq "register" qeydi varsa heç nə yazılmır. Yazılan məbləği qaytarır (0 — verilmədi).
  */
 public function grantRegistration(Customer $customer): float {
  if ((int) Setting::valueOf('registration_bonus_enabled', 1) !== 1) return 0;
  $amount = max(0, round((float) Setting::valueOf('registration_bonus_amount', 10), 2));
  if ($amount <= 0) return 0;

  return \Illuminate\Support\Facades\DB::transaction(function () use ($customer, $amount) {
   // Eyni müştəriyə paralel iki sorğu iki bonus yazmasın
   \Illuminate\Support\Facades\DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();
   if ($customer->bonusTransactions()->where('type', 'register')->exists()) return 0;
   $customer->increment('bonus_balance', $amount);
   $customer->bonusTransactions()->create(['type' => 'register', 'amount' => $amount, 'note' => 'Qeydiyyat bonusu']);
   return $amount;
  });
 }
}
