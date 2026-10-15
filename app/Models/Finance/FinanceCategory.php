<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Pul hərəkətinin kateqoriyası (Satış, Anbara ödəniş, Əməkhaqqı...). İki səviyyəli ağac. */
class FinanceCategory extends Model
{
    public const TYPES = ['income' => 'Mədaxil', 'expense' => 'Məxaric', 'both' => 'Hər ikisi'];

    /** Avtomatik əməliyyatların kateqoriyaları — "Yeni əməliyyat"da seçilmir */
    public const AUTO = ['sale', 'customer_refund', 'warehouse', 'warehouse_refund', 'transfer'];

    protected $fillable = ['name', 'type', 'parent_id', 'code', 'system'];

    protected function casts(): array
    {
        return ['system' => 'boolean'];
    }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function movements(): HasMany { return $this->hasMany(MoneyMovement::class, 'category_id'); }

    public static function byCode(string $code): ?self
    {
        return self::where('code', $code)->first();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Mədaxil (income) və ya Məxaric (outgo) əməliyyatında seçilə bilərmi */
    public function fits(string $kind): bool
    {
        if (in_array($this->code, self::AUTO, true)) return false;

        return $this->type === 'both' || $this->type === ($kind === 'income' ? 'income' : 'expense');
    }

    /** Ağac sırası: hər üst kateqoriyadan sonra onun alt kateqoriyaları */
    public static function tree()
    {
        $all = self::orderByDesc('system')->orderBy('name')->get();

        return $all->whereNull('parent_id')->flatMap(fn ($c) => collect([$c])->merge($all->where('parent_id', $c->id)))->values();
    }
}
