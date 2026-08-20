@extends('layouts.app')

@section('title', 'Project Drafts')

@section('content')

    <div class="mx-auto max-w-3xl">

        <section class="rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">

            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-500">

                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 4h16v16H4z"></path>
                    <path d="M8 9h8"></path>
                    <path d="M8 13h6"></path>
                </svg>

            </div>

            <h1 class="mt-4 text-lg font-semibold text-slate-900">
                Project Draft Encoding
            </h1>

            <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">
                GIP project encoding will be handled through a controlled draft workflow.
                Draft information will require TUPAD Coordinator review before becoming an official project record.
            </p>

        </section>

    </div>

@endsection
