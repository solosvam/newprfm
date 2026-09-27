<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Existing credit applications are intentionally discarded.
        Schema::dropIfExists('credit_applications');
        Schema::create('credit_statuses', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('name_az', 100);
            $t->string('name_en', 100);
            $t->string('name_ru', 100);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        foreach ([
            ['pending','Gözləmədə','Pending','Ожидает',1],
            ['reviewing','Yoxlanılır','Under review','На рассмотрении',2],
            ['approved','Təsdiqlənib','Approved','Одобрено',3],
            ['rejected','İmtina edilib','Rejected','Отклонено',4],
            ['cancelled','Ləğv edilib','Cancelled','Отменено',5],
        ] as [$code,$az,$en,$ru,$sort]) {
            DB::table('credit_statuses')->insert(['code'=>$code,'name_az'=>$az,'name_en'=>$en,'name_ru'=>$ru,'sort_order'=>$sort,'created_at'=>now(),'updated_at'=>now()]);
        }
        Schema::create('credit_applications', function (Blueprint $t) {
            $t->id();
            $t->integer('customer_id');
            $t->unsignedBigInteger('order_id')->unique();
            $t->unsignedBigInteger('credit_period_id');
            $t->decimal('interest_rate', 8, 2);
            $t->decimal('total', 12, 2);
            $t->decimal('monthly', 12, 2);
            $t->unsignedBigInteger('credit_status_id');
            $t->timestamps();
            $t->foreign('customer_id')->references('id')->on('customers');
            $t->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $t->foreign('credit_period_id')->references('id')->on('credit_periods');
            $t->foreign('credit_status_id')->references('id')->on('credit_statuses');
        });
    }
    public function down(): void {
        Schema::dropIfExists('credit_applications');
        Schema::dropIfExists('credit_statuses');
    }
};