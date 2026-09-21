<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 60)->unique();
            $table->string('name', 150);
            $table->text('template');
            $table->boolean('active')->default(true);
        });

        DB::table('sms_templates')->insert([
            ['code'=>'crm_order_accepted','name'=>'CRM-dən operator sifariş qəbul etdikdə','template'=>'Hormetli {fullname}, sizin sifarishiniz qebul olundu. Qazandiginiz bonus {bonus} AZN. Bizi secdiyiniz ucun teshekkur edirik!','active'=>1],
            ['code'=>'order_sent','name'=>'Sifariş göndərildikdə','template'=>'Hormetli {fullname}, Sizin sifarishiniz artiq yoldadir. Sifarishin meblegi: {total} AZN. Toplam Bonus balansiniz: {total_bonus} AZN. Sifarishiniz ucun teshekkur edirik !','active'=>1],
            ['code'=>'website_order_accepted','name'=>'Müştəri özü saytdan sifariş verdikdə','template'=>'Hormetli {fullname}, sizin sifarishiniz qebul olundu. Qazandiginiz bonus {bonus} AZN. Bizi secdiyiniz ucun teshekkur edirik!','active'=>1],
            ['code'=>'crm_order_cancelled','name'=>'CRM-dən sifariş ləğv edildikdə','template'=>'Hormetli {fullname}, Sizin sifarishiniz legv olundu. Teshekkur edirik !','active'=>1],
        ]);
    }

    public function down(): void { Schema::dropIfExists('sms_templates'); }
};
