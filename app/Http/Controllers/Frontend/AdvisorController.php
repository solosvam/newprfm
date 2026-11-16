<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\PerfumeAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * AI ətir məsləhətçisi: /advisor — chat (frontend/js/advisor.js, söhbət brauzerin localStorage-indədir).
 * POST /advisor — söhbətin növbəti addımı: AI-nin sualı + hazır cavablar; söhbət bitəndə — seçilən ətirlərin kartları.
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
            'finish' => ['nullable', 'boolean'],
        ]);
        $messages = array_map(fn ($m) => ['role' => $m['role'], 'text' => trim(strip_tags($m['text']))], $data['messages']);
        if (!collect($messages)->contains('role', 'user')) {
            return response()->json(['message' => __('advisor_error')], 422);
        }

        try {
            $turn = $advisor->chat($messages, app()->getLocale(), (bool) ($data['finish'] ?? false));
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
}
