<?php

namespace App\Models\Product;

use Illuminate\Database\Eloquent\Model;

class ProductSearchClick extends Model
{
    protected $table = 'product_search_clicks';

    public $timestamps = false;

    protected $fillable = [
        'search_log_id',
        'product_id',
        'result_rank',
        'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function searchLog()
    {
        return $this->belongsTo(ProductSearchLog::class, 'search_log_id');
    }
}
