<?php

namespace App\Console\Commands;

use App\Models\Product\Size;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Köhnə saytdan gələn ölçüləri ("L 75edp", "m 100edt", "2pcs m 100edt") standart "N ml" ölçülərinə köçürür.
 *
 * - Həcm addan tanınır: vahidin (ml, edp, edt, edc, ed, etp, parf, tes, old) yanındakı rəqəm,
 *   olmasa L / m / Unisex-dən sonrakı rəqəm. Dəst sayı (2pcs, 3pc) heç vaxt həcm sayılmır.
 * - "N ml" ölçüsü bazada yoxdursa, ölçüyə toxunulmur (əl ilə düzəldilir).
 * - Məhsulda artıq hədəf ölçülü variant varsa (toqquşma), həmin variantə toxunulmur.
 * - Variantın ID-si dəyişmir (sifarişlər, səbətlər pozulmur); boşalan köhnə ölçü silinir.
 */
class NormalizeSizes extends Command
{
    protected $signature = 'parfumshop:normalize-sizes {--apply : Köçür və boşalan ölçüləri sil (olmasa yalnız göstərir)}';

    protected $description = 'Köhnə ölçüləri (L 75edp, m 100edt …) standart "N ml" ölçülərinə köçürür';

    private const STANDARD = '/^(\d+(?:\.\d+)?) ml$/u';

    public function handle(): int
    {
        $sizes = Size::orderBy('name_az')->get();

        $standard = $sizes->filter(fn (Size $size) => preg_match(self::STANDARD, $size->name_az))
            ->mapWithKeys(fn (Size $size) => [$this->key((float) $size->name_az) => $size]);

        $variants = DB::table('product_variants')->get(['id', 'product_id', 'size_id'])->groupBy('size_id');
        $productSizes = DB::table('product_variants')->get(['product_id', 'size_id'])
            ->map(fn ($row) => $row->product_id.':'.$row->size_id)->flip();

        $moves = [];
        $unrecognized = [];
        $noTarget = [];
        $conflicts = [];

        foreach ($sizes as $size) {
            if (preg_match(self::STANDARD, $size->name_az)) {
                continue;
            }

            $sizeVariants = $variants->get($size->id, collect());
            $volume = self::volume($size->name_az);

            if ($volume === null) {
                $unrecognized[] = [$size->id, $size->name_az, $sizeVariants->count()];
                continue;
            }

            $target = $standard->get($this->key($volume));

            if (!$target) {
                $noTarget[] = [$size->id, $size->name_az, $sizeVariants->count(), $this->key($volume).' ml'];
                continue;
            }

            $movable = [];

            foreach ($sizeVariants as $variant) {
                if (isset($productSizes[$variant->product_id.':'.$target->id])) {
                    $conflicts[] = [$variant->product_id, $size->name_az, $target->name_az];
                } else {
                    $movable[] = $variant->id;
                }
            }

            $moves[] = ['size' => $size, 'target' => $target, 'variants' => $movable, 'total' => $sizeVariants->count()];
        }

        $this->table(['ID', 'Köhnə ölçü', 'Variant', 'Köçəcək', '→ Hədəf'], array_map(fn ($move) => [
            $move['size']->id, $move['size']->name_az, $move['total'], count($move['variants']), $move['target']->name_az.' (#'.$move['target']->id.')',
        ], $moves));

        if ($conflicts) {
            $this->warn('Toqquşma — məhsulda artıq hədəf ölçü var, toxunulmur:');
            $this->table(['Məhsul ID', 'Köhnə ölçü', 'Artıq var'], $conflicts);
        }

        if ($noTarget) {
            $this->warn('Hədəf "N ml" ölçüsü bazada yoxdur — toxunulmur (əl ilə düzəldin):');
            $this->table(['ID', 'Ölçü', 'Variant', 'Lazım olan'], $noTarget);
        }

        if ($unrecognized) {
            $this->warn('Həcm tanınmadı — toxunulmur:');
            $this->table(['ID', 'Ölçü', 'Variant'], $unrecognized);
        }

        $movable = array_sum(array_map(fn ($move) => count($move['variants']), $moves));
        $this->line('Köçəcək variant: '.$movable.', toqquşma: '.count($conflicts));

        if (!$this->option('apply')) {
            $this->warn('Yoxlama rejimi — heç nə dəyişmədi. Tətbiq etmək üçün --apply əlavə edin.');
            return self::SUCCESS;
        }

        $deleted = 0;

        DB::transaction(function () use ($moves, &$deleted) {
            foreach ($moves as $move) {
                if ($move['variants']) {
                    DB::table('product_variants')->whereIn('id', $move['variants'])->update(['size_id' => $move['target']->id]);
                }

                if (!DB::table('product_variants')->where('size_id', $move['size']->id)->exists()) {
                    $move['size']->delete();
                    $deleted++;
                }
            }
        });

        $this->info("Köçürüldü: {$movable} variant. Silindi: {$deleted} köhnə ölçü.");

        return self::SUCCESS;
    }

    /** Ölçü adından həcm (ml); tanınmasa null */
    public static function volume(string $name): ?float
    {
        // Dəst sayı (2pcs, 3pc, 2p) həcm deyil
        $name = preg_replace('/\d+\s*(pcs|pc|p)\b/iu', ' ', $name);

        if (preg_match('/(\d+(?:\.\d+)?)\s*(ml|edp|edt|edc|etp|ed|parf|tes|old)/iu', $name, $match)) {
            return (float) $match[1];
        }

        if (preg_match('/(?:^|\s)(?:l|m|unisex)\s*(\d+(?:\.\d+)?)(?!\d)/iu', $name, $match)) {
            return (float) $match[1];
        }

        return null;
    }

    private function key(float $volume): string
    {
        return rtrim(rtrim(number_format($volume, 2, '.', ''), '0'), '.');
    }
}
