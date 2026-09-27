<?php
namespace App\Services;
use App\Models\Setting;
class ShopPricing {
 public function delivery():array {
  $mode=Setting::valueOf('delivery_mode','free');
  return ['mode'=>$mode,'fee'=>in_array($mode,['paid','threshold'],true)?(float)Setting::valueOf('delivery_fee',0):0,'free_from'=>$mode==='threshold'?(float)Setting::valueOf('free_delivery_from',0):0];
 }
 public function deliveryFee(float $goods):float {
  $d=$this->delivery();
  return $d['mode']==='free'||($d['free_from']>0&&$goods >= $d['free_from'])?0:$d['fee'];
 }
 public function bonusRate():float{return (float)Setting::valueOf('order_bonus_percent',5)/100;}
}
