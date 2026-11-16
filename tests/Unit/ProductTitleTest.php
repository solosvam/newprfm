<?php

namespace Tests\Unit;

use App\Models\Product\Brand;
use App\Models\Product\Gender;
use App\Models\Product\Product;
use App\Models\Product\Type;
use App\Support\ProductTitle;
use PHPUnit\Framework\TestCase;

class ProductTitleTest extends TestCase
{
    private function product(string $brand, string $name, ?string $type, array $genders): Product
    {
        $p = new Product(['name' => $name]);
        $p->setRelation('brand', new Brand(['name' => $brand]));
        $p->setRelation('type', $type ? (new Type())->forceFill(['name_az' => $type, 'name_en' => $type]) : null);
        $p->setRelation('genders', collect(array_map(fn ($g) => (new Gender())->forceFill(['name_az' => $g, 'name_en' => '']), $genders)));

        return $p;
    }

    public function test_brand_model_gender_type_size(): void
    {
        $this->assertSame('Narciso Rodriguez Pure Musc For Her L EDP 50ml',
            ProductTitle::of($this->product('Narciso Rodriguez', 'Pure Musc For Her', 'Eau De Parfum', ['Qadın üçün']), '50 ml'));
        $this->assertSame('Dior Sauvage M EDT 100ml', ProductTitle::of($this->product('Dior', 'Sauvage', 'Eau De Toilette', ['Kişi üçün']), '100 ml'));
        $this->assertSame('Kilian Angels Share U Extrait', ProductTitle::of($this->product('Kilian', 'Angels Share', 'Extrait De Parfum', ['Unisex'])));
        // Cinsi və növü yoxdursa — buraxılır; model brendlə başlayırsa — brend təkrarlanmır
        $this->assertSame('Agent Provocateur 30ml', ProductTitle::of($this->product('Agent Provocateur', 'Agent Provocateur', null, []), '30 ml'));
        $this->assertSame('X Lotion Body Lotion', ProductTitle::of($this->product('X', 'Lotion', 'Body Lotion', [])));
        $this->assertSame('Silinmiş məhsul', ProductTitle::of(null));
    }
}
