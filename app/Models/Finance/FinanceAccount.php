<?php

namespace App\Models\Finance;

use App\Models\Procurement\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pul hesabı. Qalıq = daxil olan − çıxan (FinanceService::balances) */
class FinanceAccount extends Model
{
    public const TYPES = [
        'cash' => 'Nağd kassa',
        'bank' => 'Bank hesabı',
        'online' => 'Onlayn ödənişlər',
        'courier' => 'Kuryer',
        'owner' => 'Sahibkar',
        'warehouse' => 'Anbar',
        'expense' => 'Xərc',
        'customer' => 'Müştərilər',
    ];

    /** Şirkətin öz pulu olan hesablar (köçürmə bunların arasında xərc deyil) */
    public const OWN = ['cash', 'bank', 'online'];

    protected $fillable = ['type', 'code', 'name', 'user_id', 'warehouse_id', 'active'];

    /** Yeni hesab aktivdir (firstOrCreate-dən sonra da yaddaşda düzgün olsun) */
    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class); }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
