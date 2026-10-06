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
        'warehouse_payment' => ['Anbara ödəniş', ['courier', 'cash', 'bank', 'owner'], ['warehouse']],
        // Mal anbara qaytarılıb (qapıda imtina / sifariş ləğvi) — anbar ödənilən pulu geri verdi
        'warehouse_refund' => ['Anbar pulu qaytardı', ['warehouse'], ['courier', 'cash', 'bank', 'owner']],
        'courier_handover' => ['Kuryer pulu təhvil verdi', ['courier'], ['cash']], // kuryer yalnız nağd verir
        'courier_advance' => ['Kuryerə avans / qaytarma', ['cash', 'bank'], ['courier']],
        'expense' => ['Xərc', ['cash', 'bank', 'owner', 'courier'], ['expense']],
        'transfer' => ['Hesablar arası köçürmə', ['cash', 'bank', 'online'], ['cash', 'bank']],
        'owner_contribution' => ['Sahibkar pul qoydu', ['owner'], ['cash', 'bank']],
        'owner_repayment' => ['Sahibkara qaytarıldı', ['cash', 'bank'], ['owner']],
        // Sistem (avtomatik) — əl ilə seçilmir
        'customer_payment' => ['Müştəri ödənişi', ['customer'], ['online', 'courier', 'cash']],
        'customer_refund' => ['Müştəriyə qaytarma', ['online', 'courier', 'cash'], ['customer']],
        'reversal' => ['Əks əməliyyat', [], []],
    ];

    /** "Yeni əməliyyat" pəncərəsində seçilənlər */
    public const MANUAL = ['courier_advance', 'expense', 'transfer', 'owner_contribution', 'owner_repayment'];

    /** Hesab səhifəsindən edilənlər: anbara ödəniş (anbar), pulu təhvil al (kuryer) */
    public const FROM_ACCOUNT_PAGE = ['warehouse_payment', 'courier_handover', 'warehouse_refund'];

    /** Real tarix (occurred_at) qeydə alınma anından (created_at) 1 saatdan çox əvvəldirsə — sonradan yazılıb */
    public function isBackdated(): bool
    {
        return $this->created_at && $this->occurred_at && $this->created_at->diffInMinutes($this->occurred_at, true) > 60;
    }

    protected $fillable = ['from_account_id', 'to_account_id', 'amount', 'kind', 'order_id', 'order_item_allocation_id',
        'payment_id', 'payment_operation_id', 'reversal_of_id', 'note', 'created_by', 'occurred_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'occurred_at' => 'datetime'];
    }

    public function from(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'from_account_id'); }
    public function to(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'to_account_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function allocation(): BelongsTo { return $this->belongsTo(OrderItemAllocation::class, 'order_item_allocation_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reversedBy(): HasOne { return $this->hasOne(self::class, 'reversal_of_id'); }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind][0] ?? $this->kind;
    }
}
