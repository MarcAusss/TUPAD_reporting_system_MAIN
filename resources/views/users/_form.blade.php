@php
    $editing = isset($account) && $account;
    $selectedRole = old('role', $account?->role?->value ?? ($canManageAllRoles ? '' : \App\Enums\UserRole::TC->value));
    $selectedProvince = old('assigned_province_id', $account?->assigned_province_id ?? '');
@endphp

<div class="grid gap-5 lg:grid-cols-2">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700">Full Name <span class="text-red-600">*</span></label>
        <input id="name" name="name" type="text" maxlength="255" required value="{{ old('name', $account?->name ?? '') }}"
            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
        @error('name')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="username" class="mb-1.5 block text-sm font-semibold text-slate-700">Username <span class="text-red-600">*</span></label>
        <input id="username" name="username" type="text" maxlength="50" required autocomplete="off" value="{{ old('username', $account?->username ?? '') }}"
            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
        <p class="mt-1.5 text-xs text-slate-500">Letters, numbers, periods, underscores, and hyphens only.</p>
        @error('username')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="position" class="mb-1.5 block text-sm font-semibold text-slate-700">Position / Designation</label>
        <input id="position" name="position" type="text" maxlength="255" value="{{ old('position', $account?->position ?? (!$canManageAllRoles ? 'TUPAD Coordinator' : '')) }}"
            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
        @error('position')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="role" class="mb-1.5 block text-sm font-semibold text-slate-700">Account Role <span class="text-red-600">*</span></label>
        @if ($canManageAllRoles)
            <select id="role" name="role" required @disabled($isSelfAccount)
                class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100 disabled:text-slate-500">
                <option value="">Select role</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->value }}" @selected($selectedRole === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
            @if ($isSelfAccount)
                <input type="hidden" name="role" value="{{ $account->role->value }}">
                <p class="mt-1.5 text-xs text-slate-500">Your own Administrator role cannot be changed here.</p>
            @endif
        @else
            <input type="hidden" id="role" name="role" value="{{ \App\Enums\UserRole::TC->value }}">
            <div class="flex h-11 items-center rounded-lg border border-slate-200 bg-slate-50 px-3.5 text-sm font-semibold text-slate-800">TUPAD Coordinator</div>
            <p class="mt-1.5 text-xs text-slate-500">Focal accounts can manage TUPAD Coordinator accounts only.</p>
        @endif
        @error('role')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>

    <div data-user-role-field="tc" class="{{ $selectedRole === \App\Enums\UserRole::TC->value || !$canManageAllRoles ? '' : 'hidden' }}">
        <label for="assigned_province_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Assigned Province <span class="text-red-600">*</span></label>
        <select id="assigned_province_id" name="assigned_province_id"
            class="h-11 w-full rounded-lg border border-slate-300 bg-white px-3.5 text-sm text-slate-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            <option value="">Select province</option>
            @foreach ($provinces as $province)
                <option value="{{ $province->id }}" @selected((string) $selectedProvince === (string) $province->id)>{{ $province->name }}</option>
            @endforeach
        </select>
        <p class="mt-1.5 text-xs text-slate-500">TC access is restricted to this Region V province.</p>
        @error('assigned_province_id')<p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-5 grid gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
    <div>
        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Role Assignment</div>
        <div class="mt-1 text-sm font-semibold text-slate-900">Server validated</div>
        <p class="mt-1 text-xs leading-5 text-slate-500">Administrator and Focal accounts have regional access. TUPAD Coordinator accounts require an active Region V province assignment.</p>
    </div>
    <div>
        <div class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $editing ? 'Password Management' : 'Initial Password' }}</div>
        <div class="mt-1 text-sm font-semibold text-slate-900">{{ $editing ? 'Reset on demand' : 'Generated after creation' }}</div>
        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $editing ? 'Editing this account does not change its password. Reset Password generates a new one-time credential and forces a password change.' : 'The generated temporary password is shown once after creation and is never stored in plaintext.' }}</p>
    </div>
</div>

<label class="mt-5 flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-4 {{ $isSelfAccount ? 'opacity-70' : '' }}">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $account?->is_active ?? true)) @disabled($isSelfAccount)
        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-500">
    @if ($isSelfAccount)<input type="hidden" name="is_active" value="1">@endif
    <span>
        <span class="block text-sm font-semibold text-slate-800">Active account</span>
        <span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ $isSelfAccount ? 'Your currently signed-in Administrator account cannot be deactivated here.' : 'Inactive accounts cannot sign in to the TUPAD Reporting System.' }}</span>
    </span>
</label>

@if ($canManageAllRoles)
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const roleSelect = document.getElementById('role');
        const provinceField = document.querySelector('[data-user-role-field="tc"]');
        const provinceSelect = document.getElementById('assigned_province_id');
        function syncRoleFields() {
            const isTc = roleSelect?.value === 'tc';
            provinceField?.classList.toggle('hidden', !isTc);
            if (provinceSelect) provinceSelect.required = isTc;
        }
        roleSelect?.addEventListener('change', syncRoleFields);
        syncRoleFields();
    });
</script>
@endif
