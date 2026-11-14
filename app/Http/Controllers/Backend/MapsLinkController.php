<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Support\GoogleMapsLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ünvan pəncərəsi → "Lokasiya linki": müştərinin WhatsApp-da göndərdiyi lokasiyanı koordinata çevirir (GoogleMapsLink) */
class MapsLinkController extends Controller
{
    public function resolve(Request $request, GoogleMapsLink $links): JsonResponse
    {
        $data = $request->validate(['link' => ['required', 'string', 'max:2000']], ['link.required' => 'Linki yapışdırın.']);
        $result = $links->resolve($data['link']);

        return $result
            ? response()->json($result)
            : response()->json(['message' => 'Linkdən yer tapılmadı. Google Maps linki və ya koordinat yapışdırın, ya da nöqtəni xəritədə özünüz seçin.'], 422);
    }
}
