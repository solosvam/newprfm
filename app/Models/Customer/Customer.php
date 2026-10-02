<?php

namespace App\Models\Customer;

use App\Models\Order\Order;
use App\Models\Payment\Payment;
use App\Models\Product\Product;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use Notifiable;

    protected $guard = 'web';

    /** Müştəri haradan yaranıb (customers.source) */
    public const SOURCES = [
        'website' => 'Saytda qeydiyyat',
        'crm' => 'CRM (operator)',
        'assistant' => 'Operator paneli (extension)',
        'easy_order' => 'Asan sifarişdən',
        'legacy' => 'Köhnə sistemdən',
    ];

    public function sourceLabel(): ?string
    {
        $code = $this->source ?? ($this->old_customer_id ? 'legacy' : null);

        return $code ? (self::SOURCES[$code] ?? $code) : null;
    }

    protected $fillable = [
        'old_customer_id',
        'source',
        'referral_code',
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

    /** Bu müştərinin dəvət etdikləri */
    public function referrals()
    {
        return $this->hasMany(CustomerReferral::class, 'referrer_id');
    }

    /** Bu müştəri kim tərəfindən dəvət olunub */
    public function referredBy()
    {
        return $this->hasOne(CustomerReferral::class, 'invitee_id');
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
