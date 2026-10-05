<?php
namespace App\Models\Customer;
use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerBonusTransaction extends Model {
 /** Bonus "paketi" yaradan növlər (müsbət məbləğlə). refund paket deyil — xərci geri qaytarır. */
 public const LOT_TYPES = ['earn', 'register', 'referral', 'adjustment'];

 protected $guarded=[];
 protected function casts():array{return ['amount'=>'decimal:2','expires_at'=>'datetime','expired_at'=>'datetime'];}
 public function customer(){return $this->belongsTo(Customer::class);}
 public function order(){return $this->belongsTo(Order::class);}

 /** Qazanılmış bonus paketləri */
 public function scopeLots(Builder $q): Builder {
  return $q->whereIn('type', self::LOT_TYPES)->where('amount', '>', 0);
 }
}
