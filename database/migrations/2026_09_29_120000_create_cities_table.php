<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Şəhərlər siyahısı (ünvan formalarında select) + customer_addresses.city_id.
 * customer_addresses.city (mətn) saxlanılır: şəhərin adı ora da yazılır ki,
 * ünvanı göstərən köhnə kod dəyişmədən işləsin.
 */
return new class extends Migration {
    /** Köhnə saytdakı ID-lər saxlanılıb, rusca adlar silinib. */
    private const CITIES = [
        1 => 'Bakı', 2 => 'Gəncə', 3 => 'Naxçıvan', 4 => 'Abşeron', 5 => 'Sumqayıt',
        7 => 'Astara', 8 => 'Ağcabədi', 9 => 'Ağdam', 10 => 'Ağdaş', 11 => 'Ağstafa',
        12 => 'Ağsu', 13 => 'Balakən', 14 => 'Beyləqan', 15 => 'Biləsuvar', 16 => 'Bərdə',
        17 => 'Cəlilabad', 18 => 'Füzuli', 19 => 'Daşkəsən', 20 => 'Goranboy', 21 => 'Göygöl',
        22 => 'Göyçay', 23 => 'Gədəbəy', 24 => 'Hacıqabul', 25 => 'Kürdəmir', 26 => 'Lerik',
        27 => 'Lənkəran', 28 => 'Masallı', 29 => 'Mingəçevir', 30 => 'Naftalan', 31 => 'Neftçala',
        32 => 'Qax', 33 => 'Qazax', 34 => 'Qobustan', 35 => 'Quba', 36 => 'Qusar',
        37 => 'Qəbələ', 38 => 'Saatlı', 39 => 'Sabirabad', 40 => 'Salyan', 41 => 'Samux',
        42 => 'Siyəzən', 43 => 'Tovuz', 44 => 'Tərtər', 45 => 'Ucar', 46 => 'Xaçmaz',
        47 => 'Xızı', 48 => 'Yardımlı', 49 => 'Yevlax', 50 => 'Zaqatala', 51 => 'Zərdab',
        52 => 'İmişli', 53 => 'İsmayıllı', 54 => 'Şabran', 55 => 'Şamaxı', 56 => 'Şirvan',
        57 => 'Şəki', 58 => 'Şəmkir', 59 => 'Tbilisi',
    ];

    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $sort = 0;
        DB::table('cities')->insert(collect(self::CITIES)->map(function ($name, $id) use (&$sort, $now) {
            return ['id' => $id, 'name' => $name, 'sort_order' => ++$sort, 'active' => true, 'created_at' => $now, 'updated_at' => $now];
        })->values()->all());

        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->foreignId('city_id')->nullable()->after('title')->constrained('cities')->nullOnDelete();
            $table->string('city', 100)->nullable()->change();
        });

        // Mövcud ünvanlar: şəhər adı siyahıdakı ilə üst-üstə düşürsə city_id yazılır
        $ids = collect(self::CITIES)->mapWithKeys(fn ($name, $id) => [mb_strtolower($name) => $id]);
        DB::table('customer_addresses')->whereNull('city_id')->whereNotNull('city')->orderBy('id')
            ->each(function ($row) use ($ids) {
                $key = mb_strtolower(trim(explode('|', $row->city)[0]));
                if ($id = $ids[$key] ?? null) {
                    DB::table('customer_addresses')->where('id', $row->id)->update(['city_id' => $id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
        });
        Schema::dropIfExists('cities');
    }
};
