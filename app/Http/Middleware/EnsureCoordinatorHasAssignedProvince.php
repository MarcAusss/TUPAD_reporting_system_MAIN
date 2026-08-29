<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoordinatorHasAssignedProvince
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->requiresProvinceAssignment() && ! $user->hasAssignedProvince()) {
            abort(
                403,
                'This TUPAD Coordinator account has no assigned province. Contact the TUPAD Focal to assign a province before accessing province-scoped functions.'
            );
        }

        return $next($request);
    }
}
