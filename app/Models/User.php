<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $table = 'users';

    protected $guard_name = 'admin';

    protected $fillable = [
        'name',
        'surname',
        'email',
        'mobile',
        'telegram_id',
        'password',
        'active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public $timestamps = false;
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
            'location_at' => 'datetime',
        ];
    }

    /** Kuryerin son yeri Google Maps-də (kuryer tətbiqi göndərir); yer yoxdursa null */
    public function locationMapsUrl(): ?string
    {
        return $this->location_lat !== null && $this->location_lng !== null
            ? 'https://www.google.com/maps/search/?api=1&query='.$this->location_lat.','.$this->location_lng
            : null;
    }

    public function getFullNameAttribute()
    {
        return $this->name.' '.$this->surname;
    }
}

