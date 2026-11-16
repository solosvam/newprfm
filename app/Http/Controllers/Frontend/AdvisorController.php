<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\PerfumeAdvisorService;
use App\Services\Telegram\OrderTelegram;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * AI ətir məsləhətçisi: /advisor — chat (frontend/js/advisor.js, söhbət brauzerin localStorage-indədir). Yalnız daxil olmuş müştəriyə.
 * POST /advisor — söhbətin növbəti addımı: AI-nin sualı + hazır cavablar; söhbət bitəndə — seçilən ətirlərin kartları.
 * Təhqir (AI aşkarlayır), müştəri üzrə sayılır (30 gün): 1-ci — "Mən yalnız ətir seçməkdə kömək edirəm!";
 * 2-ci — "Sizin üçün söhbət dayandırıldı!"; "Yenidən başla"dan sonra yenə — həm də sifariş qrupuna Telegram bildirişi.
 */
class AdvisorController extends Controller
{
    public function show(): View
    {
        return view('frontend.advisor');
    }

    public function recommend(Request $request, PerfumeAdvisorService $advisor): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['required', 'array', 'min:1', 'max:30'],
            'messages.*.role' => ['required', 'in:assistant,user'],
            'messages.*.text' => ['required', 'string', 'max:400'],
        ]);
        $messages = array_map(fn ($m) => ['role' => $m['role'], 'text' => trim(strip_tags($m['text']))], $data['messages']);
        if (!collect($messages)->contains('role', 'user')) {
            return response()->json(['message' => __('advisor_error')], 422);
        }

        try {
            $turn = $advisor->chat($messages, app()->getLocale());
            if ($turn['abusive']) {
                return response()->json($this->abuse(collect($messages)->where('role', 'user')->last()['text'], $turn['quick_replies']));
            }
            if (!$turn['done']) {
                return response()->json(['reply' => $turn['reply'], 'quick_replies' => $turn['quick_replies'], 'done' => false]);
            }
            $result = $advisor->recommend($turn['profile'], app()->getLocale());
        } catch (Throwable $e) {
            Log::warning('AI advisor failed: '.$e->getMessage());

            return response()->json(['message' => __('advisor_error')], 503);
        }

        return response()->json([
            'reply' => $turn['reply'],
            'quick_replies' => [],
            'done' => true,
            'intro' => $result['intro'],
            'html' => $result['items']->isEmpty() ? '' : view('frontend.partials.advisor-results', ['items' => $result['items']])->render(),
            'empty' => $result['items']->isEmpty() ? __('advisor_empty') : null,
        ]);
    }

    public const ABUSE_TTL_DAYS = 30;

    private function abuse(string $message, array $replies): array
    {
        $customer = auth()->user();
        $key = 'advisor:abuse:'.$customer->getAuthIdentifier();
        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addDays(self::ABUSE_TTL_DAYS));

        if ($count === 1) {
            return ['reply' => __('advisor_abuse_warning'), 'quick_replies' => $replies, 'done' => false];
        }
        if ($count >= 3) {
            rescue(fn () => app(OrderTelegram::class)->advisorAbuse($customer, $message, $count), null, false);
        }

        return ['reply' => __('advisor_abuse_stopped'), 'quick_replies' => [], 'done' => false, 'stopped' => true];
    }
}
