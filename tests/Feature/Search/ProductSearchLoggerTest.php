<?php

namespace Tests\Feature\Search;

use App\Services\Search\ProductSearchLogger;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Axtarış jurnalı: yazma prosesinin izləri qalmır; nəticəsiz qeydlər toplu silinir */
class ProductSearchLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('product_search_logs', fn (Blueprint $t) => [$t->id(), $t->string('query'), $t->string('normalized_query'), $t->string('visitor_id'),
            $t->integer('user_id')->nullable(), $t->integer('result_count'), $t->json('matched_product_ids')->nullable(), $t->timestamp('searched_at')]);
        Schema::create('product_search_clicks', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('search_log_id'), $t->integer('product_id'),
            $t->integer('result_rank'), $t->timestamp('clicked_at')]);
    }

    private function log(string $query, string $visitor = 'v1'): int
    {
        return app(ProductSearchLogger::class)->record($query, $query, $visitor, null, [])->id;
    }

    public function test_only_the_last_typed_query_remains(): void
    {
        foreach (['fe', 'fer', 'ferr', 'ferre', 'ferreg', 'ferregam'] as $query) {
            $this->log($query);
        }
        $this->log('ferrega');            // backspace — "ferregam" də yazma prosesidir
        $this->log('ferre', 'v2');        // başqa ziyarətçi — toxunulmur
        $this->log('creed');              // eyni ziyarətçi, başqa söz — əvvəlki qalır

        $this->assertEqualsCanonicalizing(
            ['v1:ferrega', 'v2:ferre', 'v1:creed'],
            DB::table('product_search_logs')->get()->map(fn ($log) => "{$log->visitor_id}:{$log->normalized_query}")->all()
        );
    }

    public function test_old_and_clicked_logs_are_kept(): void
    {
        $old = $this->log('ferre');
        DB::table('product_search_logs')->where('id', $old)->update(['searched_at' => now()->subMinutes(5)]);
        $clicked = $this->log('savaj');
        DB::table('product_search_clicks')->insert(['search_log_id' => $clicked, 'product_id' => 1, 'result_rank' => 1, 'clicked_at' => now()]);

        $this->log('ferreg');
        $this->log('savaj parfum');

        $this->assertSame(4, DB::table('product_search_logs')->count());
    }

    public function test_bulk_delete_no_result_queries_via_ajax(): void
    {
        Schema::create('permissions', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
        foreach (['fere', 'fere', 'ferre', 'savaz'] as $i => $query) {
            $this->log($query, "v$i");
        }
        $this->log('dior', 'v9');
        DB::table('product_search_logs')->where('normalized_query', 'dior')->update(['result_count' => 3]);
        $user = new \App\Models\User(['name' => 'Admin']);
        $user->id = 1;
        Gate::before(fn () => true);
        $this->actingAs($user, 'admin');

        $this->deleteJson('/admin/product/search-aliases/no-result', ['queries' => ['fere', 'Ferre', 'dior']])
            ->assertOk()->assertJson(['deleted' => 3]);

        // nəticəli "dior" qeydi silinmir — yalnız nəticəsizlər
        $this->assertEqualsCanonicalizing(['savaz', 'dior'], DB::table('product_search_logs')->pluck('normalized_query')->all());
    }
}
