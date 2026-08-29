@extends('layouts.app')

@section('title', 'TUPAD Coordinator Accounts')

@section('content')
    <x-page-header
        eyebrow="Administration"
        title="TUPAD Coordinator Accounts"
        description="Create and maintain province-assigned TUPAD Coordinator accounts. Coordinators are restricted to their assigned province when province enforcement is activated."
    >
        <x-slot:actions>
            <a href="{{ route('users.create') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                <span class="text-base leading-none">+</span>
                Add Coordinator
            </a>
        </x-slot:actions>
    </x-page-header>

    <section class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('users.index') }}" class="grid gap-3 lg:grid-cols-[minmax(220px,1fr)_220px_180px_auto] lg:items-end">
            <div>
                <label for="search" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Search</label>
                <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, username, or position"
                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>
            <div>
                <label for="province_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Province</label>
                <select id="province_id" name="province_id" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">All provinces</option>
                    @foreach ($provinces as $province)
                        <option value="{{ $province->id }}" @selected((string) ($filters['province_id'] ?? '') === (string) $province->id)>{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="status" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Status</label>
                <select id="status" name="status" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="flex gap-2">
                <button class="inline-flex h-10 items-center justify-center rounded-lg bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800">Filter</button>
                <a href="{{ route('users.index') }}" class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Coordinator Registry</h2>
                <p class="mt-1 text-xs text-slate-500">{{ number_format($coordinators->total()) }} TUPAD Coordinator account(s)</p>
            </div>
            <div class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-900">
                New/reset password: <span class="font-mono font-bold">password</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Coordinator</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Username</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Assigned Province</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($coordinators as $coordinator)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $coordinator->name }}</div>
                                <div class="mt-0.5 text-xs text-slate-500">{{ $coordinator->position ?: 'TUPAD Coordinator' }}</div>
                            </td>
                            <td class="px-5 py-4 font-mono text-sm text-slate-700">{{ $coordinator->username }}</td>
                            <td class="px-5 py-4">
                                @if ($coordinator->assignedProvince)
                                    <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-900">{{ $coordinator->assignedProvince->name }}</span>
                                @else
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Unassigned</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $coordinator->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $coordinator->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex min-w-[280px] justify-end gap-2">
                                    <a href="{{ route('users.edit', $coordinator) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>
                                    <form method="POST" action="{{ route('users.status', $coordinator) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold {{ $coordinator->is_active ? 'text-amber-700' : 'text-emerald-700' }} hover:bg-slate-50"
                                            onclick="return confirm('{{ $coordinator->is_active ? 'Deactivate' : 'Activate' }} this Coordinator account?')">
                                            {{ $coordinator->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('users.reset-password', $coordinator) }}">
                                        @csrf
                                        <button class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                            onclick="return confirm('Reset this account password to password?')">Reset Password</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state title="No Coordinator accounts found" message="Create a province-assigned TUPAD Coordinator account or change the current filters.">
                                    <x-slot:action>
                                        <a href="{{ route('users.create') }}" class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">Add Coordinator</a>
                                    </x-slot:action>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-5">{{ $coordinators->links() }}</div>
@endsection
