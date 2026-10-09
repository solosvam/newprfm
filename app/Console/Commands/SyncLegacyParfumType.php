<?php

namespace App\Console\Commands;

use App\Models\Product\Product;
use App\Models\Product\Type;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Köhnə sistemdə parfum_type = 'Parfum' olan məhsulları yeni sistemdə verilən tipə keçirir.
 * Siyahı köhnə saytın migration-parfum-type.php?parfum_type=Parfum faylından gəlir.
 */
class SyncLegacyParfumType extends Command
{
    protected $signature = 'parfumshop:sync-parfum-type
        {--parfum-type=Parfum : Köhnə sistemdəki parfum_type dəyəri}
        {--type-id=5 : Yeni sistemdə təyin olunacaq type_id}
        {--apply : Bazaya yaz (olmasa yalnız göstərir)}';

    protected $description = 'Köhnə parfum_type-a görə məhsulların tipini yeniləyir';

    public function handle(): int
    {
        $type = Type::find((int) $this->option('type-id'));

        if (!$type) {
            $this->error('type_id '.$this->option('type-id').' tapılmadı.');
            return self::FAILURE;
        }

        $parfumType = (string) $this->option('parfum-type');

        $response = Http::acceptJson()->timeout(60)
            ->get('https://www.parfumshop.az/migration-parfum-type.php', ['parfum_type' => $parfumType])
            ->json();

        if (!($response['success'] ?? false) || !isset($response['product_ids'])) {
            $this->error('Köhnə sayt siyahını qaytarmadı. migration-parfum-type.php köhnə serverə yüklənib?');
            return self::FAILURE;
        }

        $oldIds = array_values(array_unique(array_map('intval', $response['product_ids'])));

        $products = Product::whereIn('old_id', $oldIds);
        $found = (clone $products)->count();
        $toChange = (clone $products)->where('type_id', '!=', $type->id)->count();

        $this->line("Köhnə sistemdə parfum_type = '{$parfumType}': ".count($oldIds).' məhsul');
        $this->line('Yeni bazada old_id ilə tapılan: '.$found.' (tapılmayan: '.(count($oldIds) - $found).')');
        $this->line("Hədəf tip: #{$type->id} {$type->name_az}");
        $this->line('Tipi dəyişəcək: '.$toChange);

        if (!$this->option('apply')) {
            $this->warn('Yoxlama rejimi — bazaya yazılmadı. Yazmaq üçün --apply əlavə edin.');
            return self::SUCCESS;
        }

        $updated = (clone $products)->where('type_id', '!=', $type->id)->update(['type_id' => $type->id]);
        $this->info('Yeniləndi: '.$updated);

        return self::SUCCESS;
    }
}
