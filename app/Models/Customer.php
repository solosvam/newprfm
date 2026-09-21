<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guard = 'web';

    protected $fillable = [
        'name',
        'surname',
        'email',
        'mobile',
        'password',
        'active',
        'bonus_balance',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }
    public function addresses(){ return $this->hasMany(CustomerAddress::class); }
    public function orders(){ return $this->hasMany(Order::class); }
    public function bonusTransactions(){ return $this->hasMany(CustomerBonusTransaction::class); }
}
