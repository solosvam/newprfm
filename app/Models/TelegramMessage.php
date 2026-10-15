<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Anbar Telegram qrupuna göndərilmiş düyməli mesaj və onun vəziyyəti (TelegramWarehouseBot) */
class TelegramMessage extends Model
{
    public const REQUEST_ITEM = 'request_item';
    public const ALLOCATION = 'allocation';
    public const PROMPT = 'prompt';

    public const AWAIT_QUANTITY = 'await_quantity';
    public const AWAIT_PRICE = 'await_price';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['has_photo' => 'boolean'];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}
