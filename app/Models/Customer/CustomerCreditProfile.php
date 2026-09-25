<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerCreditProfile extends Model
{
    protected $table = 'customer_credit_profiles';

    protected $fillable = [
        'customer_id',
        'father_name',
        'fin',
        'relative_1_name',
        'relative_1_phone',
        'relative_2_name',
        'relative_2_phone',
        'id_card_front',
        'id_card_back',
        'workplace_name',
        'salary',
        'position',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function isComplete(): bool
    {
        return !empty($this->father_name)
            && !empty($this->fin)
            && !empty($this->relative_1_name)
            && !empty($this->relative_1_phone)
            && !empty($this->relative_2_name)
            && !empty($this->relative_2_phone)
            && !empty($this->id_card_front)
            && !empty($this->id_card_back)
            && !empty($this->workplace_name)
            && $this->salary !== null
            && !empty($this->position);
    }
}
