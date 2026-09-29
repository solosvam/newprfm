<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $guarded = [];

    public function requests()
    {
        return $this->hasMany(WarehouseRequest::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
