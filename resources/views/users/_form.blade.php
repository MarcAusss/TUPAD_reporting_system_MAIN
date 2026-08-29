@php
    $editing = isset($coordinator);
@endphp

<div class="grid gap-5 lg:grid-cols-2">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700">Full Name <span class="text-red-600">*</span></label>
        <input id="name" name="name" type="text" maxlength="255" required
            value="{{ old('name', $coordinator->name ?? '') }}"
            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
        @error('name')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="username" class="mb-1.5 block text-sm font-semibold text-slate-700">Username <span class="text-red-600">*</span></label>
        <input id="username" name="username" type="text" maxlength="50" required autocomplete="off"
            value="{{ old('username', $coordinator->username ?? '') }}"
            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
        <p class="mt-1.5 text-xs text-slate-500">Letters, numbers, periods, underscores, and hyphens only.</p>
        @error('username')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="assigned_province_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Assigned Province <span class="text-red-600">*</span></label>
        <select id="assigned_province_id" name="assigned_province_id" required
            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            <option value="">Select province</option>
            @foreach ($provinces as $province)
                <option value="{{ $province->id }}" @selected((string) old('assigned_province_id', $coordinator->assigned_province_id ?? '') === (string) $province->id)>
                    {{ $province->name }}
                </option>
            @endforeach
        </select>
        @error('assigned_province_id')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="position" class="mb-1.5 block text-sm font-semibold text-slate-700">Position / Designation</label>
        <input id="position" name="position" type="text" maxlength="255"
            value="{{ old('position', $coordinator->position ?? 'TUPAD Coordinator') }}"
            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
        @error('position')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
    <div>
        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Account Role</div>
        <div class="mt-1 text-sm font-semibold text-slate-900">TUPAD Coordinator</div>
        <p class="mt-1 text-xs leading-5 text-slate-500">The role is fixed by the server and cannot be changed from this form.</p>
    </div>
    <div>
        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $editing ? 'Password Management' : 'Initial Password' }}</div>
        <div class="mt-1 font-mono text-sm font-bold text-slate-900">password</div>
        <p class="mt-1 text-xs leading-5 text-slate-500">
            {{ $editing ? 'Editing this account does not change its password. Use Reset Password when needed.' : 'The account is created with the default password exactly as shown.' }}
        </p>
    </div>
</div>

<label class="mt-5 flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $coordinator->is_active ?? true))
        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-500">
    <span>
        <span class="block text-sm font-semibold text-slate-800">Active account</span>
        <span class="mt-0.5 block text-xs leading-5 text-slate-500">Inactive accounts cannot sign in to the TUPAD Reporting System.</span>
    </span>
</label>
