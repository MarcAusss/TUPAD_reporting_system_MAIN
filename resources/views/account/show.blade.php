@extends('layouts.app')

@section('title', 'My Account')

@section('content')
    <x-page-header
        eyebrow="Account"
        title="My Account"
        description="Review your assigned TUPAD Coordinator account and securely change your password."
    />

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_390px]">
        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Coordinator Account</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Your username, role, province assignment, and designation are managed by the Focal account and cannot be edited here.
                </p>
            </div>

            <dl class="grid gap-px bg-slate-200 sm:grid-cols-2">
                @foreach ([
                    ['Full Name', $coordinator->name],
                    ['Username', $coordinator->username],
                    ['Role', $coordinator->roleLabel()],
                    ['Assigned Province', $coordinator->assignedProvince?->name ?? 'Not assigned'],
                    ['Position / Designation', $coordinator->position ?: 'TUPAD Coordinator'],
                    ['Account Status', $coordinator->is_active ? 'Active' : 'Inactive'],
                ] as [$label, $value])
                    <div class="bg-white px-5 py-5">
                        <dt class="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{{ $label }}</dt>
                        <dd class="mt-2 text-sm font-semibold text-slate-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            <div class="border-t border-slate-200 bg-slate-50 px-5 py-4">
                @if ($mappingAccessReady)
                    <div class="mb-3 inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                        Geographic Mapping access ready
                    </div>
                @else
                    <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-semibold leading-5 text-amber-800">
                        Geographic Mapping is unavailable until the Focal assigns this account to one active Bicol province.
                    </div>
                @endif

                <p class="text-xs leading-5 text-slate-600">
                    If your username, province assignment, or designation is incorrect, contact the Focal account administrator. TUPAD Coordinators can change only their own password.
                </p>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Change Password</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Enter your current password before setting a new password. The new password must contain at least 8 characters.
                </p>
            </div>

            <form method="POST" action="{{ route('account.password.update') }}" class="space-y-5 p-5">
                @csrf
                @method('PATCH')

                <div>
                    <label for="current_password" class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Current Password <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="current_password"
                        name="current_password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                    @error('current_password')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">
                        New Password <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                    @error('password')
                        <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">
                        Confirm New Password <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="password_confirmation"
                        name="password_confirmation"
                        type="password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                    >
                </div>

                <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 text-xs leading-5 text-blue-900">
                    Changing your password does not change your assigned province, role, username, or project access.
                </div>

                <button
                    type="submit"
                    class="inline-flex h-10 w-full items-center justify-center rounded-lg bg-blue-700 px-5 text-sm font-semibold text-white transition hover:bg-blue-800"
                >
                    Update Password
                </button>
            </form>
        </section>
    </div>
@endsection
