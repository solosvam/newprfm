<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

class WarehouseOffer extends Model
{
    protected $guarded = [];

    /**
     * Cavabı kim yazıb: Telegram-da — düyməni basan adam (qeyddə "Telegram: Kamal"), anbar linkində — "Anbar (link)",
     * operator — $staff siyahısından ad.
     */
    public function authorName(callable $staffName): ?string
    {
        if ($this->source === 'telegram' && (int) $this->recorded_by === 0) {
            return str_starts_with((string) $this->note, 'Telegram: ') ? substr((string) $this->note, strlen('Telegram: ')) : 'Anbar';
        }

        return $staffName($this->recorded_by);
    }

    public function requestItem()
    {
        return $this->belongsTo(WarehouseRequestItem::class, 'warehouse_request_item_id');
    }

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2'];
    }
}
