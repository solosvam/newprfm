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
        'cash' => 'Nağd',
        'card' => 'Kart',
        'bank' => 'Bank',
        'online' => 'Onlayn ödənişlər',
        'person' => 'Şəxs',
        'courier' => 'Kuryer',
        'warehouse' => 'Anbar',
        'external' => 'Kənar',
        'customer' => 'Müştərilər',
    ];

    /** Şirkətin öz pulu olan hesablar — "Hesablar"ın cəmi bunlardır */
    public const OWN = ['cash', 'card', 'bank', 'online'];

    /** "Yeni hesab" ilə yaradılan tiplər. Şəxsin pulu şirkətin pulu deyil: mənfi qalıq — ona borcumuz */
    public const CREATABLE = ['cash', 'card', 'bank', 'person'];

    protected $fillable = ['type', 'code', 'name', 'description', 'user_id', 'warehouse_id', 'active'];

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
