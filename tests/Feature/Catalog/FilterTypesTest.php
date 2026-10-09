<?php

namespace Tests\Feature\Catalog;

use App\Services\CatalogService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FilterTypesTest extends TestCase
{
    public function test_filter_shows_only_types_with_active_products(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('types', fn (Blueprint $t) => [$t->id(), $t->string('name_az')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->unsignedBigInteger('type_id'), $t->boolean('active')]);
        DB::table('types')->insert([
            ['id' => 1, 'name_az' => 'Parfum suyu'],
            ['id' => 2, 'name_az' => 'Body Lotion'],  // yalnız deaktiv məhsul
            ['id' => 3, 'name_az' => 'Shower Gel'],   // məhsulu yoxdur
        ]);
        DB::table('products')->insert([
            ['name' => 'A', 'type_id' => 1, 'active' => 1],
            ['name' => 'B', 'type_id' => 2, 'active' => 0],
        ]);

        $this->assertSame(['Parfum suyu'], app(CatalogService::class)->filterTypes()->pluck('name_az')->all());
    }
}
