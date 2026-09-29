<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class AllocationStatusLog extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
