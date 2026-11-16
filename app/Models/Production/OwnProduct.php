<?php

namespace App\Models\Production;

use App\Models\Product\Product;
use App\Models\Product\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Öz istehsalımız olan məhsul ölçüsü (kataloqdakı məhsulun variantı). Hazır mal "Öz anbarımız"dadır */
class OwnProduct extends Model
{
    protected $table = 'production_products';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['avg_cost' => 'decimal:4', 'active' => 'boolean'];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function recipe(): HasMany { return $this->hasMany(OwnProductRecipeItem::class)->orderBy('id'); }
    public function batches(): HasMany { return $this->hasMany(ProductionBatch::class)->orderByDesc('produced_at')->orderByDesc('id'); }

    /** "Brend Ad · 50 ml" */
    public function label(): string
    {
        return \App\Support\ProductTitle::of($this->product, $this->variant?->size?->name_az);
    }
}
