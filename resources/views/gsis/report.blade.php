@extends('layouts.app')

@section('title', 'GPAI Enrollment Report')

@php
    use App\Models\GpaiEnrollment as Gpai;

    $peso = fn (int $cents): string => '₱'.number_format($cents / 100, 2);
    $periodOptions = ['annual' => 'Annual', 'semester' => 'Semester', 'quarter' => 'Quarterly', 'month' => 'Monthly'];
    $partOptions = [
        'semester' => [1 => '1st Semester', 2 => '2nd Semester'],
        'quarter' => [1 => 'Q1 (Jan–Mar)', 2 => 'Q2 (Apr–Jun)', 3 => 'Q3 (Jul–Sep)', 4 => 'Q4 (Oct–Dec)'],
        'month' => collect(range(1, 12))->mapWithKeys(fn ($month) => [$month => \Illuminate\Support\Carbon::create(2000, $month, 1)->format('F')])->all(),
    ];
    $stageTone = [
        'prepared' => 'bg-slate-100 text-slate-700',
        'for_billing' => 'bg-indigo-50 text-indigo-700',
        'for_routing' => 'bg-sky-50 text-sky-700',
        'for_payment' => 'bg-amber-50 text-amber-800',
        'for_or_policy' => 'bg-violet-50 text-violet-700',
        'complete' => 'bg-emerald-50 text-emerald-700',
    ];

    // Enrollment information for the row modal, keyed by enrollment id.
    $formatStep = fn ($value, string $type): ?string => $value ? $value->format($type === 'date' ? 'M j, Y' : 'M j, Y g:i A') : null;
    $enrollmentInfo = $enrollments->mapWithKeys(fn ($enrollment) => [$enrollment->id => [
        'gpai_number' => $enrollment->gpai_number,
        'nature' => $enrollment->natureOfPayment(),
        'payment' => Gpai::modeLabel($enrollment->payment_mode).($enrollment->cashAdvance ? ' · '.$enrollment->cashAdvance->label() : ''),
        'transmittal_date' => $enrollment->transmittal_date->format('F j, Y'),
        'coverage' => $enrollment->coverage_start->format('M j, Y').' – '.$enrollment->coverage_end->format('M j, Y'),
        'stage' => $enrollment->stageLabel(),
        'next_step' => $enrollment->nextStepLabel(),
        'billing_status' => $enrollment->billingStatusLabel(),
        'received_via' => $enrollment->receivedViaLabel(),
        'ops_status' => $enrollment->opsStatusLabel(),
        'or_number' => $enrollment->or_number,
        'policy_id' => $enrollment->policy_id,
        'doc_stamp' => $peso($enrollment->docStampCents()),
        'total' => $peso($enrollment->totalCents()),
        'beneficiaries' => number_format($enrollment->beneficiaries()),
        'remarks' => $enrollment->remarks,
        'document_url' => $enrollment->ops_document_url,
        'steps' => collect(Gpai::STEPS)->map(fn (array $step, string $field) => [
            'label' => $step[0],
            'value' => $formatStep($enrollment->{$field}, $step[1]),
        ])->values(),
        'projects' => $enrollment->items->map(fn ($item) => [
            'title' => $item->project?->project_title ?? '—',
            'code' => $item->project?->approval?->project_code,
            'beneficiaries' => number_format($item->beneficiaries),
            'amount' => $peso($item->beneficiaries * Gpai::REPORT_RATE * 100),
        ])->values(),
        'url' => route('gsis.enrollments.edit', $enrollment),
    ]]);
@endphp

