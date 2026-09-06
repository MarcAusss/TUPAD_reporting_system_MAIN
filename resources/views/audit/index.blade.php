@extends('layouts.app')

@section('title', 'Audit Trail')

@section('content')
    <x-page-header eyebrow="Administration" title="Audit Trail"
        description="Read-only system activity register for audited TUPAD records and account changes." />

    <section class="tupad-filter-panel mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('audit.index') }}" class="grid gap-3 xl:grid-cols-6 lg:items-end">
            <div class="xl:col-span-2">
                <label for="q" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Search</label>
                <input id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Actor, module, action, record type"
                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>
            <div>
                <label for="module" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Module</label>
                <select id="module" name="module" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">All modules</option>
                    @foreach ($modules as $module)
                        <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ $module }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="action" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Action</label>
                <select id="action" name="action" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">All actions</option>
                    @foreach (['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['action'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="user_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Actor</label>
                <select id="user_id" name="user_id" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                    <option value="">All actors</option>
                    @foreach ($actors as $actor)
                        <option value="{{ $actor->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('audit.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
            <div>
                <label for="date_from" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">From</label>
                <input id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
            </div>
            <div>
                <label for="date_to" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">To</label>
                <input id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
            </div>
        </form>
    </section>

    <section class="tupad-table-shell overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Activity Register</h2>
                <p class="mt-1 text-xs text-slate-500">{{ number_format($auditLogs->total()) }} matching audit record(s)</p>
            </div>
            <span class="text-[10px] font-semibold text-slate-400">Newest activity first</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Date & Time</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Actor</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Module</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Action</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Record</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Changed Fields</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Origin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($auditLogs as $log)
                        @php
                            $changedFields = collect(array_keys($log->new_values ?? []))
                                ->merge(array_keys($log->old_values ?? []))
                                ->unique()
                                ->reject(fn ($field) => in_array($field, ['updated_at', 'created_at'], true))
                                ->values();
                        @endphp
                        <tr class="align-top hover:bg-slate-50/70">
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-500">{{ $log->performed_at?->format('M d, Y h:i A') ?? '—' }}</td>
                            <td class="px-5 py-4">
                                <div class="text-xs font-semibold text-slate-800">{{ $log->user?->name ?? 'System' }}</div>
                                <div class="mt-0.5 text-[10px] text-slate-400">{{ $log->user?->username ?? 'Automated / seed' }}</div>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600">{{ $log->module }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-700">{{ $log->action }}</span>
                            </td>
                            <td class="px-5 py-4 text-xs text-slate-600">{{ $log->record_label }}</td>
                            <td class="px-5 py-4 text-xs text-slate-500">
                                {{ $changedFields->isNotEmpty() ? $changedFields->map(fn ($field) => str_replace('_', ' ', $field))->implode(', ') : 'No field delta recorded' }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-[11px] text-slate-600">{{ $log->ip_address ?: 'No IP recorded' }}</div>
                                <div class="mt-1 max-w-[220px] truncate text-[10px] text-slate-400" title="{{ $log->user_agent }}">{{ $log->user_agent ?: 'No user agent recorded' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No audit records found" message="No audit records match the selected filters." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($auditLogs->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">{{ $auditLogs->links() }}</div>
        @endif
    </section>
@endsection
