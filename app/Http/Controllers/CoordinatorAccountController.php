<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCoordinatorPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CoordinatorAccountController extends Controller
{
    public function show(Request $request): View
    {
        $coordinator = $request->user()->load('assignedProvince:id,name');

        return view('account.show', [
            'coordinator' => $coordinator,
        ]);
    }

    public function updatePassword(UpdateCoordinatorPasswordRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $coordinator = $request->user();

        $coordinator->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('account.show')
            ->with('success', 'Password changed successfully.');
    }
}
