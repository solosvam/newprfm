<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CreditStatus extends Model {
    protected $guarded = [];
    public function getLocalizedNameAttribute(): string {
        $locale = in_array(app()->getLocale(), ['az','en','ru'], true) ? app()->getLocale() : 'az';
        return $this->{'name_'.$locale} ?: $this->name_az;
    }
}