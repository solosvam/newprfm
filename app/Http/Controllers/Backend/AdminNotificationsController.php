<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Zəng işarəsi (public/backend/js/admin-notifications.js hər 15 saniyədən bir soruşur):
 * son bildirişlər, oxunmamış sayı; açılanda hamısı oxunmuş sayılır (əməkdaş üzrə son görülən id).
 */
class AdminNotificationsController extends Controller
{
    private const LIMIT = 15;

    public function feed(): JsonResponse
    {
        $seen = (int) auth('admin')->user()->notifications_seen_id;
        $items = DB::table('admin_notifications')->orderByDesc('id')->limit(self::LIMIT)->get()
            ->map(fn ($n) => [
                'id' => $n->id, 'type' => $n->type, 'title' => $n->title, 'body' => $n->body,
                'url' => $n->url, 'time' => \Illuminate\Support\Carbon::parse($n->created_at)->format('d.m H:i'),
                'unread' => $n->id > $seen,
            ]);

        return response()->json([
            'items' => $items,
            'unread' => DB::table('admin_notifications')->where('id', '>', $seen)->count(),
        ])->header('Cache-Control', 'no-store');
    }

    public function read(Request $request): JsonResponse
    {
        $last = (int) DB::table('admin_notifications')->max('id');
        $user = auth('admin')->user();
        if ($last > (int) $user->notifications_seen_id) {
            DB::table('users')->where('id', $user->id)->update(['notifications_seen_id' => $last]);
            $user->setAttribute('notifications_seen_id', $last);
        }

        return response()->json(['unread' => 0]);
    }
}