@section('content')
    <x-page-header
        eyebrow="GSIS GPAI Enrollment"
        title="Enrolled Projects Report"
        :description="'Enrolled projects with their beneficiaries and amount (beneficiaries × ₱'.Gpai::REPORT_RATE.') plus the documentary stamp of each transmittal, by transmittal date: '.$periodLabel.'. Click a row for the enrollment information.'"
    >
        <x-slot:actions>
            <button type="button" onclick="window.print()"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 print:hidden">
                Print
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- Period filter --}}
    <form method="GET" action="{{ route('gsis.report') }}" class="mb-5 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm print:hidden" data-report-filter>
        <div>
            <label for="fiscal_year" class="mb-1 block text-xs font-semibold text-slate-600">Fiscal Year</label>
            <select id="fiscal_year" name="fiscal_year" onchange="this.form.submit()" class="h-10 w-32 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                @foreach ($fiscalYears as $year)
                    <option value="{{ $year }}" @selected($year === $fiscalYear)>FY {{ $year }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <span class="mb-1 block text-xs font-semibold text-slate-600">Report</span>
            <div class="flex rounded-lg border border-slate-300 bg-white p-0.5">
                @foreach ($periodOptions as $value => $label)
                    <label class="cursor-pointer rounded-md px-3 py-1.5 text-xs font-semibold {{ $period === $value ? 'bg-[#063b86] text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                        <input type="radio" name="period" value="{{ $value }}" class="sr-only" @checked($period === $value) onchange="this.form.part && (this.form.part.value = 'all'); this.form.submit()">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
        <div>
            <span class="mb-1 block text-xs font-semibold text-slate-600">Payment</span>
            <div class="flex rounded-lg border border-slate-300 bg-white p-0.5">
                @foreach (['' => 'All', Gpai::MODE_VOUCHER => 'Voucher', Gpai::MODE_CASH_ADVANCE => 'CA'] as $value => $label)
                    <label class="cursor-pointer rounded-md px-3 py-1.5 text-xs font-semibold {{ (string) $mode === (string) $value ? 'bg-[#063b86] text-white' : 'text-slate-600 hover:bg-slate-50' }}">
                        <input type="radio" name="mode" value="{{ $value }}" class="sr-only" @checked((string) $mode === (string) $value) onchange="this.form.submit()">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
        @if ($period !== 'annual')
            <div>
                <label for="part" class="mb-1 block text-xs font-semibold text-slate-600">{{ ['semester' => 'Semester', 'quarter' => 'Quarter', 'month' => 'Month'][$period] }}</label>
                <select id="part" name="part" onchange="this.form.submit()" class="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="all" @selected($part === null)>All {{ ['semester' => 'semesters', 'quarter' => 'quarters', 'month' => 'months'][$period] }}</option>
                    @foreach ($partOptions[$period] as $value => $label)
                        <option value="{{ $value }}" @selected($part === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <noscript><button class="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold">Show</button></noscript>
    </form>

    {{-- Voucher / Cash Advance / Overall totals --}}
    <section class="mb-5 grid gap-4 {{ $mode ? 'sm:grid-cols-1' : 'sm:grid-cols-3' }}" aria-label="Report totals">
        @foreach ($mode ? $sections->map(fn ($section) => [$section['label'], $section['totals']])->values()->all() : [...$sections->map(fn ($section) => [$section['label'], $section['totals']])->values()->all(), ['Overall', $overall]] as [$label, $total])
            <article class="rounded-xl border {{ $label === 'Overall' ? 'border-[#063b86] bg-blue-50' : 'border-slate-200 bg-white' }} p-4 shadow-sm">
                <div class="text-[11px] font-bold uppercase tracking-[0.08em] {{ $label === 'Overall' ? 'text-[#063b86]' : 'text-slate-400' }}">{{ $label }}</div>
                <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $peso($total['total_cents']) }}</div>
                <p class="mt-1 text-xs text-slate-500">Premium {{ $peso($total['amount_cents']) }} + Doc. stamp {{ $peso($total['doc_stamp_cents']) }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ number_format($total['transmittals']) }} {{ str('transmittal')->plural($total['transmittals']) }} · {{ number_format($total['projects']) }} {{ str('project')->plural($total['projects']) }} · {{ number_format($total['beneficiaries']) }} beneficiaries</p>
            </article>
        @endforeach
    </section>

    @foreach ($sections as $mode => $section)
        <section class="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm" data-report-section="{{ $mode }}">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-900">{{ $section['label'] }} <span class="font-normal text-slate-500">· {{ $periodLabel }}</span></h2>
                <span class="text-xs text-slate-500">{{ number_format($section['totals']['projects']) }} {{ str('project')->plural($section['totals']['projects']) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="tupad-system-table min-w-full text-sm">
                    <thead>
                        <tr>
                            <th class="p-3 text-left">No.</th>
                            <th class="p-3 text-left">GPAI No.</th>
                            <th class="p-3 text-left">Transmitted</th>
                            <th class="p-3 text-left">Project</th>
                            <th class="p-3 text-left">Project Code</th>
                            <th class="p-3 text-left">Location</th>
                            <th class="p-3 text-right">Beneficiaries</th>
                            <th class="p-3 text-right">Amount (× ₱{{ Gpai::REPORT_RATE }})</th>
                            <th class="p-3 text-right">Doc. Stamp <span class="block text-[10px] font-normal normal-case">per transmittal</span></th>
                            <th class="p-3 text-right">Transmittal Total</th>
                            <th class="p-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($rowGroups = $grouped ? $section['groups'] : collect([['label' => null, 'rows' => $section['rows'], 'totals' => $section['totals']]]))
                        @foreach ($rowGroups as $rowGroup)
                        @if ($rowGroup['label'])
                            <tr class="bg-slate-50"><td colspan="11" class="p-2 px-3 text-xs font-bold uppercase tracking-wide text-slate-600">{{ $rowGroup['label'] }}</td></tr>
                        @endif
                        @foreach ($rowGroup['rows'] as $row)
                            @php($enrollment = $row['enrollment'])
                            <tr data-enrollment-id="{{ $enrollment->id }}" tabindex="0" role="button"
                                aria-label="Open enrollment information for {{ $row['project']?->project_title }}"
                                class="cursor-pointer hover:bg-slate-50 focus:bg-blue-50 focus:outline-none">
                                <td class="p-3 text-slate-500">{{ $loop->iteration }}</td>
                                <td class="whitespace-nowrap p-3 font-semibold text-[#063b86]">{{ $enrollment->gpai_number }}</td>
                                <td class="whitespace-nowrap p-3 text-slate-600">{{ $enrollment->transmittal_date->format('M j, Y') }}</td>
                                <td class="min-w-64 p-3 font-semibold text-slate-900">{{ $row['project']?->project_title ?? '—' }}</td>
                                <td class="whitespace-nowrap p-3">
                                    @if ($row['project_code'])
                                        <span class="font-semibold text-slate-800">{{ $row['project_code'] }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">Not yet assigned</span>
                                    @endif
                                </td>
                                <td class="p-3 text-slate-600">{{ collect([$row['project']?->municipality, $row['project']?->province])->filter()->implode(', ') ?: '—' }}</td>
                                <td class="p-3 text-right">{{ number_format($row['beneficiaries']) }}</td>
                                <td class="whitespace-nowrap p-3 text-right font-semibold">{{ $peso($row['amount_cents']) }}</td>
                                @if ($row['first_of_transmittal'])
                                    @php($transmittalTotal = $enrollment->items->sum(fn ($item) => (int) $item->beneficiaries * Gpai::REPORT_RATE * 100) + $row['doc_stamp_cents'])
                                    <td rowspan="{{ $row['transmittal_rows'] }}" class="whitespace-nowrap border-l border-slate-100 p-3 text-right align-middle">{{ $peso($row['doc_stamp_cents']) }}</td>
                                    <td rowspan="{{ $row['transmittal_rows'] }}" class="whitespace-nowrap border-l border-slate-100 p-3 text-right align-middle font-bold text-slate-900">{{ $peso($transmittalTotal) }}</td>
                                @endif
                                <td class="whitespace-nowrap p-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $stageTone[$enrollment->stage()] }}">{{ $enrollment->stageLabel() }}</span>
                                </td>
                            </tr>
                        @endforeach
                        @if ($rowGroup['label'])
                            <tr class="font-semibold">
                                <td colspan="6" class="p-3 text-right text-slate-600">Sub-total · {{ $rowGroup['label'] }}</td>
                                <td class="p-3 text-right">{{ number_format($rowGroup['totals']['beneficiaries']) }}</td>
                                <td class="whitespace-nowrap p-3 text-right">{{ $peso($rowGroup['totals']['amount_cents']) }}</td>
                                <td class="whitespace-nowrap p-3 text-right">{{ $peso($rowGroup['totals']['doc_stamp_cents']) }}</td>
                                <td class="whitespace-nowrap p-3 text-right">{{ $peso($rowGroup['totals']['total_cents']) }}</td>
                                <td></td>
                            </tr>
                        @endif
                        @endforeach
                        @if ($section['rows']->isEmpty())
                            <tr><td colspan="11" class="p-6 text-center text-sm text-slate-400">No projects enrolled by {{ $section['label'] }} for {{ $periodLabel }}.</td></tr>
                        @endif
                        @if ($section['rows']->isNotEmpty())
                            <tr class="font-bold">
                                <td colspan="6" class="p-3 text-right">Sub-total · {{ $section['label'] }}</td>
                                <td class="p-3 text-right">{{ number_format($section['totals']['beneficiaries']) }}</td>
                                <td class="whitespace-nowrap p-3 text-right">{{ $peso($section['totals']['amount_cents']) }}</td>
                                <td class="whitespace-nowrap p-3 text-right">{{ $peso($section['totals']['doc_stamp_cents']) }}</td>
                                <td class="whitespace-nowrap p-3 text-right">{{ $peso($section['totals']['total_cents']) }}</td>
                                <td></td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach

    <section class="overflow-hidden rounded-xl border border-[#063b86] bg-white shadow-sm" data-report-overall>
        <table class="tupad-system-table min-w-full text-sm">
            <thead>
                <tr>
                    <th class="p-3 text-left">Overall · {{ $periodLabel }}</th>
                    <th class="p-3 text-right">Projects</th>
                    <th class="p-3 text-right">Beneficiaries</th>
                    <th class="p-3 text-right">Transmittals</th>
                    <th class="p-3 text-right">Premium</th>
                    <th class="p-3 text-right">Doc. Stamp</th>
                    <th class="p-3 text-right">Total Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sections as $section)
                    <tr>
                        <td class="p-3">{{ $section['label'] }}</td>
                        <td class="p-3 text-right">{{ number_format($section['totals']['projects']) }}</td>
                        <td class="p-3 text-right">{{ number_format($section['totals']['beneficiaries']) }}</td>
                        <td class="p-3 text-right">{{ number_format($section['totals']['transmittals']) }}</td>
                        <td class="p-3 text-right">{{ $peso($section['totals']['amount_cents']) }}</td>
                        <td class="p-3 text-right">{{ $peso($section['totals']['doc_stamp_cents']) }}</td>
                        <td class="p-3 text-right font-semibold">{{ $peso($section['totals']['total_cents']) }}</td>
                    </tr>
                @endforeach
                <tr class="font-bold">
                    <td class="p-3">TOTAL</td>
                    <td class="p-3 text-right">{{ number_format($overall['projects']) }}</td>
                    <td class="p-3 text-right">{{ number_format($overall['beneficiaries']) }}</td>
                    <td class="p-3 text-right">{{ number_format($overall['transmittals']) }}</td>
                    <td class="p-3 text-right">{{ $peso($overall['amount_cents']) }}</td>
                    <td class="p-3 text-right">{{ $peso($overall['doc_stamp_cents']) }}</td>
                    <td class="p-3 text-right">{{ $peso($overall['total_cents']) }}</td>
                </tr>
            </tbody>
        </table>
    </section>

    {{-- Enrollment information modal (filled from the clicked row) --}}
    <dialog id="enrollmentInfo" aria-labelledby="enrollmentInfoTitle"
        class="tupad-dialog w-[calc(100%-2rem)] max-w-2xl rounded-2xl border border-slate-200 bg-white p-0 text-left shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 pt-5 pb-4">
            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-500">Enrollment Information</div>
                <h2 id="enrollmentInfoTitle" class="mt-1 text-lg font-bold text-slate-900" data-info="gpai_number"></h2>
                <p class="text-xs text-slate-500" data-info="nature"></p>
            </div>
            <button type="button" data-info-close class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Close">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
            </button>
        </div>
        <div class="max-h-[65vh] space-y-5 overflow-y-auto px-6 py-5 text-sm">
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2" data-info-facts></dl>
            <div>
                <h3 class="mb-2 text-xs font-bold uppercase tracking-[0.08em] text-slate-500">Projects in this Transmittal</h3>
                <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200" data-info-projects></ul>
            </div>
            <div>
                <h3 class="mb-2 text-xs font-bold uppercase tracking-[0.08em] text-slate-500">GSIS Processing</h3>
                <ol class="space-y-1.5" data-info-steps></ol>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/70 px-6 py-3">
            <a href="#" data-info-document target="_blank" rel="noopener noreferrer" class="hidden h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Open Google Drive ↗</a>
            <a href="#" data-info-open class="inline-flex h-10 items-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b]">Open Transmittal →</a>
        </div>
    </dialog>

    <script id="enrollmentInfoData" type="application/json">@json($enrollmentInfo)</script>
@endsection

@push('scripts')
    <script>
        (function () {
            const dialog = document.getElementById('enrollmentInfo');
            const info = JSON.parse(document.getElementById('enrollmentInfoData').textContent || '{}');

            function el(tag, className, text) {
                const node = document.createElement(tag);
                if (className) node.className = className;
                if (text !== undefined) node.textContent = text;
                return node;
            }

            function open(id) {
                const data = info[id];
                if (!data) return;

                dialog.querySelectorAll('[data-info]').forEach((node) => { node.textContent = data[node.dataset.info] ?? ''; });

                const facts = [
                    ['Payment', data.payment], ['Transmitted', data.transmittal_date], ['Coverage', data.coverage],
                    ['Status', data.stage], ['Next Step', data.next_step || 'All steps recorded'],
                    ['Table 1 Status', data.billing_status], ['Received via', data.received_via], ['Table 2 Status', data.ops_status],
                    ['OR Number', data.or_number], ['Policy ID', data.policy_id],
                    ['Beneficiaries', data.beneficiaries], ['Doc. Stamp', data.doc_stamp], ['Transmittal Total', data.total],
                    ['Remarks', data.remarks],
                ];
                dialog.querySelector('[data-info-facts]').replaceChildren(...facts.map(([label, value]) => {
                    const wrap = el('div');
                    wrap.append(el('dt', 'text-[11px] font-semibold uppercase tracking-wide text-slate-400', label), el('dd', 'font-semibold text-slate-800', value || '—'));
                    return wrap;
                }));

                dialog.querySelector('[data-info-projects]').replaceChildren(...data.projects.map((project) => {
                    const li = el('li', 'flex items-start justify-between gap-3 px-3 py-2');
                    const left = el('div', 'min-w-0');
                    left.append(el('div', 'font-semibold text-slate-800', project.title), el('div', 'text-xs text-slate-500', project.code ? `Project Code ${project.code}` : 'Project Code not yet assigned'));
                    li.append(left, el('div', 'shrink-0 text-right text-xs text-slate-600', `${project.beneficiaries} benef. · ${project.amount}`));
                    return li;
                }));

                dialog.querySelector('[data-info-steps]').replaceChildren(...data.steps.map((step) => {
                    const li = el('li', 'flex items-center justify-between gap-3 text-xs');
                    li.append(
                        el('span', step.value ? 'text-slate-700' : 'text-slate-400', `${step.value ? '✓' : '○'} ${step.label}`),
                        el('span', 'shrink-0 font-semibold text-slate-800', step.value || '—'),
                    );
                    return li;
                }));

                dialog.querySelector('[data-info-open]').href = data.url;
                const documentLink = dialog.querySelector('[data-info-document]');
                documentLink.classList.toggle('hidden', !data.document_url);
                documentLink.classList.toggle('inline-flex', !!data.document_url);
                documentLink.href = data.document_url || '#';

                dialog.showModal();
            }

            document.querySelectorAll('tr[data-enrollment-id]').forEach((row) => {
                row.addEventListener('click', () => open(row.dataset.enrollmentId));
                row.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        open(row.dataset.enrollmentId);
                    }
                });
            });

            dialog.querySelector('[data-info-close]').addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
        })();
    </script>
@endpush
