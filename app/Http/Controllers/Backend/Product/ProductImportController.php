<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Models\Product\Gender;
use App\Models\Product\Ingredient;
use App\Services\FragranticaImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImportController extends Controller
{
    public function preview(Request $request, FragranticaImportService $fragrantica): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        try {
            $product = $fragrantica->import($request->string('url')->toString());

            return response()->json([
                'product' => $product,
                'matches' => [
                    'brand_id' => $this->findBrand($product['brand']),
                    'gender_ids' => $this->findGenders($product['gender']),
                    'ingredient_ids' => $this->findIngredients($product['notes']),
                ],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function findBrand(string $brand): ?int
    {
        return Brand::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($brand)])
            ->value('id');
    }

    private function findGenders(?string $gender): array
    {
        $needles = match ($gender) {
            'women and men' => ['unisex', 'uniseks'],
            'women' => ['women', 'woman', 'qadın'],
            'men' => ['men', 'man', 'kişi'],
            default => [],
        };

        if (!$needles) {
            return [];
        }

        return Gender::all()
            ->filter(fn (Gender $item) => collect([$item->name_az, $item->name_en, $item->name_ru])
                ->filter()
                ->map(fn (string $name) => Str::lower($name))
                ->contains(fn (string $name) => in_array($name, $needles, true)))
            ->pluck('id')
            ->values()
            ->all();
    }

    private function findIngredients(array $notes): array
    {
        $aliases = [
            'mandarin orange' => ['mandarin', 'mandarin portağalı', 'мандарин'],
            'iso e super' => ['iso e super'],
        ];

        $wanted = collect($notes)
            ->flatMap(function (string $note) use ($aliases) {
                $key = $this->normalize($note);
                return array_merge([$key], array_map([$this, 'normalize'], $aliases[$key] ?? []));
            })
            ->unique()
            ->all();

        return Ingredient::all()
            ->filter(function (Ingredient $ingredient) use ($wanted) {
                return collect([$ingredient->name_az, $ingredient->name_en, $ingredient->name_ru])
                    ->filter()
                    ->map(fn (string $name) => $this->normalize($name))
                    ->contains(fn (string $name) => in_array($name, $wanted, true));
            })
            ->pluck('id')
            ->values()
            ->all();
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }
}
