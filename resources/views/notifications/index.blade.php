@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
    <x-page-header eyebrow="System Attention Center" title="Notifications"
        description="Live role-scoped alerts generated from the current TUPAD workflow and draft records.">
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
            <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format($notificationData['total_count']) }}</div>
            <p class="mt-1 text-xs text-slate-500">Current records represented by your alerts.</p>
        </article>
        <article class="tupad-metric-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Needs Attention</div>
            <div class="mt-2 text-2xl font-extrabold text-amber-700">{{ number_format($notificationData['attention_count']) }}</div>
            <p class="mt-1 text-xs text-slate-500">Aged or returned records requiring closer review.</p>
        </article>
        <article class="tupad-metric-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">Critical Aging</div>
            <div class="mt-2 text-2xl font-extrabold text-rose-700">{{ number_format($notificationData['critical_count']) }}</div>
            <p class="mt-1 text-xs text-slate-500">Workflow items beyond the configured critical threshold.</p>
        </article>
    </section>

    <section class="tupad-table-shell overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Current Notifications</h2>
            <p class="mt-1 text-xs text-slate-500">
                Notifications are resolved automatically when the authoritative workflow or draft state changes; no separate read/unread state is stored.
            </p>
        </div>

        @if ($notificationData['items']->isEmpty())
            <x-empty-state title="No pending notifications"
                message="There are no current workflow or draft actions requiring your role." />
        @else
            <div class="divide-y divide-slate-100">
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
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $tone }}">
                                    {{ $item['category'] }}
                                </span>
                                <span class="text-xs font-semibold text-slate-500">{{ number_format($item['count']) }} item(s)</span>
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
            </div>
        @endif
    </section>
@endsection
