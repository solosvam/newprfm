<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** Popup-un gün üzrə sayğacları: shown / clicked / closed / dismissed */
class PopupStat extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['day' => 'date'];

    public static function bump(int $popupId, string $column): void
    {
        $day = today()->toDateString();
        DB::table('popup_stats')->insertOrIgnore(['popup_id' => $popupId, 'day' => $day]);
        DB::table('popup_stats')->where('popup_id', $popupId)->where('day', $day)->increment($column);
    }
}
