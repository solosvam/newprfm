<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Köhnə saytın təsdiqlənmiş rəyləri → yeni saytdakı ətirlərə.
 * Mənbə: database/data/legacy-reviews.json — köhnə bazanın oc_review cədvəlindən (19.11.2026 dump) yalnız təsdiqlənmiş
 * (status = 1) rəylər; mağazanın öz cavabları ("www.parfumshop.az" adı ilə) çıxarılıb. Müştərilərin sualları qalıb —
 * köçürəndən sonra admində (Kataloq → Rəylər) baxılıb silinir.
 * Məhsul — products.old_id ilə; rəy müştəriyə bağlanmır (köhnə rəylərin hamısında customer_id = 0), ad author_name-də.
 * Standart — yalnız yoxlama; --apply ilə yazılır. Təkrar işlədəndə artıq köçürülən rəy (legacy_key) ötürülür.
 */
class ImportLegacyReviews extends Command
{
    protected $signature = 'parfumshop:import-reviews
        {--apply : Rəyləri bazaya yaz (yoxsa yalnız yoxlama)}
        {--file= : JSON faylı (standart: database/data/legacy-reviews.json)}';

    protected $description = 'Köhnə ParfumShop saytının təsdiqlənmiş rəylərini yeni saytdakı məhsullara köçürür';

    public function handle(): int
    {
        $path = $this->option('file') ?: database_path('data/legacy-reviews.json');
        $reviews = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (!is_array($reviews)) {
            $this->error('Rəy faylı oxunmadı: '.$path);

            return self::FAILURE;
        }
        $apply = (bool) $this->option('apply');
        $products = DB::table('products')->whereNotNull('old_id')->orderBy('id')->pluck('id', 'old_id');
        $existing = DB::table('product_reviews')->whereNotNull('legacy_key')->pluck('legacy_key')->flip();

        $counts = ['created' => 0, 'already' => 0, 'no_product' => 0];
        $missing = [];
        foreach ($reviews as $review) {
            $key = sha1('oc_review:'.$review['id']);
            if (isset($existing[$key])) {
                $counts['already']++;
                continue;
            }
            $productId = $products[$review['product']] ?? null;
            if (!$productId) {
                $counts['no_product']++;
                $missing[$review['product']] = true;
                continue;
            }
            $text = trim(html_entity_decode(str_replace("\r\n", "\n", (string) $review['text']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text === '') {
                continue;
            }
            $date = $review['date'] ? Carbon::parse($review['date']) : now();
            if ($apply) {
                DB::table('product_reviews')->insert([
                    'product_id' => $productId, 'customer_id' => null,
                    'author_name' => mb_substr(trim(html_entity_decode((string) $review['author'], ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 100) ?: 'Müştəri',
                    'rating' => min(5, max(1, (int) $review['rating'])), 'comment' => $text, 'active' => 1, 'legacy_key' => $key,
                    'created_at' => $date, 'updated_at' => $date,
                ]);
            }
            $counts['created']++;
        }

        $this->table(['', 'Say'], [
            ['Fayldakı rəy', count($reviews)],
            ['Artıq köçürülüb', $counts['already']],
            ['Yeni saytda məhsulu yoxdur (köçürülmür)', $counts['no_product']],
            [$apply ? 'Yazıldı' : 'Yazılacaq', $counts['created']],
        ]);
        if ($missing) {
            $this->line('Məhsulu tapılmayan köhnə ID-lər: '.implode(', ', array_keys($missing)));
        }
        if (!$apply) {
            $this->comment('Yazmaq üçün: php artisan parfumshop:import-reviews --apply');
        }

        return self::SUCCESS;
    }
}
