<?php
namespace App\Services;
use App\Models\Customer\Customer;
use App\Models\Order;
use App\Models\Setting;

class BonusService {
 public function earnForOrder(Customer $customer, Order $order, float $paidAmount): float {
  $percent=(float) Setting::valueOf('order_bonus_percent',5);
  $bonus=round(max(0,$paidAmount)*($percent/100),2);
  if($bonus<=0)return 0;
  $customer->increment('bonus_balance',$bonus);
  $customer->bonusTransactions()->create(['order_id'=>$order->id,'type'=>'earn','amount'=>$bonus,'note'=>'Sifariş bonusu']);
  $order->update(['bonus_earned'=>$bonus]);
  return $bonus;
 }
}
