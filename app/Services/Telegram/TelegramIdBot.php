<?php

namespace App\Services\Telegram;

/**
 * Botun şəxsi çatında "@idver" (və ya /idver): yazanın Telegram ID-si və adı təyin olunmuş çata
 * (TELEGRAM_ID_CHAT — məs. adminin özü və ya qrup) göndərilir — əməkdaşın "Telegram ID" xanasını doldurmaq üçün.
 * Yazana ID-si və "📱 Nömrəmi göndər" düyməsi qayıdır: nömrəni paylaşsa, o da həmin çata gedir
 * (bot nömrəni yalnız adam özü paylaşanda görür). Təyin olunmuş çatın sahibi özü yazıbsa — yalnız ID-si qayıdır.
 */
class TelegramIdBot
{
    public function __construct(private TelegramApi $api)
    {
    }

    /** Yeniləmə bu funksiyaya aiddirsə işləyir və true qaytarır */
    public function handle(array $update): bool
    {
        $message = $update['message'] ?? null;
        if (!$message || ($message['chat']['type'] ?? '') !== 'private') {
            return false;
        }
        $from = $message['from'] ?? [];
        if (isset($message['contact'])) {
            // Yalnız öz nömrəsi (başqasının kontaktını göndərmək olmaz)
            if ((string) ($message['contact']['user_id'] ?? '') !== (string) ($from['id'] ?? '-')) {
                return false;
            }
            $sent = $this->report($from, $message['contact']['phone_number'] ?? null);
            $this->api->call('sendMessage', ['chat_id' => $from['id'], 'text' => $sent ? 'Təşəkkürlər, nömrəniz göndərildi.' : 'Nömrə qəbul olundu.',
                'reply_markup' => ['remove_keyboard' => true]]);

            return true;
        }
        if (!preg_match('~^[@/]idver(@\w+)?$~i', trim((string) ($message['text'] ?? '')))) {
            return false;
        }
        $sent = $this->report($from, null);
        $this->api->call('sendMessage', [
            'chat_id' => $from['id'], 'parse_mode' => 'HTML',
            'text' => 'Sizin Telegram ID: <code>'.e((string) ($from['id'] ?? '')).'</code>'
                .($sent ? "\nID-niz göndərildi. Nömrənizi də göndərmək üçün aşağıdakı düyməyə basın." : ''),
            'reply_markup' => $sent
                ? ['keyboard' => [[['text' => '📱 Nömrəmi göndər', 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true]
                : ['remove_keyboard' => true],
        ]);

        return true;
    }

    /** Təyin olunmuş çata: ad, @username, ID, nömrə (varsa). Göndərilmədisə — false */
    private function report(array $from, ?string $phone): bool
    {
        $target = trim((string) config('services.telegram.id_chat'));
        if ($target === '' || $target === (string) ($from['id'] ?? '')) {
            return false; // çat yoxdur və ya yazan çatın sahibidir — ID-ni onsuz da cavabda görür
        }
        $name = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? ''));
        $phone = $phone ? '+'.ltrim($phone, '+') : null;
        $this->api->call('sendMessage', ['chat_id' => $target, 'parse_mode' => 'HTML', 'text' => ($phone ? '📱 <b>Nömrə paylaşıldı</b>' : '🆔 <b>ID sorğusu (@idver)</b>')
            ."\n\nAd: ".e($name ?: '—')
            .(isset($from['username']) ? "\nUsername: @".e($from['username']) : '')
            ."\nTelegram ID: <code>".e((string) ($from['id'] ?? ''))."</code>"
            .($phone ? "\nNömrə: ".e($phone) : '')]);

        return true;
    }
}
