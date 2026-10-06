<?php
namespace App\Models\Order;
use App\Models\Credit\CreditApplication;
use App\Models\Customer\Customer;
use App\Models\Customer\CustomerAddress;
use App\Models\Payment\Payment;
use App\Models\Payment\PaymentMethod;
use Illuminate\Database\Eloquent\Model;

class Order extends Model {
    protected $guarded=[];
    protected function casts():array
    {
        return [
            'gift_wrap'=>'boolean',
            'subtotal'=>'decimal:2',
            'discount'=>'decimal:2',
            'referral_discount'=>'decimal:2',
            'total'=>'decimal:2'
        ];
    }
    /** Bank səhifəsində ödənilən üsullar (SMS ödəniş linki də yalnız bunlarda işləyir) */
    public const ONLINE_PAYMENT_CODES = ['card_online', 'birbank_installment'];

    public function isOnlinePayment(): bool
    {
        return in_array($this->paymentMethod?->code, self::ONLINE_PAYMENT_CODES, true);
    }

    public function isCancelled(): bool
    {
        return $this->status?->code === 'cancelled';
    }

    /** Bankda nəticəsi hələ bəlli olmayan ödəniş cəhdi var (uğurlu ola bilər — təkrar ödəniş olmaz) */
    public function hasPendingPayment(): bool
    {
        return $this->payments()->where('status', Payment::PENDING)->exists();
    }

    /**
     * Ödənişə başlamaq olarmı — "Sifarişlərim" və SMS linki üçün ortaq qayda:
     *  - onlayn üsul, ödənilməyib, sifariş ləğv edilməyib, gözləyən bank cəhdi yoxdur;
     *  - əvvəlki cəhd uğursuz/ləğv olub və ya operator yaradıb, heç cəhd olmayıb.
     */
    public function canStartOnlinePayment(): bool
    {
        if (!$this->isOnlinePayment() || $this->payment_status === 'paid' || $this->isCancelled()) {
            return false;
        }
        if (!in_array($this->payment_status, ['failed', 'cancelled'], true) && !$this->isAwaitingPayment()) {
            return false;
        }

        return !$this->hasPendingPayment();
    }

    /**
     * Onlayn ödənişli (kart / Birbank taksit) sifariş hələ heç ödənilməyə cəhd olunmayıb:
     * operator (CRM, asan sifariş) yaradıb, müştəri "Sifarişlərim"-dən ödəməlidir.
     * Siyahıda N+1 olmasın deyə withCount('payments') istifadə edin.
     */
    public function isAwaitingPayment(): bool
    {
        if (!in_array($this->paymentMethod?->code, ['card_online', 'birbank_installment'], true)
            || $this->payment_status !== 'pending') {
            return false;
        }
        $attempts = $this->getAttribute('payments_count')
            ?? ($this->relationLoaded('payments') ? $this->payments->count() : $this->payments()->count());

        return (int) $attempts === 0;
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Müştəriyə göstərilən tarixçə (yenisi yuxarıda): daxili mərhələlər "Hazırlanır"-a çevrilir,
     * ardıcıl eyni status bir sətir olur (ilk vaxtı ilə). Operator qeydləri yalnız ləğvdə göstərilir.
     *
     * @return \Illuminate\Support\Collection<int, array{status: OrderStatus, at: \Illuminate\Support\Carbon, note: ?string}>
     */
    public function customerTimeline()
    {
        $rows = collect();
        foreach ($this->statusLogs->sortBy('id') as $log) {
            $status = $log->status?->forCustomer();
            if (!$status || ($rows->last()['status'] ?? null)?->id === $status->id) {
                continue;
            }
            $rows->push(['status' => $status, 'at' => $log->created_at, 'note' => $status->code === 'cancelled' ? $log->note : null]);
        }

        return $rows->reverse()->values();
    }

    public function statusLogs()
    {
        return $this->hasMany(OrderStatusLog::class)->orderBy('created_at');
    }

    public function creditApplication()
    {
        return $this->hasOne(CreditApplication::class);
    }
    public function itemCancellations()
    {
        return $this->hasMany(OrderItemCancellation::class)->orderBy('id');
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
    /** Təyin olunmuş kuryer (users, "Kuryer" rolu) */
    public function courier()
    {
        return $this->belongsTo(\App\Models\User::class, 'courier_id');
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
