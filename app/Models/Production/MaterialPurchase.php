<?php

namespace App\Models\Production;

use App\Models\Finance\FinanceAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Xammal alışı. payment: paid — balansdan ödənilib, debt — tədarükçüyə borc, opening — başlanğıc qalıq (pul hərəkəti yoxdur) */
class MaterialPurchase extends Model
{
    protected $table = 'production_material_purchases';

    protected $guarded = [];

    public const PAYMENTS = ['paid' => 'Ödənilib', 'debt' => 'Borca', 'opening' => 'Başlanğıc qalıq'];

    protected function casts(): array
    {
        return ['purchased_at' => 'date', 'total' => 'decimal:2', 'paid' => 'decimal:2'];
    }

    public function supplier(): BelongsTo { return $this->belongsTo(FinanceAccount::class, 'supplier_account_id'); }
    public function lines(): HasMany { return $this->hasMany(RawMaterialMovement::class)->orderBy('id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
