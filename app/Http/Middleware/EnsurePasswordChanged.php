<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Deactivated/retired accounts must lose application access even when
        // an authenticated session existed before the account was disabled.
        if ($user && (! $user->is_active || ! in_array($user->role, UserRole::assignable(), true))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors(['username' => 'This account is currently inactive.']);
        }

        if ($user?->must_change_password) {
            return redirect()->route('password.change.required');
        }

        return $next($request);
    }
}
