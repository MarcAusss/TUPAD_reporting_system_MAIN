<?php

namespace App\Http\Middleware;

use App\Services\Auth\ProvinceAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoordinatorHasAssignedProvince
{
    public function __construct(
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->requiresProvinceAssignment()
            && $this->provinceAccess->assignedProvinceId($user) === null) {
            abort(
                403,
                'This TUPAD Coordinator account has no valid active Bicol province assignment. Contact the TUPAD Focal before accessing province-scoped functions.'
            );
        }

        return $next($request);
    }
}
