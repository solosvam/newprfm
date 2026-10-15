<?php

namespace App\Services\Telegram;

use App\Models\Order\OrderItem;
use App\Models\Procurement\OrderItemAllocation;
use App\Models\Procurement\Warehouse;
use App\Models\Procurement\WarehouseRequest;
use App\Models\Procurement\WarehouseRequestItem;
use App\Models\SmsLog;
use App\Models\TelegramMessage;
use App\Services\ProcurementService;
use App\Services\WarehouseNotifier;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sorğu tipi Telegram olan anbarlar: bildiriş anbarın Telegram qrupuna (anbar + bot + Parfumshop) gedir.
 *  - sorğu: hər məhsul üçün ayrıca mesaj (şəkil + ad, ölçü, növ, cins) və düymələr — "Var" / "Yoxdur"
 *    (çox ədəd istənilibsə "Hamısı var", "Qismən", "Yoxdur"). "Var" → bot qiyməti soruşur (cavab mesajı ilə),
 *    "Qismən" → əvvəl sayı. Cavab sorğuya anbarın cavabı kimi yazılır (mənbə "telegram", user_id 0 — anbarın özü);
 *  - seçim: "sizdən seçildi, rezerv edin" + "Rezerv etdim" düyməsi (→ "Anbar ayırdı");
 *  - ləğv: məlumat mesajı.
 * Göndərilən hər mesaj jurnala yazılır (sms_logs, channel = telegram) — SMS ilə eyni yerdə görünür.
 * Cavablar yalnız anbarın öz qrupundan (Warehouse::telegramChatId) qəbul olunur.
 */
class TelegramWarehouseBot
{
    public function __construct(private TelegramApi $api)
    {
    }

    /** Sorğu: hər məhsula bir mesaj. Hamısı getdisə — uğurlu jurnal yazısı, biri getmədisə — xətalı. */
    public function sendRequest(WarehouseRequest $request, int $actor): SmsLog
    {
        $request->loadMissing(['warehouse', 'items.orderItem.product.brand', 'items.orderItem.product.type', 'items.orderItem.product.genders', 'items.orderItem.product.images', 'items.orderItem.variant.size']);
        $result = null;
        foreach ($request->items as $requestItem) {
            $label = $this->label($requestItem->orderItem);
            $quantity = (int) $requestItem->requested_quantity;
            $text = 'Sizdə <b>'.e($label['title']).'</b> ətirinin <b>'.e($label['size'] ?? '—').'</b> ölçüsü <b>'.e($label['type'] ?? '—').'</b> olan <b>'.$quantity.' ədəd</b> var?'
                ."\n\n".$this->details($label)."\nSorğu #".$request->id;
            $log = $this->post($request->warehouse, $text, $label['photo'], TelegramMessage::REQUEST_ITEM, $requestItem->id,
                fn (TelegramMessage $m) => $this->answerButtons($m, $quantity),
                'warehouse_request', WarehouseNotifier::SUBJECT_REQUEST, $request->id, $actor);
            $result = $result && !$result->isSent() ? $result : $log;
        }

        return $result;
    }

    public function sendSelected(OrderItemAllocation $allocation, int $actor): SmsLog
    {
        $allocation->loadMissing(['warehouse', 'orderItem.product.brand', 'orderItem.product.type', 'orderItem.product.genders', 'orderItem.product.images', 'orderItem.variant.size']);
        $label = $this->label($allocation->orderItem);
        $text = '✅ <b>'.e($label['full']).'</b> — '.$allocation->quantity.' ədəd sizdən seçildi. Zəhmət olmasa rezerv edin və təsdiqləyin.'
            ."\n\n".$this->details($label)."\nAlış: ".number_format((float) $allocation->unit_cost, 2).' AZN';

        return $this->post($allocation->warehouse, $text, $label['photo'], TelegramMessage::ALLOCATION, $allocation->id,
            fn (TelegramMessage $m) => [[['text' => '✅ Rezerv etdim', 'callback_data' => $m->id.':reserve']]],
            'warehouse_selected', WarehouseNotifier::SUBJECT_ALLOCATION, $allocation->id, $actor);
    }

