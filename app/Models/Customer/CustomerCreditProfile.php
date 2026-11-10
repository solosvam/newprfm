<?php

namespace App\Models\Customer;

use Illuminate\Database\Eloquent\Model;

class CustomerCreditProfile extends Model
{
    protected $table = 'customer_credit_profiles';

    /**
     * Qəbul olunan sənəd seriyaları (yazılış Ferrum-dakı ilə eyni — extension mətnlə seçir):
     * AA…, AB — yeni biometrik vəsiqə, AZE — köhnə vəsiqə, MYİ — müvəqqəti, DYİ/DY — daimi yaşayış icazəsi.
     */
    public const ID_CARD_SERIES = ['AA', 'AZE', 'AB', 'MYİ', 'DYİ', 'AA0', 'AA1', 'AA2', 'AA3', 'AAA', 'DY']; // sıra formada belə görünür

    /** Arxa üzü də tələb olunan seriyalar (köhnə vəsiqədə məlumatın bir hissəsi arxadadır). Qalanlarına ön üz kifayətdir. */
    public const DOUBLE_SIDE_SERIES = ['AZE'];

    public static function needsBackSide(?string $series): bool
    {
        return in_array($series, self::DOUBLE_SIDE_SERIES, true);
    }

    protected $fillable = [
        'customer_id',
        'father_name',
        'fin',
        'id_card_series',
        'id_card_number',
        'relative_1_name',
        'relative_1_phone',
        'relative_2_name',
        'relative_2_phone',
        'id_card_front',
        'id_card_back',
        'workplace_name',
        'salary',
        'position', // formadan çıxarılıb, köhnə məlumat üçün qalır
    ];

    protected $casts = [
        'salary' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * $staff — CRM / operator: qohumların ad və nömrələri, iş yeri məcburi deyil
     * (operator sifarişi telefonla götürür, bu məlumatı sonra əlavə edə bilər). Saytda müştəri üçün hamısı məcburidir.
     */
    public function isComplete(bool $staff = false): bool
    {
        return !empty($this->father_name)
            && !empty($this->fin)
            && !empty($this->id_card_series)
            && !empty($this->id_card_number)
            && ($staff || (!empty($this->relative_1_name)
                && !empty($this->relative_1_phone)
                && !empty($this->relative_2_name)
                && !empty($this->relative_2_phone)
                && !empty($this->workplace_name)))
            && !empty($this->id_card_front)
            && (!self::needsBackSide($this->id_card_series) || !empty($this->id_card_back))
            && $this->salary !== null;
    }
}
