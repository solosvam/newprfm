<?php
namespace App\Models\Order;
use App\Models\Credit\CreditApplication;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use App\Models\PaymentMethod;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class Order extends Model {
    protected $guarded=[];
    protected function casts():array
    {
        return [
            'gift_wrap'=>'boolean',
            'subtotal'=>'decimal:2',
            'discount'=>'decimal:2',
            'total'=>'decimal:2'
        ];
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function statusLogs()
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('created_at');
    }

    public function creditApplication()
    {
        return $this->hasOne(CreditApplication::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function status()
    {
        return $this->belongsTo(OrderStatus::class,'order_status_id');
    }
    public function address()
    {
        return $this->belongsTo(CustomerAddress::class,'customer_address_id');
    }
}
