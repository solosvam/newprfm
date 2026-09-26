<?php
namespace App\Models\Customer;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;

class CustomerBonusTransaction extends Model {
 protected $guarded=[];
 protected function casts():array{return ['amount'=>'decimal:2'];}
 public function customer(){return $this->belongsTo(Customer::class);}
 public function order(){return $this->belongsTo(Order::class);}
}
