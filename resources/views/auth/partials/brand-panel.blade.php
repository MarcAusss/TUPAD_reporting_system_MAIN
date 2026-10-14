{{-- <------------------------------------------- Auth Brand Panel ------------------------------/> --}}
{{-- Decorative visual panel shared by the login and required password change screens. --}}

@php
    $eyebrow ??= 'TUPAD Reporting System';
    $headline ??= 'Tulong Panghanapbuhay sa Ating Disadvantaged Workers';
    $caption ??= 'Monitor projects, beneficiaries, and fund utilization in one place.';
@endphp

<div class="relative hidden h-full min-h-140 overflow-hidden rounded-[22px] bg-linear-to-br from-[#063b86] via-[#0b3170] to-[#10294f] p-8 text-white md:flex md:flex-col">

    {{-- Background shapes --}}
    <div aria-hidden="true" class="pointer-events-none absolute inset-0">
        <div class="absolute -right-24 -top-24 h-72 w-72 rounded-full bg-[#1765d8]/40 blur-3xl"></div>
        <div class="absolute -bottom-28 -left-20 h-80 w-80 rounded-full bg-[#4b8cf0]/20 blur-3xl"></div>
        <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 22px 22px;"></div>
    </div>

    {{-- Eyebrow chip --}}
    <div class="relative flex items-center justify-between">
        <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-[11px] font-semibold tracking-wide ring-1 ring-white/20 backdrop-blur">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>
            {{ $eyebrow }}
        </span>
        <span class="text-[11px] font-medium text-white/60">DOLE</span>
    </div>

    {{-- Center showcase --}}
    <div class="relative flex flex-1 items-center justify-center py-10">

        <div class="relative">
            <div class="flex h-40 w-32 items-center justify-center overflow-hidden rounded-2xl bg-white p-2 shadow-2xl shadow-black/30 ring-4 ring-white/15">
                <img src="{{ asset('images/mainlogo.jpg') }}" alt="" class="h-full w-full object-contain">
            </div>

            {{-- Floating cards --}}
            <div class="absolute -left-36 top-2 hidden w-40 lg:block rounded-xl bg-white/95 p-3 text-[#10294f] shadow-xl shadow-black/20">
                <div class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#eaf2ff] text-[#063b86]">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h12"/></svg>
                    </span>
                    <div class="text-[11px] font-bold leading-tight">Project<br>Monitoring</div>
                </div>
                <div class="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-[#e4eaf2]">
                    <div class="h-full w-3/4 rounded-full bg-[#1765d8]"></div>
                </div>
            </div>

            <div class="absolute -right-36 top-16 hidden w-36 lg:block rounded-xl bg-white/95 p-3 text-[#10294f] shadow-xl shadow-black/20">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-[#6f7f98]">Fund Status</div>
                <div class="mt-1.5 flex h-8 items-end gap-1">
                    <span class="w-full rounded-sm bg-[#ccd7e6]" style="height: 40%"></span>
                    <span class="w-full rounded-sm bg-[#ccd7e6]" style="height: 65%"></span>
                    <span class="w-full rounded-sm bg-[#1765d8]" style="height: 90%"></span>
                    <span class="w-full rounded-sm bg-[#ccd7e6]" style="height: 55%"></span>
                    <span class="w-full rounded-sm bg-[#063b86]" style="height: 75%"></span>
                </div>
            </div>

            <div class="absolute -bottom-6 -left-24 hidden items-center lg:flex gap-2 rounded-full bg-white/95 py-1.5 pl-1.5 pr-3 text-[#10294f] shadow-xl shadow-black/20">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <span class="text-[11px] font-bold">Reports ready</span>
            </div>
        </div>

    </div>

    {{-- Caption --}}
    <div class="relative">
        <h2 class="max-w-sm text-xl font-bold leading-snug tracking-tight">
            {{ $headline }}
        </h2>
        <p class="mt-2 max-w-sm text-sm leading-6 text-white/70">
            {{ $caption }}
        </p>
    </div>

</div>  
