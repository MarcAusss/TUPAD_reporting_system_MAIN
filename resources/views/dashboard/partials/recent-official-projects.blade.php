{{-- Recent Official Projects (Dashboard for TC/Admin, Notifications for every role). Expects $recentProjects. --}}
@php($recentViewer = auth()->user())
        <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">

            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">

                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Recent Official Projects
                    </h2>

                    <p class="mt-1 text-xs text-slate-500">
                        Most recently updated projects across the official workflow.
                    </p>
                </div>

                @if ($recentViewer->isTc() || $recentViewer->isAdmin())
                    <a href="{{ route('projects.index') }}"
                        class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        View All Projects
                    </a>
                @endif

            </div>

            <div class="overflow-x-auto">

                <table class="tupad-system-table min-w-full">

                    <thead class="bg-slate-50">

                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Project
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Location
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Beneficiaries
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Cost
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">
                                Status
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">
                                Action
                            </th>
                        </tr>

                    </thead>

                    <tbody class="divide-y divide-slate-100">

                        @forelse($recentProjects as $project)
                            <tr class="hover:bg-slate-50">

                                <td class="px-5 py-4">

                                    <div class="max-w-[320px] truncate text-sm font-semibold text-slate-900">
                                        {{ $project->project_title }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-400">
                                        {{ $project->approval?->project_code ?? 'Project code pending' }}
                                    </div>

                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ collect([$project->municipality, $project->province])->filter()->implode(', ') }}
                                </td>

                                <td class="px-5 py-4 text-right text-sm font-semibold text-slate-700">
                                    {{ number_format($project->beneficiaries_total) }}
                                </td>

                                <td class="px-5 py-4 text-right text-sm font-semibold text-slate-700">
                                    ₱{{ number_format($project->total_project_cost, 2) }}
                                </td>

                                <td class="px-5 py-4">
                                    <x-project-status :project="$project" />
                                </td>

                                <td class="px-5 py-4 text-right">

                                    @if ($recentViewer->isTc() || $recentViewer->isAdmin())
                                        <a href="{{ route('projects.show', $project) }}"
                                            class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                            Open Project
                                        </a>
                                    @else
                                        <span class="text-xs font-medium text-slate-400">
                                            View through monitoring
                                        </span>
                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="6" class="p-0">
                                    <x-empty-state title="No official projects yet"
                                        message="Project activity will appear here when official projects are created." />
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

        </section>
