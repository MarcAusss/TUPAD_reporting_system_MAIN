{{--
    Shared confirmation dialog (mounted once in layouts/app).
    Opened by [data-confirm] elements/forms and window.TupadConfirm() in resources/js/tupad-ui.js.
--}}
<dialog data-tupad-confirm aria-labelledby="tupad-confirm-title" aria-describedby="tupad-confirm-message"
    class="tupad-dialog w-[calc(100%-2rem)] max-w-md rounded-2xl border border-slate-200 bg-white p-0 text-left shadow-2xl">
    <div class="flex gap-4 px-5 pt-5">
        <span data-confirm-icon class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-[#063b86]" aria-hidden="true">
            <svg data-confirm-icon-primary class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg>
            <svg data-confirm-icon-danger class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <h2 id="tupad-confirm-title" data-confirm-title class="text-base font-bold text-slate-900">Please confirm</h2>
            <p id="tupad-confirm-message" data-confirm-message class="mt-1.5 whitespace-pre-line text-sm leading-6 text-slate-600"></p>
            <ul data-confirm-details class="mt-3 hidden space-y-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-700"></ul>
        </div>
    </div>
    <div class="mt-5 flex flex-col-reverse gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3 sm:flex-row sm:justify-end">
        <button type="button" data-confirm-cancel
            class="inline-flex h-10 items-center justify-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300">
            Cancel
        </button>
        <button type="button" data-confirm-accept
            class="inline-flex h-10 items-center justify-center rounded-lg bg-[#063b86] px-4 text-sm font-semibold text-white hover:bg-[#052f6b] focus:outline-none focus:ring-2 focus:ring-blue-300">
            Continue
        </button>
    </div>
</dialog>