    public function sendCancelled(OrderItemAllocation $allocation, int $actor): SmsLog
    {
        $allocation->loadMissing(['warehouse', 'orderItem.product.brand', 'orderItem.product.type', 'orderItem.product.genders', 'orderItem.variant.size']);
        $label = $this->label($allocation->orderItem);
        $text = '❌ <b>'.e($label['full']).'</b> — '.$allocation->quantity.' ədəd seçimi ləğv edildi, rezerv lazım deyil.';

        return $this->post($allocation->warehouse, $text, null, null, null, null,
            'warehouse_cancelled', WarehouseNotifier::SUBJECT_ALLOCATION, $allocation->id, $actor);
    }

    /** Webhook: düymə basılması (callback_query) və botun sualına cavab (message.reply_to_message) */
    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->onButton($update['callback_query']);
        } elseif (isset($update['message']['reply_to_message'])) {
            $this->onReply($update['message']);
        } elseif (preg_match('~^/id(@\w+)?$~', trim((string) ($update['message']['text'] ?? '')))) {
            // Qrupun id-sini öyrənmək üçün: qrupda /id yazılır — bot id-ni qaytarır (anbarın "Telegram ID" xanasına yazılır)
            $chat = $update['message']['chat'] ?? [];
            $this->api->call('sendMessage', ['chat_id' => $chat['id'] ?? null, 'parse_mode' => 'HTML',
                'text' => 'Bu çatın ID-si: <code>'.e((string) ($chat['id'] ?? '')).'</code>'.(isset($chat['title']) ? ' ('.e($chat['title']).')' : '')]);
        }
    }

    private function onButton(array $callback): void
    {
        [$id, $action] = array_pad(explode(':', (string) ($callback['data'] ?? ''), 2), 2, null);
        $message = TelegramMessage::find((int) $id);
        $chat = (string) ($callback['message']['chat']['id'] ?? '');
        if (!$message || $message->chat_id !== $chat || (int) ($callback['message']['message_id'] ?? 0) !== (int) $message->message_id) {
            $this->toast($callback, 'Bu mesaj artıq aktiv deyil.');

            return;
        }
        $who = $this->who($callback['from'] ?? []);

        try {
            if ($message->kind === TelegramMessage::ALLOCATION && $action === 'reserve') {
                $this->reserve($message, $who);
                $this->toast($callback, 'Rezerv qeyd olundu.');

                return;
            }
            if ($message->kind !== TelegramMessage::REQUEST_ITEM) {
                $this->toast($callback);

                return;
            }
            $requestItem = WarehouseRequestItem::with('request')->findOrFail($message->subject_id);
            $requested = (int) $requestItem->requested_quantity;
            match ($action) {
                'none' => $this->saveAnswer($message, $requestItem, 0, null, $who),
                'all' => $this->ask($message, TelegramMessage::AWAIT_PRICE, $requested, $requested > 1 ? '1 ədədin qiymətini yazın (AZN). Say: '.$requested : '1 ədədin qiymətini yazın (AZN)'),
                'part' => $this->ask($message, TelegramMessage::AWAIT_QUANTITY, null, 'Neçə ədəd var? 1-dən '.($requested - 1).'-dək rəqəm yazın.'),
                'change' => $this->edit($message, $message->text, $this->answerButtons($message, $requested), ['state' => null, 'quantity' => null]),
                default => null,
            };
            $this->toast($callback);
        } catch (ValidationException $e) {
            $this->toast($callback, collect($e->errors())->flatten()->first() ?: 'Cavab qəbul olunmadı.');
        }
    }

    private function onReply(array $incoming): void
    {
        $chat = (string) ($incoming['chat']['id'] ?? '');
        $prompt = TelegramMessage::where('kind', TelegramMessage::PROMPT)->where('chat_id', $chat)
            ->where('message_id', (int) ($incoming['reply_to_message']['message_id'] ?? 0))->first();
        $message = $prompt?->parent;
        if (!$message || !$message->state) {
            return;
        }
        $requestItem = WarehouseRequestItem::with('request')->find($message->subject_id);
        if (!$requestItem) {
            return;
        }
        // Anbarın cavab mesajı da yadda saxlanır — qiymət qeyd olunandan sonra söhbət təmizlənir
        TelegramMessage::create(['chat_id' => $chat, 'message_id' => (int) ($incoming['message_id'] ?? 0), 'kind' => TelegramMessage::REPLY,
            'parent_id' => $message->id, 'text' => Str::limit((string) ($incoming['text'] ?? ''), 200)]);
        $requested = (int) $requestItem->requested_quantity;
        $value = str_replace([',', ' '], ['.', ''], trim((string) ($incoming['text'] ?? '')));

        try {
            if ($message->state === TelegramMessage::AWAIT_QUANTITY) {
                if (!ctype_digit($value) || (int) $value < 1 || (int) $value >= $requested) {
                    $this->ask($message, TelegramMessage::AWAIT_QUANTITY, null, 'Say 1-dən '.($requested - 1).'-dək olmalıdır. Yenidən yazın.');

                    return;
                }
                $this->ask($message, TelegramMessage::AWAIT_PRICE, (int) $value, '1 ədədin qiymətini yazın (AZN). Say: '.(int) $value);

                return;
            }
            if (!is_numeric($value) || (float) $value <= 0 || (float) $value > 9999999) {
                $this->ask($message, TelegramMessage::AWAIT_PRICE, $message->quantity, 'Qiyməti rəqəmlə yazın, məsələn 95 və ya 95.50');

                return;
            }
            $this->saveAnswer($message, $requestItem, (int) $message->quantity, number_format((float) $value, 2, '.', ''), $this->who($incoming['from'] ?? []));
        } catch (ValidationException $e) {
            $this->api->call('sendMessage', ['chat_id' => $chat, 'reply_to_message_id' => $incoming['message_id'] ?? null,
                'text' => collect($e->errors())->flatten()->first() ?: 'Cavab qəbul olunmadı.']);
        }
    }

    /** Anbarın cavabı sorğuya yazılır (CRM-də dərhal görünür), mesaj nəticə ilə yenilənir */
    private function saveAnswer(TelegramMessage $message, WarehouseRequestItem $requestItem, int $quantity, ?string $price, string $who): void
    {
        $order = $requestItem->request->order()->firstOrFail();
        app(ProcurementService::class)->recordOffer($order, $requestItem->id, [
            'available_quantity' => $quantity, 'unit_cost' => $price, 'source' => 'telegram', 'note' => 'Telegram: '.$who,
        ], 0);
        // Cavab yalnız düymədə yazılır (mətndə təkrar olmasın); basanda cavab dəyişdirilir — düymələr yenidən açılır.
        // Kimin cavab verdiyi CRM-də görünür (WarehouseOffer::authorName)
        $button = $quantity
            ? '✅ Var — '.($quantity > 1 || (int) $requestItem->requested_quantity > 1 ? $quantity.' ədəd × ' : '').$price.' AZN'
            : '❌ Yoxdur';
        $this->edit($message, $message->text, [[['text' => $button, 'callback_data' => $message->id.':change']]], ['state' => null, 'quantity' => null]);
        $this->cleanup($message);
    }

    private function reserve(TelegramMessage $message, string $who): void
    {
        $allocation = OrderItemAllocation::with('orderItem.order')->findOrFail($message->subject_id);
        if ($allocation->status !== OrderItemAllocation::RESERVED) {
            if (!in_array($allocation->status, [OrderItemAllocation::SELECTED, OrderItemAllocation::NOTIFIED], true)) {
                throw ValidationException::withMessages(['allocation' => 'Bu seçim artıq dəyişib.']);
            }
            app(ProcurementService::class)->transition($allocation->orderItem->order, $allocation->id, OrderItemAllocation::RESERVED, ['note' => 'Anbar Telegram-da təsdiqlədi ('.$who.')'], 0);
        }
        $this->edit($message, $message->text."\n\n".'✅ <b>Rezerv olundu</b> ('.e($who).')', [], []);
    }

    /** Botun sualı: qrupda həmin mesaja cavab kimi, Telegram yazı xanasını avtomatik açır */
    private function ask(TelegramMessage $message, string $state, ?int $quantity, string $question): void
    {
        $sent = $this->api->call('sendMessage', [
            'chat_id' => $message->chat_id, 'text' => $question, 'reply_to_message_id' => $message->message_id,
            'reply_markup' => ['force_reply' => true, 'input_field_placeholder' => $state === TelegramMessage::AWAIT_PRICE ? '95.50' : '1'],
        ]);
        $message->update(['state' => $state] + ($quantity !== null ? ['quantity' => $quantity] : []));
        TelegramMessage::create(['chat_id' => $message->chat_id, 'message_id' => (int) $sent['message_id'], 'kind' => TelegramMessage::PROMPT,
            'parent_id' => $message->id, 'text' => $question]);
    }

    /**
     * Botun sualları (qiymət, say) və anbarın cavab mesajları silinir — qrupda yalnız nəticəli mesaj qalır.
     * Botun öz mesajını həmişə silə bilir; anbarın mesajını yalnız bot qrupda admindirsə ("Mesajları silmək" icazəsi) — olmasa qalır.
     */
    private function cleanup(TelegramMessage $message): void
    {
        $extra = TelegramMessage::where('parent_id', $message->id)->whereIn('kind', [TelegramMessage::PROMPT, TelegramMessage::REPLY])->get();
        foreach ($extra as $row) {
            rescue(fn () => $this->api->call('deleteMessage', ['chat_id' => $row->chat_id, 'message_id' => $row->message_id]), null, false);
        }
        TelegramMessage::whereKey($extra->pluck('id'))->delete();
    }

    /** @return list<list<array{text: string, callback_data: string}>> */
    private function answerButtons(TelegramMessage $message, int $quantity): array
    {
        return $quantity > 1
            ? [[['text' => '✅ Hamısı var ('.$quantity.')', 'callback_data' => $message->id.':all'], ['text' => 'Qismən', 'callback_data' => $message->id.':part']],
               [['text' => '❌ Yoxdur', 'callback_data' => $message->id.':none']]]
            : [[['text' => '✅ Var', 'callback_data' => $message->id.':all'], ['text' => '❌ Yoxdur', 'callback_data' => $message->id.':none']]];
    }

    private function edit(TelegramMessage $message, string $text, array $keyboard, array $state): void
    {
        $this->api->call($message->has_photo ? 'editMessageCaption' : 'editMessageText', [
            'chat_id' => $message->chat_id, 'message_id' => $message->message_id, 'parse_mode' => 'HTML',
            ($message->has_photo ? 'caption' : 'text') => $text, 'reply_markup' => ['inline_keyboard' => $keyboard],
        ]);
        if ($state) {
            $message->update($state);
        }
    }

    /**
     * Mesajı göndərir (şəkil varsa şəkillə; şəkil alınmasa — mətn), düyməli mesajın vəziyyətini və jurnal yazısını saxlayır.
     *
     * @param  (callable(TelegramMessage): array)|null  $buttons
     */
    private function post(Warehouse $warehouse, string $text, ?string $photo, ?string $kind, ?int $subjectId, ?callable $buttons,
        string $context, string $subjectType, int $logSubjectId, int $actor): SmsLog
    {
        $log = ['context' => $context, 'channel' => 'telegram', 'subject_type' => $subjectType, 'subject_id' => $logSubjectId,
            'msisdn' => $warehouse->telegramChatId(), 'message' => Str::limit(strip_tags($text), 1000), 'created_by' => $actor ?: null, 'created_at' => now()];
        $message = null;
        try {
            if ($kind) {
                // Düymələrdə öz id-si olduğu üçün qeyd əvvəl yaradılır, mesaj id-si göndərişdən sonra yazılır
                $message = TelegramMessage::create(['chat_id' => (string) $warehouse->telegramChatId(), 'message_id' => 0, 'kind' => $kind, 'subject_id' => $subjectId, 'text' => $text]);
            }
            $markup = $buttons && $message ? ['reply_markup' => ['inline_keyboard' => $buttons($message)]] : [];
            $sent = null;
            if ($photo) {
                // Şəkil yüklənməsə (fayl yoxdur, Telegram ünvana çata bilmir) — mesaj şəkilsiz gedir
                $sent = rescue(fn () => $this->api->call('sendPhoto', ['chat_id' => $warehouse->telegramChatId(), 'photo' => $photo, 'caption' => $text, 'parse_mode' => 'HTML'] + $markup), null, false);
            }
            $withPhoto = $sent !== null; // şəkilli mesajın mətni "caption"dır — redaktə ayrı üsulla gedir
            $sent ??= $this->api->call('sendMessage', ['chat_id' => $warehouse->telegramChatId(), 'text' => $text, 'parse_mode' => 'HTML'] + $markup);
            $message?->update(['message_id' => (int) $sent['message_id'], 'has_photo' => $withPhoto]);
            $log += ['status' => SmsLog::SENT, 'provider_id' => (string) $sent['message_id']];
        } catch (Throwable $e) {
            report($e);
            $message?->delete();
            $log += ['status' => SmsLog::FAILED, 'error' => Str::limit($e->getMessage(), 250)];
        }

        return SmsLog::create($log);
    }

    /** @return array{title: string, full: string, brand: ?string, name: string, size: ?string, type: ?string, gender: ?string, photo: ?string} */
    private function label(?OrderItem $item): array
    {
        $product = $item?->product;
        $brand = $product?->brand?->name ? html_entity_decode($product->brand->name) : null;
        $name = $product?->name ?? 'Silinmiş məhsul';
        $size = $item?->variant?->size?->name_az;
        $type = $product?->type?->name_az;
        $gender = $product?->genders?->pluck('name_az')->implode(', ') ?: null;
        $image = $product?->images?->first()?->image;
        // Telegram şəkli öz serverindən yükləyir — yalnız ictimai https ünvanı işləyir
        $base = rtrim((string) config('app.url'), '/');
        $photo = $image && str_starts_with($base, 'https://') ? $base.'/frontend/uploads/products/'.rawurlencode($image) : null;

        return [
            'title' => trim(($brand ? $brand.' ' : '').$name), 'full' => collect([$brand, $name, $size, $type, $gender])->filter()->implode(' '),
            'brand' => $brand, 'name' => $name, 'size' => $size, 'type' => $type, 'gender' => $gender, 'photo' => $photo,
        ];
    }

    private function details(array $label): string
    {
        return collect(['Marka' => $label['brand'], 'Model' => $label['name'], 'Ölçü' => $label['size'], 'Növ' => $label['type'], 'Cins' => $label['gender']])
            ->filter()->map(fn ($value, $key) => $key.': '.e($value))->implode("\n");
    }

    private function who(array $from): string
    {
        return trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? '')) ?: ($from['username'] ?? 'Anbar');
    }

    private function toast(array $callback, ?string $text = null): void
    {
        rescue(fn () => $this->api->call('answerCallbackQuery', ['callback_query_id' => $callback['id'] ?? ''] + ($text ? ['text' => $text] : [])), null, false);
    }
}
