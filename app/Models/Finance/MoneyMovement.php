<?php

namespace App\Models\Finance;

use App\Models\Order\Order;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Pul hərəkəti: from → to. Silinmir; düzəliş — əks əməliyyat (reversal_of_id) */
class MoneyMovement extends Model
{
    /** Növlər və icazəli hesab tipləri [from => [...], to => [...]] */
    public const KINDS = [
        // Kassa: Mədaxil / Məxaric — qarşı tərəf "Kənar" hesabıdır, mənası kateqoriyada (Satış, Əməkhaqqı, Sahibkar...)
        'income' => ['Mədaxil', ['external'], ['cash', 'card', 'bank', 'online']],
        // Şəxs öz cibindən ödəyibsə də Məxaricdir — şirkət ona borclu qalır
        'outgo' => ['Məxaric', ['cash', 'card', 'bank', 'online', 'courier', 'person'], ['external']],
        // Öz hesablar və şəxslər arasında: gəlir və ya xərc deyil
        'transfer' => ['Köçürmə', ['cash', 'card', 'bank', 'online', 'person'], ['cash', 'card', 'bank', 'person']],
        'warehouse_payment' => ['Anbara ödəniş', ['courier', 'cash', 'card', 'bank', 'person'], ['warehouse']],
        // Mal anbara qaytarılıb (qapıda imtina / sifariş ləğvi) — anbar ödənilən pulu geri verdi
        'warehouse_refund' => ['Anbar pulu qaytardı', ['warehouse'], ['courier', 'cash', 'card', 'bank', 'person']],
        'courier_handover' => ['Kuryer pulu təhvil verdi', ['courier'], ['cash']], // kuryer yalnız nağd verir
        'courier_advance' => ['Kuryerə avans / qaytarma', ['cash', 'card', 'bank'], ['courier']],
        // Sistem (avtomatik) — əl ilə seçilmir
        'customer_payment' => ['Müştəri ödənişi', ['customer'], ['online', 'courier', 'cash']],
        'customer_refund' => ['Müştəriyə qaytarma', ['online', 'courier', 'cash'], ['customer']],
        'reversal' => ['Əks əməliyyat', [], []],
    ];

    /** Növün öz kateqoriyası (FinanceCategory::code); Mədaxil / Məxaricdə kateqoriyanı istifadəçi seçir */
    public const KIND_CATEGORY = [
        'customer_payment' => 'sale', 'customer_refund' => 'customer_refund',
        'warehouse_payment' => 'warehouse', 'warehouse_refund' => 'warehouse_refund',
        'transfer' => 'transfer', 'courier_advance' => 'transfer', 'courier_handover' => 'transfer',
    ];

    /** "Yeni əməliyyat" pəncərəsi: Məxaric / Mədaxil / Köçürmə (kuryerə köçürmə — avans, kuryerdən — təhvil) */
    public const MANUAL = ['outgo', 'income', 'transfer', 'courier_advance', 'courier_handover'];

    /** Hesab səhifəsindən edilənlər: anbara ödəniş (anbar), pulu təhvil al (kuryer) */
    public const FROM_ACCOUNT_PAGE = ['warehouse_payment', 'courier_handover', 'warehouse_refund'];

    /** Real tarix (occurred_at) qeydə alınma anından (created_at) 1 saatdan çox əvvəldirsə — sonradan yazılıb */
    public function isBackdated(): bool
    {
        return $this->created_at && $this->occurred_at && $this->created_at->diffInMinutes($this->occurred_at, true) > 60;
    }

    protected $fillable = ['from_account_id', 'to_account_id', 'amount', 'kind', 'category_id', 'order_id', 'order_item_allocation_id',
        'payment_id', 'payment_operation_id', 'reversal_of_id', 'note', 'created_by', 'occurred_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    public function from(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'from_account_id'); }
    public function to(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'to_account_id'); }
    public function category(): BelongsTo { return $this->belongsTo(FinanceCategory::class, 'category_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function allocation(): BelongsTo { return $this->belongsTo(OrderItemAllocation::class, 'order_item_allocation_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reversalOf(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of_id'); }
    public function reversedBy(): HasOne { return $this->hasOne(self::class, 'reversal_of_id'); }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind][0] ?? $this->kind;
    }

    /** Növ + kateqoriya ("Məxaric · Əməkhaqqı"); kateqoriya növün özüdürsə təkrarlanmır */
    public function label(): string
    {
        $category = $this->relationLoaded('category') ? $this->category : null;

        return in_array($this->kind, ['income', 'outgo'], true) && $category ? $this->kindLabel().' · '.$category->name : $this->kindLabel();
    }
}
