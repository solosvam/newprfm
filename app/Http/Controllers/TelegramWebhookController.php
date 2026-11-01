<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramApi;
use App\Services\Telegram\TelegramIdBot;
use App\Services\Telegram\TelegramWarehouseBot;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Telegram webhook: anbarın düymə basması və qiymət / say cavabı (TelegramWarehouseBot); şəxsi çatda @idver (TelegramIdBot).
 * Sorğunun Telegram-dan gəldiyi gizli başlıqla yoxlanır (telegram:webhook əmri qeyd edir).
 * Həmişə 200 qaytarılır — xəta olsa da Telegram eyni yeniləməni təkrar-təkrar göndərməsin.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramApi $api, TelegramWarehouseBot $bot, TelegramIdBot $idBot): Response
    {
        abort_unless(hash_equals($api->webhookSecret(), (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        try {
            $update = $request->json()->all();
            if (!$idBot->handle($update)) {
                $bot->handle($update);
            }
        } catch (Throwable $e) {
            Log::warning('Telegram webhook xətası', ['error' => $e->getMessage()]);
        }

        return response('ok');
    }
}
