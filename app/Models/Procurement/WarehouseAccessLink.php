<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class WarehouseAccessLink extends Model
{
    protected $guarded = [];
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
