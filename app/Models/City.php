<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = ['name', 'sort_order', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** Ünvan formaları üçün: aktiv şəhərlər, siyahıdakı sıra ilə */
    public function scopeForSelect(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('sort_order')->orderBy('name');
    }
}
