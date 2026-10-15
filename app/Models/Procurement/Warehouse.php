<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $guarded = [];

    /** Sorğunun göndərilmə yolu */
    public const CHANNELS = ['sms' => 'SMS', 'telegram' => 'Telegram'];

    public function requests()
    {
        return $this->hasMany(WarehouseRequest::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
