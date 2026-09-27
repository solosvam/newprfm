<?php

namespace App\Models\Customer;

use App\Models\Order\Order;
use App\Models\Payment;
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
        'gender',
        'email',
        'mobile',
        'password',
        'active',
        'bonus_balance',
        'registration_otp_hash',
        'registration_otp_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'registration_otp_hash',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
            'registration_otp_expires_at' => 'datetime',
        ];
    }
    public function addresses(){
        return $this->hasMany(CustomerAddress::class);
    }
    public function payments()
    {
        return $this->hasMany(Payment::class);
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
