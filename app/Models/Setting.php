<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class Setting extends Model{protected $guarded=[];public static function valueOf(string $key,$default=null){return static::where('key',$key)->value('value') ?? $default;}public static function set(string $key,$value):void{static::updateOrCreate(['key'=>$key],['value'=>$value]);}}