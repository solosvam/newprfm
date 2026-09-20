<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class PaymentMethodSeeder extends Seeder {
 public function run(): void {foreach([['cash','Qapıda nağd',1],['m10','m10',2],['online_card','Onlayn (kartla)',3]] as [$code,$name,$sort]) DB::table('payment_methods')->updateOrInsert(['code'=>$code],['name'=>$name,'active'=>1,'sort_order'=>$sort,'created_at'=>now(),'updated_at'=>now()]);}
}