<?php

namespace App\Models\Production;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Xammalın hərəkəti: alış (+) və istehsala sərf (−) */
class RawMaterialMovement extends Model
{
    protected $guarded = [];

    public const KINDS = ['purchase' => 'Alış', 'production' => 'İstehsal'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:4', 'occurred_at' => 'datetime'];
    }

    public function material(): BelongsTo { return $this->belongsTo(RawMaterial::class, 'raw_material_id'); }
    public function purchase(): BelongsTo { return $this->belongsTo(MaterialPurchase::class, 'material_purchase_id'); }
    public function batch(): BelongsTo { return $this->belongsTo(ProductionBatch::class, 'production_batch_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
