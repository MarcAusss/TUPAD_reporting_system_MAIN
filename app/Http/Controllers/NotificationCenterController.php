<?php

namespace App\Http\Controllers;

use App\Services\Notifications\NotificationCenterService;
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
}
