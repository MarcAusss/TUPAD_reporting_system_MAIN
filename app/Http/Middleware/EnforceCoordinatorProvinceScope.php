<?php

namespace App\Http\Middleware;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Project;
use App\Models\ProjectDraft;
use App\Models\Province;
use App\Services\Auth\ProvinceAccessService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnforceCoordinatorProvinceScope
{
    public function __construct(
        private readonly ProvinceAccessService $provinceAccess,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isTc()) {
            return $next($request);
        }

        // Coordinators must still be able to manage their own password and sign out
        // even while a Focal user is correcting a missing province assignment.
        if ($request->routeIs('account.*', 'logout')) {
            return $next($request);
        }

        if (! $user->hasAssignedProvince()) {
            abort(
                403,
                'This TUPAD Coordinator account has no assigned province. Contact the TUPAD Focal before accessing project data.'
            );
        }

        $assignedProvinceId = (int) $user->assigned_province_id;

        $this->assertSubmittedProvince($request, $assignedProvinceId);
        $this->assertRouteResources($request, $assignedProvinceId);

        // Reporting and executive views are always forced to the Coordinator's
        // assigned province. A foreign province_id is rejected above rather than
        // silently replaced, so URL/filter tampering is visible and fails closed.
        if ($request->routeIs('reports.*', 'executive-dashboard.*')) {
            $request->query->set('province_id', $assignedProvinceId);
        }

        return $next($request);
    }

    private function assertSubmittedProvince(Request $request, int $assignedProvinceId): void
    {
        if ($request->filled('province_id')) {
            abort_unless(
                (int) $request->input('province_id') === $assignedProvinceId,
                403,
                'TUPAD Coordinators may only access or submit data for their assigned province.'
            );
        }
    }

    private function assertRouteResources(Request $request, int $assignedProvinceId): void
    {
        $route = $request->route();

        if (! $route) {
            return;
        }

        foreach ($route->parameters() as $name => $value) {
            if ($name === 'project') {
                $project = $value instanceof Project
                    ? $value
                    : Project::query()->find($value);

                abort_unless(
                    $project && $this->provinceAccess->canAccessProject($request->user(), $project),
                    403,
                    'This project belongs to another province.'
                );
            }

            if ($name === 'projectDraft') {
                $draft = $value instanceof ProjectDraft
                    ? $value
                    : ProjectDraft::query()->find($value);

                abort_unless(
                    $draft && $this->provinceAccess->canAccessProjectDraft($request->user(), $draft),
                    403,
                    'This project draft belongs to another province.'
                );
            }

            if ($name === 'province') {
                $this->assertProvinceParameter($value, $assignedProvinceId, $request);
            }

            if ($name === 'municipality') {
                $municipality = $value instanceof Municipality
                    ? $value
                    : Municipality::query()->find($value);

                abort_unless(
                    $municipality && (int) $municipality->province_id === $assignedProvinceId,
                    403,
                    'This municipality belongs to another province.'
                );
            }

            if ($name === 'barangay') {
                $barangay = $value instanceof Barangay
                    ? $value
                    : Barangay::query()->with('municipality:id,province_id')->find($value);

                abort_unless(
                    $barangay
                        && $barangay->municipality
                        && (int) $barangay->municipality->province_id === $assignedProvinceId,
                    403,
                    'This barangay belongs to another province.'
                );
            }
        }
    }

    private function assertProvinceParameter(
        mixed $value,
        int $assignedProvinceId,
        Request $request,
    ): void {
        if ($value instanceof Province) {
            abort_unless(
                (int) $value->id === $assignedProvinceId,
                403,
                'This province is outside the Coordinator assignment.'
            );

            return;
        }

        if (is_numeric($value)) {
            abort_unless(
                (int) $value === $assignedProvinceId,
                403,
                'This province is outside the Coordinator assignment.'
            );

            return;
        }

        $assignedProvince = $request->user()->assignedProvince()->first();
        $requestedSlug = Str::slug((string) $value);
        $assignedSlug = Str::slug((string) $assignedProvince?->name);

        abort_unless(
            $assignedSlug !== '' && $requestedSlug === $assignedSlug,
            403,
            'This province is outside the Coordinator assignment.'
        );
    }
}
