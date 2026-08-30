<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCoordinatorPasswordRequest;
use App\Models\User;
use App\Services\Auth\CoordinatorProvinceAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CoordinatorAccountController extends Controller
{
    public function show(
        Request $request,
        CoordinatorProvinceAssignmentService $assignments,
    ): View {
        /** @var User $coordinator */
        $coordinator = $request->user();

        $assignedProvince = $assignments->resolve(
            $coordinator,
            repair: true,
        );

        if ($assignedProvince !== null) {
            $coordinator->setRelation('assignedProvince', $assignedProvince);
        } else {
            $coordinator->unsetRelation('assignedProvince');
            $coordinator->load('assignedProvince:id,name,code,is_active');
        }

        return view('account.show', [
            'coordinator' => $coordinator,
            'mappingAccessReady' => $assignedProvince !== null,
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
