@if(session('success'))
    <div role="status" class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
        <div class="flex gap-3">
            <div class="mt-0.5 shrink-0">
                <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="m5 12 4 4L19 6"></path>
                </svg>
            </div>
            <div class="text-sm text-emerald-800">{{ session('success') }}</div>
        </div>
    </div>
@endif

@if(session('warning'))
    <div role="alert" class="mb-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
        <div class="flex gap-3">
            <div class="mt-0.5 shrink-0">
                <svg class="h-5 w-5 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 9v4"></path>
                    <path d="M12 17h.01"></path>
                    <path d="M10.3 3.7 2.9 16.5A2 2 0 0 0 4.6 19.5h14.8a2 2 0 0 0 1.7-3L13.7 3.7a2 2 0 0 0-3.4 0Z"></path>
                </svg>
            </div>
            <div class="text-sm text-amber-800">{{ session('warning') }}</div>
        </div>
    </div>
@endif

@if(session('error'))
    <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
        <div class="flex gap-3">
            <div class="mt-0.5 shrink-0">
                <svg class="h-5 w-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 9v4"></path>
                    <path d="M12 17h.01"></path>
                    <circle cx="12" cy="12" r="9"></circle>
                </svg>
            </div>
            <div class="text-sm text-red-800">{{ session('error') }}</div>
        </div>
    </div>
@endif

@if($errors->any())
    <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4">
        <div class="flex gap-3">
            <div class="mt-0.5 shrink-0">
                <svg class="h-5 w-5 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 9v4"></path>
                    <path d="M12 17h.01"></path>
                    <circle cx="12" cy="12" r="9"></circle>
                </svg>
            </div>

            <div>
                <div class="text-sm font-semibold text-red-800">
                    Please review the information below.
                </div>

                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
