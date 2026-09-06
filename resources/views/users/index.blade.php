@extends('layouts.app')

@php
    $pageTitle = $canManageAllRoles ? 'User Accounts' : 'TUPAD Coordinator Accounts';
    $pageDescription = $canManageAllRoles
        ? 'Manage Administrator, Focal, and TUPAD Coordinator accounts from one audited registry.'
        : 'Create and maintain province-assigned TUPAD Coordinator accounts. Coordinators are restricted to their assigned province when province enforcement is activated.';
@endphp

@section('title', $pageTitle)

@section('content')
    <x-page-header
        eyebrow="Administration"
        :title="$pageTitle"
        :description="$pageDescription"
    >
        <x-slot:actions>
            <a href="{{ route('users.create') }}"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800">
                <span class="text-base leading-none">+</span>
                {{ $canManageAllRoles ? 'Add User Account' : 'Add Coordinator' }}
            </a>
        </x-slot:actions>
    </x-page-header>

    @if (session('temporary_password'))
        <section class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-amber-800">One-time temporary password</div>
            <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-amber-950">Username: {{ session('temporary_password_username') }}</div>
                    <div class="mt-1 break-all font-mono text-lg font-bold text-slate-950">{{ session('temporary_password') }}</div>
                </div>
                <div class="max-w-sm text-xs leading-5 text-amber-900">Copy this credential now. It is not stored in plaintext and the user must replace it at the next sign-in.</div>
            </div>
        </section>
    @endif

    @if ($canManageAllRoles)
        <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="User role totals">
            @foreach ($roles as $role)
                <article class="tupad-metric-card rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-400">{{ $role->label() }}</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-900">{{ number_format((int) ($roleCounts[$role->value] ?? 0)) }}</div>
                    <p class="mt-1 text-xs text-slate-500">Registered {{ $role->shortLabel() }} account(s)</p>
                </article>
            @endforeach
        </section>
    @endif

    <section class="tupad-filter-panel mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <form method="GET" action="{{ route('users.index') }}" class="grid gap-3 {{ $canManageAllRoles ? 'xl:grid-cols-[minmax(220px,1fr)_180px_190px_160px_auto]' : 'lg:grid-cols-[minmax(220px,1fr)_220px_180px_auto]' }} lg:items-end">
            <div>
                <label for="search" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Search</label>
                <input id="search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name, username, email, or position"
                    class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>

            @if ($canManageAllRoles)
                <div>
                    <label for="role_filter" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">Role</label>
                    <select id="role_filter" name="role" class="h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

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

    <section class="tupad-table-shell overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">{{ $canManageAllRoles ? 'Account Registry' : 'Coordinator Registry' }}</h2>
                <p class="mt-1 text-xs text-slate-500">
                    {{ number_format($accounts->total()) }} {{ $canManageAllRoles ? 'user account(s)' : 'TUPAD Coordinator account(s)' }}
                </p>
            </div>
            <div class="rounded-lg border border-blue-100 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-900">
                New and reset accounts receive a one-time random password and are forced to change it at the next sign-in.
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Account</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Role</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Assignment / Scope</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($accounts as $account)
                        @php
                            $scope = match ($account->role) {
                                \App\Enums\UserRole::TC => $account->assignedProvince?->name ?? 'Unassigned province',
                                default => 'Regional access',
                            };
                            $scopeWarning = $account->isTc() && !$account->assignedProvince;
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-5 py-4">
                                <div class="text-sm font-semibold text-slate-900">{{ $account->name }}</div>
                                <div class="mt-0.5 text-xs text-slate-500">{{ $account->position ?: 'No designation' }}</div>
                                <div class="mt-1 font-mono text-[11px] text-slate-400">{{ $account->username }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $account->role->label() }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold {{ $scopeWarning ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-blue-200 bg-blue-50 text-blue-900' }}">{{ $scope }}</span>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $account->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if ($account->must_change_password)
                                    <div class="mt-1 text-[10px] font-semibold text-amber-700">Password change required</div>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex min-w-[280px] justify-end gap-2">
                                    <a href="{{ route('users.edit', $account) }}" class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">Edit</a>

                                    @if (!auth()->user()->is($account))
                                        <form method="POST" action="{{ route('users.status', $account) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold {{ $account->is_active ? 'text-amber-700' : 'text-emerald-700' }} hover:bg-slate-50"
                                                onclick="return confirm('{{ $account->is_active ? 'Deactivate' : 'Activate' }} this {{ $account->role->label() }} account?')">
                                                {{ $account->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('users.reset-password', $account) }}">
                                            @csrf
                                            <button class="inline-flex h-9 items-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                onclick="return confirm('Generate a new temporary password for this account? Existing remembered sessions will be invalidated and a password change will be required at the next sign-in.')">Reset Password</button>
                                        </form>
                                    @else
                                        <span class="inline-flex h-9 items-center rounded-lg bg-slate-100 px-3 text-xs font-semibold text-slate-500">Signed in</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-0">
                                <x-empty-state :title="$canManageAllRoles ? 'No user accounts found' : 'No Coordinator accounts found'" :message="$canManageAllRoles ? 'Create an account or change the current filters.' : 'Create a province-assigned TUPAD Coordinator account or change the current filters.'">
                                    <x-slot:action>
                                        <a href="{{ route('users.create') }}" class="inline-flex h-9 items-center rounded-lg bg-slate-900 px-4 text-xs font-semibold text-white hover:bg-slate-800">{{ $canManageAllRoles ? 'Add User Account' : 'Add Coordinator' }}</a>
                                    </x-slot:action>
                                </x-empty-state>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="mt-5">{{ $accounts->links() }}</div>
@endsection
