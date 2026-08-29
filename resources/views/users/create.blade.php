@extends('layouts.app')

@section('title', 'Add TUPAD Coordinator')

@section('content')
    <x-page-header
        eyebrow="Administration / User Accounts"
        title="Add TUPAD Coordinator"
        description="Create a Coordinator account and assign the province the account will be responsible for."
    >
        <x-slot:actions>
            <a href="{{ route('users.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to Accounts</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('users.store') }}" class="rounded-xl border border-slate-200 bg-white shadow-sm">
        @csrf
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Coordinator Account Details</h2>
            <p class="mt-1 text-xs leading-5 text-slate-500">The role is fixed to TUPAD Coordinator. The account starts with the default password <span class="font-mono font-semibold">password</span>.</p>
        </div>
        <div class="p-5">
            @include('users._form')
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-200 bg-slate-50 px-5 py-4">
            <a href="{{ route('users.index') }}" class="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
            <button class="inline-flex h-10 items-center rounded-lg bg-blue-700 px-5 text-sm font-semibold text-white hover:bg-blue-800">Create Coordinator</button>
        </div>
    </form>
@endsection
