@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <x-page-header eyebrow="System Attention Center" title="Notifications"
        description="Live role-scoped alerts generated from the current TUPAD workflow. Updates appear automatically while this page is open.">
        <x-slot:actions>
            <a href="{{ route('dashboard') }}"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Back to Dashboard
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="mb-5 grid gap-3 sm:grid-cols-3">
        <article class="tupad-metric-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Active Alerts</div>
            <div data-notification-total class="mt-2 text-2xl font-extrabold text-slate-900">
                {{ number_format($notificationData['total_count']) }}</div>
            <p class="mt-1 text-xs text-slate-500">Current records represented by your alerts.</p>
        </article>
        <article class="tupad-metric-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Needs Attention</div>
            <div data-notification-attention class="mt-2 text-2xl font-extrabold text-amber-700">
                {{ number_format($notificationData['attention_count']) }}</div>
            <p class="mt-1 text-xs text-slate-500">Aged or returned records requiring closer review.</p>
        </article>
        <article class="tupad-metric-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Critical Aging</div>
            <div data-notification-critical class="mt-2 text-2xl font-extrabold text-rose-700">
                {{ number_format($notificationData['critical_count']) }}</div>
            <p class="mt-1 text-xs text-slate-500">Workflow items beyond the configured critical threshold.</p>
        </article>
    </section>

    @php
        $editRequestItems = collect($notificationData['project_items'] ?? [])
            ->filter(fn (array $item): bool => in_array($item['kind'] ?? null, ['edit_request', 'edit_decision'], true));
    @endphp

    @if ($editRequestItems->isNotEmpty())
        <section class="mb-5 overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
            <div class="border-b border-amber-100 bg-amber-50 px-5 py-4">
                <h2 class="text-sm font-semibold text-amber-950">Edit Requests</h2>
                <p class="mt-1 text-xs text-amber-800">
                    TUPAD Coordinator edits to project sections need Focal approval. An approval unlocks one save.
                </p>
            </div>

            <div class="divide-y divide-slate-100">
                @foreach ($editRequestItems as $item)
                    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-slate-900">{{ $item['message'] }}</div>
                            <div class="mt-0.5 text-xs text-slate-500">{{ $item['project_title'] }} · {{ $item['occurred_human'] }}</div>
                            @if (! empty($item['reason']))
                                <p class="mt-1 text-xs italic text-slate-600">“{{ $item['reason'] }}”</p>
                            @endif
                        </div>

                        <div class="flex shrink-0 flex-wrap gap-2">
                            <a href="{{ $item['url'] }}"
                                class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                View Section
                            </a>
                            @foreach ($item['actions'] ?? [] as $action)
                                <form method="POST" action="{{ $action['url'] }}">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex h-9 items-center rounded-lg px-4 text-xs font-semibold {{ $action['style'] === 'primary' ? 'bg-[#063b86] text-white hover:bg-[#052f6b]' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' }}">
                                        {{ $action['label'] }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="tupad-table-shell overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Current Notifications</h2>
            <p class="mt-1 text-xs text-slate-500">
                Notifications update automatically from authoritative workflow state; no browser refresh or separate
                read/unread record is required.
            </p>
        </div>

        <div data-live-notification-list class="divide-y divide-slate-100">
            @if ($notificationData['items']->isEmpty())
                <div class="px-5 py-12 text-center" data-notification-empty>
                    <div class="text-sm font-semibold text-slate-800">No pending notifications</div>
                    <p class="mt-1 text-xs text-slate-500">There are no current workflow actions requiring your role.</p>
                </div>
            @else
                @foreach ($notificationData['items'] as $item)
                    @php
                        $tone = match ($item['severity']) {
                            'critical' => 'border-rose-200 bg-rose-50 text-rose-800',
                            'attention' => 'border-amber-200 bg-amber-50 text-amber-800',
                            default => 'border-blue-100 bg-blue-50 text-blue-800',
                        };
                    @endphp
                    <div class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $tone }}">
                                    {{ $item['category'] }}
                                </span>
                                <span class="text-xs font-semibold text-slate-500">{{ number_format($item['count']) }}
                                    item(s)</span>
                            </div>
                            <div class="mt-2 text-sm font-semibold text-slate-900">{{ $item['title'] }}</div>
                            <p class="mt-1 text-xs leading-5 text-slate-500">{{ $item['message'] }}</p>
                        </div>
                        <a href="{{ $item['url'] }}"
                            class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">
                            Open Queue
                        </a>
                    </div>
                @endforeach
            @endif
        </div>
    </section>
@endsection
