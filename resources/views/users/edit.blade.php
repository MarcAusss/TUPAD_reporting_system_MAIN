@extends('layouts.app')

@section('title', 'Edit TUPAD Coordinator')

@section('content')
    <x-page-header
        eyebrow="Administration / User Accounts"
        title="Edit TUPAD Coordinator"
        description="Update the Coordinator identity, province assignment, designation, and account status."
    >
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to Accounts</a>
        </x-slot:actions>
    </x-page-header>


    @if (session('temporary_password'))
        <section class="mb-5 rounded-xl border border-amber-300 bg-amber-50 p-4 shadow-sm">
            <div class="text-xs font-bold uppercase tracking-wider text-amber-800">One-time temporary password</div>
            <div class="mt-2 text-sm font-semibold text-amber-950">Username: {{ session('temporary_password_username') }}</div>
            <div class="mt-1 break-all font-mono text-lg font-bold text-slate-950">{{ session('temporary_password') }}</div>
            <p class="mt-2 text-xs leading-5 text-amber-900">Copy this credential now. It will not be shown again after this request. The Coordinator must replace it before accessing the system.</p>
        </section>
    @endif

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_320px]">
        <form method="POST" action="{{ route('users.update', $coordinator) }}" class="rounded-xl border border-slate-200 bg-white shadow-sm">
            @csrf
            @method('PUT')
            <div class="border-b border-slate-200 px-5 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Account Details</h2>
                <p class="mt-1 text-xs text-slate-500">Changes to province assignment are audited. Password is not changed by this form.</p>
            </div>
            <div class="p-5">
                @include('users._form')
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
                <a href="{{ route('users.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button class="inline-flex h-10 items-center rounded-lg bg-blue-700 px-5 text-sm font-semibold text-white hover:bg-blue-800">Save Changes</button>
            </div>
        </form>

        <aside class="space-y-4">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Current Assignment</div>
                <div class="mt-2 text-lg font-bold text-slate-900">{{ $coordinator->assignedProvince?->name ?? 'Unassigned' }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ $coordinator->username }}</div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-slate-900">Password Reset</h2>
                <p class="mt-2 text-xs leading-5 text-slate-500">Resetting generates a new one-time temporary password, invalidates the remembered login token, and forces a password change at the next sign-in.</p>
                <form method="POST" action="{{ route('users.reset-password', $coordinator) }}" class="mt-4">
                    @csrf
                    <button class="inline-flex h-10 w-full items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        onclick="return confirm('Generate a new temporary password for this Coordinator?')">Generate Temporary Password</button>
                </form>
            </section>

            <section class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                <div class="text-sm font-semibold text-amber-900">No account deletion</div>
                <p class="mt-1 text-xs leading-5 text-amber-800">Use Active/Inactive instead of deleting an account so existing project and audit references remain intact.</p>
            </section>
        </aside>
    </div>
@endsection
