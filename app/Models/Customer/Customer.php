<?php

namespace App\Models\Customer;

use App\Models\Order\Order;
use App\Models\Product\Product;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use Notifiable;

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
    public function addresses(){
        return $this->hasMany(CustomerAddress::class);
    }
    public function orders(){
        return $this->hasMany(Order::class);
    }
    public function bonusTransactions(){
        return $this->hasMany(CustomerBonusTransaction::class);
    }
    public function favoriteProducts()
    {
        return $this->belongsToMany(Product::class, 'product_favorites', 'customer_id', 'product_id')
            ->withPivot('created_at');
    }

    public function creditProfile()
    {
        return $this->hasOne(CustomerCreditProfile::class);
    }

    public function getFullNameAttribute()
    {
        return $this->name.' '.$this->surname;
    }
}
