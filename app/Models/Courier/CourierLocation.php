<?php

namespace App\Models\Courier;

use Illuminate\Database\Eloquent\Model;

/** Kuryerin yer tarixçəsinin bir nöqtəsi (kuryer tətbiqi göndərir) */
class CourierLocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'lat', 'lng', 'accuracy', 'recorded_at'];

    protected function casts(): array
    {
        return ['recorded_at' => 'datetime', 'lat' => 'float', 'lng' => 'float'];
    }

    public function mapsUrl(): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.$this->lat.','.$this->lng;
    }
}
