<?php

namespace App\Models\Courier;

use Illuminate\Database\Eloquent\Model;

/** Kuryerin bildirişi (kuryer tətbiqi → Bildirişlər; CourierNotifier yazır) */
class CourierNotification extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }

    public static function unreadCount(int $userId): int
    {
        return (int) rescue(fn () => static::where('user_id', $userId)->whereNull('read_at')->count(), 0, false);
    }
}
