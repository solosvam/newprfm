<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\NormalizeSizes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NormalizeSizesTest extends TestCase
{
    public static function names(): array
    {
        return [
            ['L 75edp', 75.0], ['m 100edt', 100.0], ['Unisex 50 edp', 50.0], ['L 30Parf', 30.0],
            ['m30edt 2', 30.0], ['30ml  22', 30.0], ['40edt 33', 40.0], ['5ml 33az', 5.0],
            ['2pcs m 100edt', 100.0], ['3pc 100 ml', 100.0], ['4pc set 100 ml', 100.0], ['100 ml +10 ml', 100.0],
            ['m 100Tes', 100.0], ['L 100edt Teste', 100.0], ['Unisex 100etp', 100.0], ['L100edp', 100.0],
            ['75edt T', 75.0], ['L 7.5edp', 7.5], ['75 old', 75.0],
            ['Standart', null], ['SET', null], ['Tester 5', null], ['L Gift Set 2p', null], ['m Pocket', null], ['L set(25', null],
        ];
    }

    #[DataProvider('names')]
    public function test_volume_is_parsed_from_legacy_size_name(string $name, ?float $volume): void
    {
        $this->assertSame($volume, NormalizeSizes::volume($name));
    }

    public function test_apply_moves_variants_skips_conflicts_and_deletes_empty_sizes(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('sizes', fn (Blueprint $t) => [$t->id(), $t->string('name_az'), $t->string('name_en')->nullable(), $t->string('name_ru')->nullable()]);
        Schema::create('product_variants', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->unsignedBigInteger('size_id'), $t->decimal('price', 10, 2), $t->boolean('active')]);
        DB::table('sizes')->insert([
            ['id' => 15, 'name_az' => '75 ml'],
            ['id' => 50, 'name_az' => 'L 75edp'],    // 2 variant: biri köçür, biri toqquşur
            ['id' => 51, 'name_az' => 'm 75edt'],    // köçür və silinir
            ['id' => 52, 'name_az' => '220ml -'],    // hədəf yoxdur
            ['id' => 53, 'name_az' => 'Standart'],   // tanınmır
        ]);
        DB::table('product_variants')->insert([
            ['id' => 1, 'product_id' => 1, 'size_id' => 50, 'price' => 1, 'active' => 1],
            ['id' => 2, 'product_id' => 2, 'size_id' => 50, 'price' => 1, 'active' => 1],
            ['id' => 3, 'product_id' => 2, 'size_id' => 15, 'price' => 1, 'active' => 1],
            ['id' => 4, 'product_id' => 3, 'size_id' => 51, 'price' => 1, 'active' => 1],
            ['id' => 5, 'product_id' => 4, 'size_id' => 52, 'price' => 1, 'active' => 1],
            ['id' => 6, 'product_id' => 5, 'size_id' => 53, 'price' => 1, 'active' => 1],
        ]);

        $this->artisan('parfumshop:normalize-sizes')->expectsOutputToContain('Köçəcək variant: 2, toqquşma: 1')->assertSuccessful();
        $this->assertSame(50, (int) DB::table('product_variants')->where('id', 1)->value('size_id'));

        $this->artisan('parfumshop:normalize-sizes --apply')->expectsOutputToContain('Köçürüldü: 2 variant. Silindi: 1 köhnə ölçü.')->assertSuccessful();

        $this->assertSame([1 => 15, 2 => 50, 3 => 15, 4 => 15, 5 => 52, 6 => 53],
            DB::table('product_variants')->pluck('size_id', 'id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame([15, 50, 52, 53], DB::table('sizes')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all());
    }
}
