<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductSearchLog extends Model
{
    protected $table = 'product_search_logs';

    public $timestamps = false;

    protected $fillable = [
        'query',
        'normalized_query',
        'visitor_id',
        'user_id',
        'result_count',
        'matched_product_ids',
        'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'matched_product_ids' => 'array',
            'searched_at' => 'datetime',
        ];
    }

    public function clicks()
    {
        return $this->hasMany(ProductSearchClick::class, 'search_log_id');
    }
}
