<?php
namespace App\Services;
use App\Models\Customer;
use App\Models\Order;
class BonusService {
 public const RATE = 0.05;
 public function earnForOrder(Customer $customer, Order $order, float $paidAmount): float {
  $bonus=round(max(0,$paidAmount)*self::RATE,2);
  if($bonus<=0)return 0;
  $customer->increment('bonus_balance',$bonus);
  $customer->bonusTransactions()->create(['order_id'=>$order->id,'type'=>'earn','amount'=>$bonus,'note'=>'Sifariş bonusu']);
  $order->update(['bonus_earned'=>$bonus]);
  return $bonus;
 }
}