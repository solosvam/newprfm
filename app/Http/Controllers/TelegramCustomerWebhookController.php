<?php

namespace App\Http\Controllers;

use App\Services\Messenger\CustomerBot;
use App\Services\Messenger\TelegramCustomerChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Müştəri botunun (@parfumshopai_bot) Telegram webhook-u → CustomerBot. İşçi botunun webhook-u ayrıdır (TelegramWebhookController).
 * Gizli başlıq müştəri botunun tokenindən törənir (telegram:webhook --customer qeyd edir). Həmişə 200 — Telegram təkrar göndərməsin.
 */
class TelegramCustomerWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramCustomerChannel $channel, CustomerBot $bot): Response
    {
        abort_unless($channel->api()->configured(), 404);
        abort_unless(hash_equals($channel->api()->webhookSecret(), (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        try {
            $message = $channel->parse($request->json()->all());
            if ($message) {
                $bot->handle($message, $channel);
            }
        } catch (Throwable $e) {
            Log::warning('Müştəri botu: webhook xətası', ['error' => $e->getMessage()]);
        }

        return response('ok');
    }
}
