<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Köhnə saytın təsdiqlənmiş rəyləri → yeni saytdakı ətirlərə.
 * Mənbə: köhnə OpenCart-ın açıq rəy səhifəsi (index.php?route=product/product/review&product_id=X&page=N) —
 * orada yalnız təsdiqlənmiş rəylər görünür: ad, ulduz, tarix, mətn. Məhsul — products.old_id ilə tapılır.
 * Rəy müştəriyə bağlanmır (köhnə səhifədə müştəri ID-si yoxdur), adı author_name-də saxlanır.
 * Standart — yalnız yoxlama; --apply ilə yazılır. Təkrar işlədəndə artıq köçürülən rəy (legacy_key) ötürülür.
 */
class ImportLegacyReviews extends Command
{
    protected $signature = 'parfumshop:import-reviews
        {--apply : Rəyləri bazaya yaz (yoxsa yalnız yoxlama)}
        {--product=* : Yalnız bu köhnə məhsul ID-ləri}
        {--limit= : Ən çox neçə məhsul yoxlansın}
        {--sleep=150 : Köhnə sayta sorğular arası fasilə (ms)}';

    protected $description = 'Köhnə ParfumShop saytının təsdiqlənmiş rəylərini yeni saytdakı məhsullara köçürür';

    public function handle(): int
    {
        $base = (string) config('services.legacy_site.url');
        $apply = (bool) $this->option('apply');
        $sleep = max(0, (int) $this->option('sleep'));

        $products = DB::table('products')->whereNotNull('old_id')
            ->when($this->option('product'), fn ($q, $ids) => $q->whereIn('old_id', array_map('intval', $ids)))
            ->orderBy('old_id')->get(['id', 'old_id', 'name'])->unique('old_id');
        if ($this->option('limit') !== null) {
            $products = $products->take(max(1, (int) $this->option('limit')));
        }
        $existing = DB::table('product_reviews')->whereNotNull('legacy_key')->pluck('legacy_key')->flip();

        $this->info(($apply ? 'Köçürmə' : 'Yoxlama (bazaya yazılmır)').': '.$products->count().' məhsul');
        $bar = $this->output->createProgressBar($products->count());
        $counts = ['products_with_reviews' => 0, 'found' => 0, 'created' => 0, 'already' => 0, 'failed_products' => 0];
        $samples = [];

        foreach ($products as $product) {
            try {
                $reviews = $this->fetch($base, (int) $product->old_id, $sleep);
            } catch (Throwable $e) {
                $counts['failed_products']++;
                $this->newLine();
                $this->warn('Köhnə məhsul '.$product->old_id.': '.$e->getMessage());
                $bar->advance();
                continue;
            }
            if ($reviews) {
                $counts['products_with_reviews']++;
            }
            foreach ($reviews as $review) {
                $counts['found']++;
                $key = sha1($product->old_id.'|'.$review['author'].'|'.$review['date']->toDateString().'|'.$review['text']);
                if (isset($existing[$key])) {
                    $counts['already']++;
                    continue;
                }
                $existing[$key] = true;
                if (count($samples) < 5) {
                    $samples[] = [$product->old_id, $product->name, $review['author'], $review['rating'], $review['date']->format('d.m.Y'), mb_strimwidth($review['text'], 0, 60, '…')];
                }
                if ($apply) {
                    DB::table('product_reviews')->insert([
                        'product_id' => $product->id, 'customer_id' => null, 'author_name' => $review['author'],
                        'rating' => $review['rating'], 'comment' => $review['text'], 'active' => 1, 'legacy_key' => $key,
                        'created_at' => $review['date'], 'updated_at' => $review['date'],
                    ]);
                    $counts['created']++;
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        if ($samples) {
            $this->table(['Köhnə ID', 'Məhsul', 'Ad', 'Ulduz', 'Tarix', 'Mətn'], $samples);
        }
        $this->table(['', 'Say'], [
            ['Rəyi olan məhsul', $counts['products_with_reviews']],
            ['Tapılan rəy', $counts['found']],
            ['Artıq köçürülüb', $counts['already']],
            [$apply ? 'Yazıldı' : 'Yazılacaq', $apply ? $counts['created'] : $counts['found'] - $counts['already']],
            ['Oxunmayan məhsul (xəta)', $counts['failed_products']],
        ]);
        if (!$apply) {
            $this->comment('Yazmaq üçün: php artisan parfumshop:import-reviews --apply');
        }

        return $counts['failed_products'] ? self::FAILURE : self::SUCCESS;
    }

    /** Məhsulun bütün səhifələrindəki rəylər */
    private function fetch(string $base, int $oldId, int $sleep): array
    {
        $all = [];
        for ($page = 1; $page <= 50; $page++) {
            usleep($sleep * 1000);
            $html = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 ParfumShop Migration'])->timeout(30)->retry(3, 500)
                ->get($base.'/index.php', ['route' => 'product/product/review', 'product_id' => $oldId, 'page' => $page])->throw()->body();
            $reviews = self::parse($html);
            $all = array_merge($all, $reviews);
            preg_match('/səhifə sayı (\d+)/u', $html, $pages);
            if (!$reviews || $page >= (int) ($pages[1] ?? 1)) {
                break;
            }
        }

        return $all;
    }

    /** @return list<array{author: string, rating: int, date: Carbon, text: string}> */
    public static function parse(string $html): array
    {
        preg_match_all('#<b>(.*?)</b>\s*\|\s*<img[^>]*stars_(\d)\.png[^>]*>\s*<br\s*/?>\s*(\d{2}\.\d{2}\.\d{4})\s*<br\s*/?>\s*<br\s*/?>(.*?)</div>#su', $html, $m, PREG_SET_ORDER);

        return array_values(array_filter(array_map(function ($row) {
            $clean = fn ($v) => trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $v)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $text = preg_replace("/[ \t]*\n[ \t]*/u", "\n", $clean($row[4]));
            $rating = (int) $row[2];

            return [
                'author' => mb_substr($clean($row[1]), 0, 100) ?: 'Müştəri',
                'rating' => min(5, max(1, $rating)),
                'date' => Carbon::createFromFormat('d.m.Y', $row[3])->setTime(12, 0),
                'text' => $text,
            ];
        }, $m), fn ($r) => $r['text'] !== ''));
    }
}
