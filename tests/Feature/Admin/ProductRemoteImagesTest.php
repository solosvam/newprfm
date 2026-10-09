<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Backend\Product\ProductsController;
use App\Http\Requests\Admin\AddProductRequest;
use App\Models\Product\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Tests\TestCase;

class ProductRemoteImagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('brands', fn (Blueprint $t) => [$t->id(), $t->string('name')]);
        Schema::create('products', fn (Blueprint $t) => [$t->id(), $t->string('name'), $t->string('slug')->nullable(), $t->unsignedBigInteger('brand_id'), $t->unsignedBigInteger('type_id')]);
        Schema::create('product_images', fn (Blueprint $t) => [$t->id(), $t->unsignedBigInteger('product_id'), $t->string('image'), $t->integer('sort_order')]);
        DB::table('brands')->insert(['id' => 1, 'name' => 'Zztestbrand']);
        DB::table('products')->insert(['id' => 1, 'name' => 'Remote Image Test', 'brand_id' => 1, 'type_id' => 1]);
        DB::table('product_images')->insert(['id' => 1, 'product_id' => 1, 'image' => 'old-cover.webp', 'sort_order' => 1]);
    }

    protected function tearDown(): void
    {
        File::delete(File::glob(public_path('frontend/uploads/products/-zztestbrand-*')));
        parent::tearDown();
    }

    private function png(): string
    {
        ob_start();
        imagepng(imagecreatetruecolor(20, 20));

        return ob_get_clean();
    }

    private function store(array $input): int
    {
        $request = AddProductRequest::create('/', 'POST', $input);
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('product_image_candidates', [
            'ia' => ['original_url' => 'https://a.test/a.jpg'],
            'ib' => ['original_url' => 'https://b.test/b.jpg'],
            'ic' => ['original_url' => 'https://c.test/c.jpg'],
        ]);

        $method = new \ReflectionMethod(ProductsController::class, 'storeSelectedRemoteImages');

        return $method->invoke(new ProductsController, Product::findOrFail(1), $request, ImageManager::usingDriver(Driver::class));
    }

    public function test_primary_image_goes_before_existing_images(): void
    {
        Http::fake(['*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);

        $failed = $this->store(['remote_image_ids' => ['ia', 'ib'], 'remote_primary_image_id' => 'ib']);

        $this->assertSame(0, $failed);
        $images = Product::findOrFail(1)->images()->get();
        $this->assertCount(3, $images);
        // Əsas şəkil (ib) birinci endirilir və mövcud şəklin qabağına keçir
        $this->assertSame('-zztestbrand-remote-image-test-1.webp', $images[0]->image);
        $this->assertSame('old-cover.webp', $images[1]->image);
        $this->assertSame('-zztestbrand-remote-image-test-2.webp', $images[2]->image);
        Http::assertSentInOrder([
            fn ($request) => $request->url() === 'https://b.test/b.jpg',
            fn ($request) => $request->url() === 'https://a.test/a.jpg',
        ]);
    }

    public function test_failed_downloads_are_counted_and_do_not_break_the_rest(): void
    {
        Http::fake([
            'a.test/*' => Http::response('Forbidden', 403),
            'b.test/*' => Http::response('<html>blocked</html>', 200, ['Content-Type' => 'text/html']),
            'c.test/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $failed = $this->store(['remote_image_ids' => ['ia', 'ib', 'ic', 'unknown']]);

        $this->assertSame(3, $failed);
        $this->assertSame(2, Product::findOrFail(1)->images()->count());
    }

    public function test_follows_redirects_and_sends_browser_headers(): void
    {
        Http::fake([
            'a.test/a.jpg' => Http::response('', 302, ['Location' => 'https://a.test/real.jpg']),
            'a.test/real.jpg' => Http::response($this->png(), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->assertSame(0, $this->store(['remote_image_ids' => ['ia']]));
        Http::assertSent(fn ($request) => $request->hasHeader('Referer', 'https://a.test/') && str_contains($request->header('User-Agent')[0], 'Chrome'));
    }
}
