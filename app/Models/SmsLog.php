<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    public const SENT = 'sent';
    public const FAILED = 'failed';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function isSent(): bool
    {
        return $this->status === self::SENT;
    }
}
