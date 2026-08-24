@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    use App\Models\Adl;
    use App\Models\AdlAllocation;
    use App\Models\Project;
    use App\Models\ProjectBeneficiary;
    use App\Enums\ProjectStatus;
    use Illuminate\Support\Carbon;

    $projects = Project::query();
    $totalProjects = (clone $projects)->count();
    $completedProjects = (clone $projects)->where('status', ProjectStatus::COMPLETED)->count();
    $activeProjects = max(0, $totalProjects - $completedProjects);
    $declaredBeneficiaries = (int) (clone $projects)->sum('beneficiaries_total');
    $registeredBeneficiaries = ProjectBeneficiary::query()->count();

    $adls = Adl::query()->get();
    $totalBudget = (float) $adls->sum(function ($adl) {
        return (float) ($adl->adjusted_total_grants ?? $adl->grants ?? 0);
    });

    $totalAllocated = (float) AdlAllocation::query()->sum('amount');
    $remainingBudget = max(0, $totalBudget - $totalAllocated);
    $utilizationPercent = $totalBudget > 0 ? min(100, ($totalAllocated / $totalBudget) * 100) : 0;
    $remainingPercent = max(0, 100 - $utilizationPercent);

    $recentProjects = Project::query()
        ->with(['approval', 'allocation.adl'])
        ->latest('updated_at')
        ->limit(5)
        ->get();

    $currentYear = (int) now()->format('Y');
    $monthlyValues = collect(range(1, 12))->map(function ($month) use ($currentYear) {
        return (float) Project::query()
            ->whereYear('date_received', $currentYear)
            ->whereMonth('date_received', $month)
            ->sum('total_project_cost');
    });

    $running = 0;
    $trendValues = $monthlyValues->map(function ($value) use (&$running) {
        $running += $value;
        return $running;
    });

    $maxTrend = max(1, (float) $trendValues->max());
    $chartWidth = 720;
    $chartHeight = 210;
    $plotTop = 16;
    $plotBottom = 184;
    $plotLeft = 26;
    $plotRight = 700;
    $stepX = ($plotRight - $plotLeft) / 11;

    $points = $trendValues->values()->map(function ($value, $index) use ($maxTrend, $plotTop, $plotBottom, $plotLeft, $stepX) {
        $x = $plotLeft + ($index * $stepX);
        $ratio = $maxTrend > 0 ? ($value / $maxTrend) : 0;
        $y = $plotBottom - (($plotBottom - $plotTop) * $ratio);
        return ['x' => round($x, 2), 'y' => round($y, 2), 'value' => $value];
    });

    $polyline = $points->map(fn ($point) => $point['x'].','.$point['y'])->implode(' ');
    $areaPoints = $plotLeft.','.$plotBottom.' '.$polyline.' '.$plotRight.','.$plotBottom;
    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];

    $statusLabel = function ($status) {
        if (is_object($status) && method_exists($status, 'label')) {
            return $status->label();
        }

        return str((string) $status)->replace('_', ' ')->title()->toString();
    };

    $statusTone = function ($status) {
        $value = is_object($status) && property_exists($status, 'value') ? $status->value : (string) $status;
        return match ($value) {
            'completed' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'ongoing_implementation' => 'border-blue-200 bg-blue-50 text-blue-700',
            'for_payment' => 'border-amber-200 bg-amber-50 text-amber-700',
            'for_approval', 'tssd_evaluation' => 'border-violet-200 bg-violet-50 text-violet-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    };
@endphp

<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-[22px] font-extrabold tracking-tight text-[#0d2449]">Dashboard</h1>
            <p class="mt-1 text-[12px] text-[#6f7f98]">Overview of TUPAD program funds, projects, beneficiaries, and workflow activity.</p>
        </div>
        <div class="inline-flex h-9 w-fit items-center rounded-lg border border-[#dfe6f0] bg-white px-3 text-[11px] font-semibold text-[#4f6381]">
            {{ now()->format('F d, Y') }}
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-4">
        <article class="tupad-card p-4.5">
            <div class="flex items-start gap-3.5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#063b86] text-white">
                    <span class="text-xl font-semibold">₱</span>
                </div>
                <div class="min-w-0">
                    <div class="text-[11px] font-semibold text-[#5c6f8d]">Total Budget</div>
                    <div class="mt-1 truncate text-[20px] font-extrabold tracking-tight text-[#10294f]">₱{{ number_format($totalBudget, 2) }}</div>
                    <div class="mt-1 text-[10px] text-[#7d8ba1]">Adjusted available grants</div>
                </div>
            </div>
        </article>

        <article class="tupad-card p-4.5">
            <div class="flex items-start gap-3.5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#0f4c9a] text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><rect x="3" y="7" width="18" height="13" rx="2"></rect><path d="M3 12h18"></path></svg>
                </div>
                <div>
                    <div class="text-[11px] font-semibold text-[#5c6f8d]">Active Projects</div>
                    <div class="mt-1 text-[20px] font-extrabold tracking-tight text-[#10294f]">{{ number_format($activeProjects) }}</div>
                    <div class="mt-1 text-[10px] text-[#7d8ba1]">{{ number_format($totalProjects) }} total official projects</div>
                </div>
            </div>
        </article>

        <article class="tupad-card p-4.5">
            <div class="flex items-start gap-3.5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#21a55b] text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"></circle><circle cx="17" cy="9" r="2.5"></circle><path d="M3 20a6 6 0 0 1 12 0"></path><path d="M14 15a5 5 0 0 1 7 5"></path></svg>
                </div>
                <div>
                    <div class="text-[11px] font-semibold text-[#5c6f8d]">Beneficiaries</div>
                    <div class="mt-1 text-[20px] font-extrabold tracking-tight text-[#10294f]">{{ number_format($declaredBeneficiaries) }}</div>
                    <div class="mt-1 text-[10px] text-[#7d8ba1]">{{ number_format($registeredBeneficiaries) }} individually registered</div>
                </div>
            </div>
        </article>

        <article class="tupad-card p-4.5">
            <div class="flex items-start gap-3.5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#1765d8] text-white">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h9l4 4v14H6z"></path><path d="M15 3v5h5"></path><path d="m9 15 2 2 4-4"></path></svg>
                </div>
                <div>
                    <div class="text-[11px] font-semibold text-[#5c6f8d]">Completed Projects</div>
                    <div class="mt-1 text-[20px] font-extrabold tracking-tight text-[#10294f]">{{ number_format($completedProjects) }}</div>
                    <div class="mt-1 text-[10px] text-[#7d8ba1]">Completed official workflow</div>
                </div>
            </div>
        </article>
    </div>

    {{-- Trend + budget --}}
    <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_310px]">
        <section class="tupad-card overflow-hidden">
            <div class="flex flex-col gap-3 border-b border-[#e5ebf3] px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-[14px] font-bold text-[#10294f]">Budget Utilization Trend</h2>
                    <p class="mt-0.5 text-[10px] text-[#7c8ba0]">Cumulative official project cost by month for FY {{ $currentYear }}.</p>
                </div>
                <div class="inline-flex h-8 items-center rounded-lg border border-[#d9e2ee] bg-white px-3 text-[10px] font-semibold text-[#48617f]">FY {{ $currentYear }}</div>
            </div>

            <div class="px-4 pb-3 pt-3 sm:px-5">
                <div class="relative overflow-hidden rounded-lg bg-white">
                    <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="h-[250px] w-full" preserveAspectRatio="none" aria-label="Budget utilization chart">
                        <defs>
                            <linearGradient id="trendFill" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#1765d8" stop-opacity="0.18"></stop>
                                <stop offset="100%" stop-color="#1765d8" stop-opacity="0.01"></stop>
                            </linearGradient>
                        </defs>

                        @foreach([16,58,100,142,184] as $gridY)
                            <line x1="26" y1="{{ $gridY }}" x2="700" y2="{{ $gridY }}" stroke="#e6edf6" stroke-width="1"></line>
                        @endforeach

                        <polygon points="{{ $areaPoints }}" fill="url(#trendFill)"></polygon>
                        <polyline points="{{ $polyline }}" fill="none" stroke="#1765d8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>

                        @foreach($points as $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#1765d8" stroke="#ffffff" stroke-width="2"></circle>
                        @endforeach
                    </svg>

                    <div class="grid grid-cols-12 px-2 text-center text-[9px] font-medium text-[#72829a]">
                        @foreach($months as $month)
                            <span>{{ $month }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <aside class="tupad-card flex min-h-[330px] flex-col p-5">
            <div class="flex items-center gap-2">
                <h2 class="text-[14px] font-bold text-[#10294f]">Available Budget</h2>
                <span class="flex h-4 w-4 items-center justify-center rounded-full border border-[#9aacbf] text-[9px] font-bold text-[#718198]">i</span>
            </div>

            <div class="mt-4 text-[25px] font-extrabold tracking-tight text-[#10294f]">₱{{ number_format($remainingBudget, 2) }}</div>
            <div class="mt-0.5 text-[11px] text-[#74839a]">Remaining balance</div>

            <div class="mt-5 h-2.5 overflow-hidden rounded-full bg-[#e8edf4]">
                <div class="h-full rounded-full bg-[#063b86]" style="width: {{ $remainingPercent }}%"></div>
            </div>

            <div class="mt-2 flex items-center justify-between text-[10px] font-semibold text-[#40597b]">
                <span>{{ number_format($remainingPercent, 1) }}% remaining</span>
                <span>{{ number_format($utilizationPercent, 1) }}% utilized</span>
            </div>

            <div class="mt-5 grid grid-cols-2 gap-3 border-t border-[#e5ebf3] pt-4">
                <div>
                    <div class="text-[9px] uppercase tracking-wide text-[#8491a5]">Utilized</div>
                    <div class="mt-1 text-[12px] font-bold text-[#183357]">₱{{ number_format($totalAllocated, 2) }}</div>
                </div>
                <div class="text-right">
                    <div class="text-[9px] uppercase tracking-wide text-[#8491a5]">Total budget</div>
                    <div class="mt-1 text-[12px] font-bold text-[#183357]">₱{{ number_format($totalBudget, 2) }}</div>
                </div>
            </div>

            @if(auth()->user()->isAdmin() || auth()->user()->isFocal())
                <a href="{{ route('adl.index') }}" class="mt-auto flex h-11 items-center justify-center gap-2 rounded-lg bg-[#063b86] text-[11px] font-bold text-white transition hover:bg-[#052f6d]">
                    View Budget Details
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"></path><path d="m13 6 6 6-6 6"></path></svg>
                </a>
            @else
                <div class="mt-auto rounded-lg border border-[#dfe6f0] bg-[#f8fbff] px-3 py-3 text-center text-[10px] font-medium text-[#647895]">Budget details are maintained by the Focal account.</div>
            @endif
        </aside>
    </div>

    {{-- Recent projects --}}
    <section class="tupad-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-[#e5ebf3] px-5 py-3.5 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-5">
                <h2 class="text-[14px] font-bold text-[#10294f]">Recent Projects</h2>
                <span class="border-b-2 border-[#1765d8] px-1 py-2 text-[10px] font-bold text-[#1765d8]">All</span>
            </div>

            @if(auth()->user()->isAdmin() || auth()->user()->isTc())
                <a href="{{ route('projects.index') }}" class="inline-flex h-8 items-center justify-center rounded-lg border border-[#ccd7e6] bg-white px-3 text-[10px] font-bold text-[#17325c] hover:bg-[#f5f8fc]">View All</a>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-[#fbfcfe]">
                    <tr class="border-b border-[#e7ecf3]">
                        <th class="px-5 py-3 text-left text-[9px] font-bold uppercase tracking-[.04em] text-[#596d89]">Project</th>
                        <th class="px-5 py-3 text-left text-[9px] font-bold uppercase tracking-[.04em] text-[#596d89]">Location</th>
                        <th class="px-5 py-3 text-right text-[9px] font-bold uppercase tracking-[.04em] text-[#596d89]">Beneficiaries</th>
                        <th class="px-5 py-3 text-right text-[9px] font-bold uppercase tracking-[.04em] text-[#596d89]">Budget</th>
                        <th class="px-5 py-3 text-left text-[9px] font-bold uppercase tracking-[.04em] text-[#596d89]">Status</th>
                        <th class="px-5 py-3 text-left text-[9px] font-bold uppercase tracking-[.04em] text-[#596d89]">Last Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#edf1f6]">
                    @forelse($recentProjects as $project)
                        <tr class="tupad-table-row transition">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#e8f0fb] text-[11px] font-extrabold text-[#1765d8]">{{ strtoupper(substr($project->project_title, 0, 1)) }}</div>
                                    <div class="min-w-[190px]">
                                        <div class="max-w-[300px] truncate text-[11px] font-bold text-[#17325c]">{{ $project->project_title }}</div>
                                        <div class="mt-0.5 text-[9px] text-[#8794a7]">{{ $project->approval?->project_code ?? 'Pending project code' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-[10px] text-[#536783]">{{ $project->municipality }}, {{ $project->province }}</td>
                            <td class="px-5 py-3.5 text-right text-[10px] font-semibold text-[#40597b]">{{ number_format($project->beneficiaries_total) }}</td>
                            <td class="px-5 py-3.5 text-right text-[10px] font-semibold text-[#40597b]">₱{{ number_format($project->total_project_cost, 2) }}</td>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex rounded-md border px-2 py-1 text-[9px] font-bold {{ $statusTone($project->status) }}">{{ $statusLabel($project->status) }}</span>
                            </td>
                            <td class="px-5 py-3.5 text-[9px] leading-4 text-[#667992]">
                                {{ Carbon::parse($project->updated_at)->format('M d, Y g:i A') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-12 text-center">
                                <div class="text-[12px] font-semibold text-[#536783]">No project records yet.</div>
                                <div class="mt-1 text-[10px] text-[#8b98aa]">Recent official projects will appear here.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
