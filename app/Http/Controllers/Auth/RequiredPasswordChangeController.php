<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAccountPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RequiredPasswordChangeController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()?->must_change_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.required-password-change', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateAccountPasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password changed successfully. Your account is ready to use.');
    }
}
