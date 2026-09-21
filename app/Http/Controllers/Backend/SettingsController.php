<?php
namespace App\Http\Controllers\Backend;
use App\Http\Controllers\Controller;use App\Models\Setting;use Illuminate\Http\Request;
class SettingsController extends Controller{
 public function index(){return view('backend.settings.index',['bonusPercent'=>Setting::valueOf('order_bonus_percent',5)]);}
 public function update(Request $request){$data=$request->validate(['order_bonus_percent'=>['required','numeric','min:0','max:100']]);Setting::set('order_bonus_percent',$data['order_bonus_percent']);return back()->with('success','Ayarlar yeniləndi !');}
}