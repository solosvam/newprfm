<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\PerfumeAdvisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** AI ətir məsləhətçisi: /advisor — suallar, POST — AI seçimi (JSON: intro + kartların HTML-i) */
class AdvisorController extends Controller
{
    public function show(): View
    {
        return view('frontend.advisor', ['options' => PerfumeAdvisorService::OPTIONS]);
    }

    public function recommend(Request $request, PerfumeAdvisorService $advisor): JsonResponse
    {
        $o = PerfumeAdvisorService::OPTIONS;
        $answers = $request->validate([
            'for' => ['required', Rule::in($o['for'])],
            'occasion' => ['required', Rule::in($o['occasion'])],
            'season' => ['required', Rule::in($o['season'])],
            'families' => ['nullable', 'array', 'max:3'],
            'families.*' => [Rule::in($o['families'])],
            'strength' => ['required', Rule::in($o['strength'])],
            'budget' => ['required', Rule::in($o['budget'])],
            'liked' => ['nullable', 'string', 'max:120'],
        ]);
        $answers['families'] = array_values($answers['families'] ?? []);
        $answers['liked'] = trim(strip_tags((string) ($answers['liked'] ?? '')));

        try {
            $result = $advisor->recommend($answers, app()->getLocale());
        } catch (Throwable $e) {
            Log::warning('AI advisor failed: '.$e->getMessage());

            return response()->json(['message' => __('advisor_error')], 503);
        }

        if ($result['items']->isEmpty()) {
            return response()->json(['intro' => '', 'html' => '', 'empty' => __('advisor_empty')]);
        }

        return response()->json([
            'intro' => $result['intro'],
            'html' => view('frontend.partials.advisor-results', ['items' => $result['items']])->render(),
        ]);
    }
}
