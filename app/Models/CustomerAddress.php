<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerAddress extends Model { protected $guarded=[]; protected function casts():array{return ['is_default'=>'boolean'];} public function customer(){return $this->belongsTo(Customer::class);} public function getLabelAttribute():string{return trim(($this->title ? $this->title.' — ' : '').$this->city.', '.$this->address);} }