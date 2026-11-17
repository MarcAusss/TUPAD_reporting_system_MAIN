<?php

namespace App\Support;

use App\Enums\ImplementationMode;
use App\Models\Project;
use Illuminate\Support\Str;

/**
 * Builds breadcrumb trails for <x-breadcrumbs> / <x-page-header :breadcrumbs>.
 * Each item: ['label' => string, 'url' => ?string].
 */
class Breadcrumbs
{
    /**
     * Projects › Province › Project Title [› Current page]
     */
    public static function forProject(Project $project, ?string $current = null): array
    {
        $user = auth()->user();

        // Focal works Direct Administration projects from the Payment Queue.
        $root = $user?->isFocal() && $project->implementation_mode !== ImplementationMode::THROUGH_ACP
            ? ['label' => 'Payment of Wages', 'url' => route('payments.index')]
            : ['label' => 'Projects', 'url' => route('projects.index')];

        $location = $project->relationLoaded('projectLocations') ? $project->projectLocations->first() : null;
        $provinceId = $location?->province_id ?? $project->province_id;
        $provinceName = $location?->province?->name ?? $project->province;

        $items = [$root];

        if ($provinceName) {
            $items[] = [
                'label' => $provinceName,
                'url' => $provinceId ? route('projects.index', ['province_id' => $provinceId]) : null,
            ];
        }

        $items[] = [
            'label' => Str::limit($project->project_title, 60),
            'url' => $current !== null ? route('projects.show', $project) : null,
        ];

        if ($current !== null) {
            $items[] = ['label' => $current, 'url' => null];
        }

        return $items;
    }
}
