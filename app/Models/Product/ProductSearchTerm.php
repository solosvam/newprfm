<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductSearchTerm extends Model
{
    protected $table = 'product_search_terms';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'term',
        'normalized_term',
        'phonetic_term',
        'token_signature',
        'source',
        'priority',
        'active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
