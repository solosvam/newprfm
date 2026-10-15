<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $guarded = [];

    /** Sorğunun göndərilmə yolu */
    public const CHANNELS = ['sms' => 'SMS', 'telegram' => 'Telegram'];

    /**
     * Telegram qrupunun çat id-si. Köhnə sistemdə qrup id-ləri "-" işarəsiz saxlanırdı (482788956) və göndərəndə
     * əvvəlinə "-" qoyulurdu — qrupların id-si mənfidir. İşarəsi olan (-1001234567890) olduğu kimi götürülür.
     */
    public function telegramChatId(): ?string
    {
        $id = trim((string) $this->telegram_id);

        return $id === '' ? null : (str_starts_with($id, '-') ? $id : '-'.$id);
    }

    public function requests()
    {
        return $this->hasMany(WarehouseRequest::class);
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
