<?php

namespace App\Models\Production;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** İstehsal partiyası: xammal sərfi + əlavə xərc → bir şüşənin mayası */
class ProductionBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['produced_at' => 'date', 'materials_cost' => 'decimal:2', 'extra_cost' => 'decimal:2', 'unit_cost' => 'decimal:4'];
    }

    public function ownProduct(): BelongsTo { return $this->belongsTo(OwnProduct::class); }
    public function materials(): HasMany { return $this->hasMany(RawMaterialMovement::class)->orderBy('id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
