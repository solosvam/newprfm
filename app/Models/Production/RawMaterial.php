<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Xammal (əsans, spirt, flakon, qapaq, qutu, etiket...). Qalıq — hərəkətlərin cəmi (ProductionStock::materialStocks) */
class RawMaterial extends Model
{
    protected $table = 'production_materials';

    protected $guarded = [];

    public const UNITS = ['ml' => 'ml', 'g' => 'qr', 'pcs' => 'ədəd'];

    protected function casts(): array
    {
        return ['avg_cost' => 'decimal:4', 'active' => 'boolean'];
    }

    public function unitLabel(): string
    {
        return self::UNITS[$this->unit] ?? $this->unit;
    }

    public function movements(): HasMany { return $this->hasMany(RawMaterialMovement::class); }
}
