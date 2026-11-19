<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Müştərinin messencer hesabı (müştəri botu) — App\Services\Messenger */
class CustomerMessengerAccount extends Model
{
    public const TELEGRAM = 'telegram';

    public const WHATSAPP = 'whatsapp';

    protected $fillable = ['customer_id', 'channel', 'external_id', 'phone', 'display_name', 'linked_via'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
