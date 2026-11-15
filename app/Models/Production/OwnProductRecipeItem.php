<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Reseptin sətri: bir şüşəyə nə qədər xammal gedir */
class OwnProductRecipeItem extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function material(): BelongsTo { return $this->belongsTo(RawMaterial::class, 'raw_material_id'); }
}
