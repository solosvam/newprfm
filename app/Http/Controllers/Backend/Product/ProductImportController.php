<?php

namespace App\Http\Controllers\Backend\Product;

use App\Http\Controllers\Controller;
use App\Models\Product\Brand;
use App\Models\Product\Gender;
use App\Models\Product\Ingredient;
use App\Services\FragranticaImportService;
use App\Services\OpenAiPerfumeService;
use App\Services\SerperImageSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImportController extends Controller
{
    public function preview(Request $request, FragranticaImportService $fragrantica, OpenAiPerfumeService $openAi): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:2048', 'regex:/^https:\/\/(www\.)?fragrantica\.com\/perfume\//i'],
        ]);

        try {
            $product = $fragrantica->import($request->string('url')->toString());
        } catch (RuntimeException $exception) {
            try {
                $product = $openAi->factsFromFragrantica($request->string('url')->toString());
            } catch (RuntimeException $fallbackException) {
                return response()->json(['message' => $fallbackException->getMessage()], 422);
            }
        }

        $ingredientIds = $this->findOrCreateIngredients($product['notes'], $openAi);

        return response()->json([
            'product' => $product,
            'matches' => [
                'brand_id' => $this->findBrand($product['brand']),
                'gender_ids' => $this->findGenders($product['gender']),
                'ingredient_ids' => $ingredientIds,
                'ingredients' => Ingredient::query()
                    ->whereIn('id', $ingredientIds)
                    ->get(['id', 'name_az', 'name_en']),
            ],
        ]);
    }

    public function generateWithAi(Request $request, OpenAiPerfumeService $openAi): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:2048', 'regex:/^https:\/\/(www\.)?fragrantica\.com\/perfume\//i'],
        ]);

        try {
            $startedAt = microtime(true);
            $product = $openAi->generateFromFragrantica($request->string('url')->toString());

            return response()->json([
                'product' => $product,
                'meta' => [
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
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

    public function searchImages(Request $request, SerperImageSearchService $serper): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'max:255'],
        ]);

        try {
            $images = $serper->search($request->string('query')->toString());

            $images = collect($images)
                ->map(fn (array $image, int $index) => ['id' => $index] + $image)
                ->all();

            $request->session()->put('product_image_candidates', $images);

            return response()->json(['images' => $images]);
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
            'women and men', 'unisex' => ['unisex', 'uniseks'],
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

    private function findOrCreateIngredients(array $notes, OpenAiPerfumeService $openAi): array
    {
        $notes = collect($notes)
            ->filter(fn ($note) => is_string($note) && trim($note) !== '')
            ->map(fn (string $note) => trim($note))
            ->unique(fn (string $note) => $this->normalize($note))
            ->values();

        $existingIds = $this->findIngredients($notes->all());
        $existingNames = Ingredient::query()
            ->whereIn('id', $existingIds)
            ->pluck('name_en')
            ->map(fn (string $name) => $this->normalize($name))
            ->all();

        $missing = $notes
            ->reject(fn (string $note) => in_array($this->normalize($note), $existingNames, true))
            ->values()
            ->all();

        if ($missing) {
            $translations = $openAi->translateIngredients($missing);

            foreach ($translations as $translation) {
                $nameEn = trim((string) ($translation['name_en'] ?? ''));
                $nameAz = trim((string) ($translation['name_az'] ?? ''));
                $nameRu = trim((string) ($translation['name_ru'] ?? ''));

                if ($nameEn === '' || $nameAz === '' || $nameRu === '') {
                    continue;
                }

                $original = collect($missing)->first(
                    fn (string $note) => $this->normalize($note) === $this->normalize($nameEn)
                );

                if (!$original) {
                    continue;
                }

                Ingredient::firstOrCreate(
                    ['name_en' => $original],
                    ['name_az' => $nameAz, 'name_ru' => $nameRu]
                );
            }
        }

        return $this->findIngredients($notes->all());
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
