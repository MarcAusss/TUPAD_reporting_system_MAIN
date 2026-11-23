{{--
    Request GPAI Enrollment (Overview): while the project waits for GSIS GPAI
    enrollment (before approval included), the TUPAD Coordinator — or Focal /
    Admin — can ask the GSIS Enrollment Officer to enroll it. The project then
    tops the officer's Enroll Projects list. 3 requests per 3 hours.
--}}
@php
    $gpaiService = app(\App\Services\Gsis\GpaiEnrollmentService::class);
    $viewer = auth()->user();
    $canRequestGpai = $viewer && ($viewer->isTc() || $viewer->isFocal() || $viewer->isAdmin()) && $gpaiService->isEligible($project);
@endphp

@if ($canRequestGpai)
    @php
        $allowance = $gpaiService->requestAllowance($project);
        $limit = \App\Services\Gsis\GpaiEnrollmentService::REQUEST_LIMIT;
        $windowHours = \App\Services\Gsis\GpaiEnrollmentService::REQUEST_WINDOW_HOURS;
    @endphp
    <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 shadow-sm xl:col-span-2" data-gpai-request>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-amber-700">GSIS GPAI Enrollment</div>
                <h2 class="mt-1 text-sm font-bold text-slate-900">This project is waiting for GPAI enrollment</h2>
                <p class="mt-1 text-xs leading-5 text-slate-600">
                    Notify the GSIS Enrollment Officer to enroll {{ number_format((int) $project->insurance_beneficiaries) }} insured beneficiaries
                    (premium ₱{{ number_format((float) $project->insurance_total, 2) }}). Requested projects go to the top of their Enroll Projects list.
                </p>
                @if ($allowance['last'])
                    <p class="mt-1 text-[11px] text-slate-500">
                        Last requested {{ $allowance['last']->created_at->diffForHumans() }}
                        by {{ $allowance['last']->requester?->name ?? 'a former user' }} · {{ $allowance['total'] }} request(s) in all.
                    </p>
                @endif
                @if (session('gpai_request_error'))
                    <p class="mt-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" role="alert">{{ session('gpai_request_error') }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('projects.gpai-enrollment-request', $project) }}" class="shrink-0 text-right">
                @csrf
                <button type="submit" @disabled($allowance['remaining'] < 1)
                    class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-5 text-sm font-semibold text-white hover:bg-[#052f6b] disabled:cursor-not-allowed disabled:bg-slate-300">
                    Request GPAI Enrollment
                </button>
                <div class="mt-1 text-[11px] text-slate-500" data-gpai-request-allowance>
                    @if ($allowance['remaining'] > 0)
                        {{ $allowance['remaining'] }} of {{ $limit }} requests left
                        @if ($allowance['resets_at'])
                            · resets {{ $allowance['resets_at']->format('g:i A') }}
                        @endif
                    @else
                        Limit reached ({{ $limit }} per {{ $windowHours }} hours) · available again at {{ $allowance['resets_at']->format('g:i A') }}
                    @endif
                </div>
            </form>
        </div>
    </section>
@endif
