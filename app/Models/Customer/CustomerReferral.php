<?php

namespace App\Models\Customer;

use App\Models\Order\Order;
use Illuminate\Database\Eloquent\Model;

class CustomerReferral extends Model
{
    public const STATUS_REGISTERED = 'registered';
    public const STATUS_REWARDED = 'rewarded';
    public const STATUS_REJECTED = 'rejected';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'referrer_amount' => 'decimal:2',
            'invitee_amount' => 'decimal:2',
            'referrer_rewardable' => 'boolean',
            'rewarded_at' => 'datetime',
        ];
    }

    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }

    public function invitee()
    {
        return $this->belongsTo(Customer::class, 'invitee_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
