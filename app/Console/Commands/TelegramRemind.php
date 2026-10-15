<?php

namespace App\Console\Commands;

use App\Models\Procurement\WarehouseRequestItem;
use App\Models\TelegramMessage;
use App\Services\OrderStatusService;
use App\Services\Telegram\TelegramWarehouseBot;
use Illuminate\Console\Command;

/**
 * Telegram-da cavabsız qalan anbar sorğularına xatırlatma (hər 5 dəqiqədən bir işləyir).
 * Sorğudan --after dəqiqə sonra birinci, ondan --every dəqiqə sonra ikinci — ən çox --max dəfə.
 * Yalnız iş saatlarında (Bakı vaxtı) və sifariş hələ anbar seçimi mərhələsindədirsə.
 */
class TelegramRemind extends Command
{
    protected $signature = 'telegram:remind {--after=15} {--every=30} {--max=2} {--from=9} {--to=21}';

    protected $description = 'Telegram-da cavabsız qalan anbar sorğularına xatırlatma göndərir';

    public function handle(TelegramWarehouseBot $bot): int
    {
        $hour = (int) now('Asia/Baku')->format('G');
        if ($hour < (int) $this->option('from') || $hour >= (int) $this->option('to')) {
            return self::SUCCESS;
        }

        $sent = 0;
        $messages = TelegramMessage::where('kind', TelegramMessage::REQUEST_ITEM)->whereNull('state')
            ->where('message_id', '>', 0)
            ->where('reminders', '<', max(1, (int) $this->option('max')))
            ->where('created_at', '<=', now()->subMinutes(max(1, (int) $this->option('after'))))
            ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<=', now()->subMinutes(max(1, (int) $this->option('every')))))
            ->where('created_at', '>=', now()->subDay()) // köhnə sorğulara xatırlatma getmir
            ->get();

        foreach ($messages as $message) {
            $requestItem = WarehouseRequestItem::with(['offers', 'request.order.status', 'orderItem.product.brand', 'orderItem.variant.size'])->find($message->subject_id);
            $order = $requestItem?->request?->order;
            // Cavab artıq var (Telegram-dan, linkdən və ya operator yazıb) və ya sifariş bu mərhələdən çıxıb — xatırlatma lazım deyil
            if (!$requestItem || $requestItem->offers->isNotEmpty() || !$order
                || !in_array($order->status?->code, OrderStatusService::PROCUREMENT, true)
                || $requestItem->orderItem?->activeQuantity() <= 0) {
                continue;
            }
            $sent += $bot->remind($message, $bot->shortLabel($requestItem->orderItem)) ? 1 : 0;
        }

        $this->info('Xatırlatma göndərildi: '.$sent);

        return self::SUCCESS;
    }
}
