<?php

namespace App\Http\Controllers;

use App\Services\Notifications\NotificationCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(Request $request, NotificationCenterService $notifications): View
    {
        return view('notifications.index', [
            'notificationData' => $notifications->build($request->user()),
        ]);
    }

    /**
     * Lightweight role-scoped feed used by the global in-app notification poller.
     * The feed is derived from authoritative workflow state, so actions performed
     * by another account become visible without reloading the current page.
     */
    public function feed(Request $request, NotificationCenterService $notifications): JsonResponse
    {
        $data = $notifications->build($request->user());

        return response()->json([
            'total_count' => (int) $data['total_count'],
            'critical_count' => (int) $data['critical_count'],
            'attention_count' => (int) $data['attention_count'],
            'items' => $data['items']->values()->all(),
            'generated_at' => now()->toIso8601String(),
            'poll_after_ms' => 10000,
        ])->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
