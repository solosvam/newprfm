<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class OrderStatusSeeder extends Seeder {
 public function run(): void {
  $statuses=[
   ['new','Sifariş verildi',1],
   ['confirmed','Təsdiqləndi',2],
   ['preparing','Hazırlanır',3],
   ['sent','Göndərildi',4],
   ['courier','Kuryerə verildi',5],
   ['delivered','Təhvil verildi',6],
   ['cancelled','Ləğv edildi',7],
  ];
  foreach($statuses as [$code,$name,$sort]){
   DB::table('order_statuses')->updateOrInsert(['code'=>$code],['name'=>$name,'active'=>1,'sort_order'=>$sort,'created_at'=>now(),'updated_at'=>now()]);
  }
 }
}