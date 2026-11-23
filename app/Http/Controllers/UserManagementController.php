<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use App\Services\Auth\TemporaryPasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const MANAGED_EMAIL_SUFFIX = '@accounts.tupad.invalid';

    public function index(Request $request): View
    {
        $actor = $request->user();
        $canManageAllRoles = $actor->isAdmin();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in($canManageAllRoles ? $this->roleChoices() : $this->focalRoleChoices())],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $accounts = User::query()
            ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::assignable()))
            ->with('assignedProvince:id,name,code')
            ->when(! $canManageAllRoles, fn ($query) => $this->scopeFocalManageable($query)->whereKeyNot($actor->id))
            ->when(filled($filters['role'] ?? null), fn ($query) => $this->applyRoleChoiceFilter($query, $filters['role']))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $search = trim($search);
                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($filters['province_id'] ?? null, fn ($query, $provinceId) => $query->where('assigned_province_id', $provinceId))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'focal' THEN 2 WHEN 'tc' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $roleCounts = collect();
        if ($canManageAllRoles) {
            $roleCounts = User::query()
                ->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::assignable()))
                ->selectRaw('role, has_focal_access, COUNT(*) as total')
                ->groupBy('role', 'has_focal_access')
                ->get()
                ->reduce(function ($counts, $row) {
                    $role = $row->role instanceof UserRole ? $row->role->value : (string) $row->role;
                    $key = $role === UserRole::FOCAL->value && (bool) $row->has_focal_access
                        ? User::COORDINATOR_FOCAL_ACCESS_CHOICE
                        : $role;

                    return $counts->put($key, (int) $counts->get($key, 0) + (int) $row->total);
                }, collect());
        }

        return view('users.index', [
            'accounts' => $accounts,
            'provinces' => $this->activeProvinces(),
            'roles' => UserRole::assignable(),
            'filters' => $filters,
            'canManageAllRoles' => $canManageAllRoles,
            'roleCounts' => $roleCounts,
        ]);
    }

    public function create(Request $request): View
    {
        return view('users.create', $this->formData($request->user()));
    }

    public function store(Request $request, TemporaryPasswordGenerator $passwords): RedirectResponse
    {
        $actor = $request->user();
        $role = $this->requestedRole($request, $actor);
        $data = $this->validateAccount($request, $role);
        $username = $this->normalizedUsername($data['username']);
        $temporaryPassword = $passwords->generate();

        $account = new User([
            'name' => trim($data['name']),
            'username' => $username,
            'email' => $this->managedEmail($username),
            'position' => $this->nullableTrim($data['position'] ?? null),
            'role' => $role,
            'is_active' => $request->boolean('is_active', true),
            'assigned_province_id' => $role === UserRole::TC ? (int) $data['assigned_province_id'] : null,
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);
        $account->forceFill(['has_focal_access' => $this->requestedFocalAccess($request, $actor, $role)])->save();

        return redirect()
            ->route('users.edit', $account)
            ->with('success', $account->roleLabel().' account created. Copy the temporary password now; it will not be shown again after this request.')
            ->with('temporary_password', $temporaryPassword)
            ->with('temporary_password_username', $account->username);
    }

    public function edit(Request $request, User $user): View
    {
        $actor = $request->user();
        $account = $this->managedAccount($actor, $user);

        return view('users.edit', array_merge(
            $this->formData($actor, $account),
            ['account' => $account->load('assignedProvince:id,name')],
        ));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $account = $this->managedAccount($actor, $user);
        $role = $this->requestedRole($request, $actor, $account);

        if ($actor->is($account) && $role !== UserRole::ADMIN) {
            throw ValidationException::withMessages([
                'role' => 'You cannot change your own Administrator role from User Administration.',
            ]);
        }

        if ($actor->is($account) && ! $request->boolean('is_active')) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own signed-in account.',
            ]);
        }

        $data = $this->validateAccount($request, $role, $account);
        $username = $this->normalizedUsername($data['username']);

        $account->fill([
            'name' => trim($data['name']),
            'username' => $username,
            'position' => $this->nullableTrim($data['position'] ?? null),
            'assigned_province_id' => $role === UserRole::TC ? (int) $data['assigned_province_id'] : null,
            'is_active' => $actor->is($account) ? true : $request->boolean('is_active'),
            'role' => $role,
        ]);
        $account->forceFill(['has_focal_access' => $this->requestedFocalAccess($request, $actor, $role, $account)]);

        if ($this->isManagedEmail($account->email)) {
            $account->email = $this->managedEmail($username);
        }

        $account->save();

        return redirect()->route('users.edit', $account)->with('success', 'User account updated successfully.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $account = $this->managedAccount($actor, $user);

        abort_if($actor->is($account), 403, 'You cannot deactivate your own signed-in account.');

        $account->is_active = ! $account->is_active;
        $account->save();

        return back()->with(
            'success',
            $account->is_active
                ? $account->roleLabel().' account activated.'
                : $account->roleLabel().' account deactivated.'
        );
    }

    public function resetPassword(Request $request, User $user, TemporaryPasswordGenerator $passwords): RedirectResponse
    {
        $actor = $request->user();
        $account = $this->managedAccount($actor, $user);

        abort_if($actor->is($account), 403, 'Use My Account to change your own password.');

        $temporaryPassword = $passwords->generate();

        $account->forceFill([
            'password' => $temporaryPassword,
            'must_change_password' => true,
            'password_changed_at' => null,
            'remember_token' => Str::random(60),
        ])->save();

        return back()
            ->with('success', 'Password reset. Copy the temporary password now; the user must replace it at the next sign-in.')
            ->with('temporary_password', $temporaryPassword)
            ->with('temporary_password_username', $account->username);
    }

    private function validateAccount(Request $request, UserRole $role, ?User $account = null): array
    {
        $provinceRules = ['nullable'];

        if ($role === UserRole::TC) {
            $provinceRules = [
                'required',
                'integer',
                Rule::exists('provinces', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->whereIn('code', array_keys((array) config('tupad_mapping.provinces', [])))
                ),
            ];
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($account?->id),
            ],
            'position' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in($this->roleChoices())],
            'assigned_province_id' => $provinceRules,
            'is_active' => ['nullable', 'boolean'],
        ], [
            'username.regex' => 'The username may contain only letters, numbers, periods, underscores, and hyphens.',
            'assigned_province_id.required' => 'Assign the TUPAD Coordinator to a province.',
        ]);
    }

    private function requestedRole(Request $request, User $actor, ?User $account = null): UserRole
    {
        if (! $actor->isAdmin()) {
            // Focal manages TUPAD Coordinators (plain, province-scoped, or with
            // Focal access) and GSIS Enrollment Officers. Any other submitted role keeps the account's
            // current type, and a new account defaults to a plain TC.
            $value = $request->input('role');

            if ($value === User::COORDINATOR_FOCAL_ACCESS_CHOICE) {
                return UserRole::FOCAL;
            }

            if ($value === UserRole::GSIS->value) {
                return UserRole::GSIS;
            }

            if ($value === UserRole::TC->value || $account === null) {
                return UserRole::TC;
            }

            return match (true) {
                $account->isCoordinatorWithFocalAccess() => UserRole::FOCAL,
                $account->isGsis() => UserRole::GSIS,
                default => UserRole::TC,
            };
        }

        $value = $request->input('role', $account?->roleChoice());
        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages(['role' => 'Select an account role.']);
        }

        // A TUPAD Coordinator with Focal access is a Focal-role account.
        if ($value === User::COORDINATOR_FOCAL_ACCESS_CHOICE) {
            return UserRole::FOCAL;
        }

        $role = UserRole::tryFrom($value);
        if (! $role || ! in_array($role, UserRole::assignable(), true)) {
            throw ValidationException::withMessages(['role' => 'Select a valid account role.']);
        }

        return $role;
    }

    /**
     * Only an Administrator can grant the Focal access level. A Focal account
     * keeps its current label when the request does not name the role.
     */
    private function requestedFocalAccess(Request $request, User $actor, UserRole $role, ?User $account = null): bool
    {
        if ($role !== UserRole::FOCAL) {
            return false;
        }

        // For Focal, requestedRole() only returns FOCAL for a Coordinator with
        // Focal access, so the flag always stays on.
        if (! $actor->isAdmin()) {
            return true;
        }

        $value = $request->input('role', $account?->roleChoice());

        return $value === User::COORDINATOR_FOCAL_ACCESS_CHOICE;
    }

    /** @return array<int, string> */
    private function roleChoices(): array
    {
        return [
            ...array_map(fn (UserRole $role) => $role->value, UserRole::assignable()),
            User::COORDINATOR_FOCAL_ACCESS_CHOICE,
        ];
    }

    private function applyRoleChoiceFilter($query, string $choice)
    {
        return match ($choice) {
            User::COORDINATOR_FOCAL_ACCESS_CHOICE => $query
                ->where('role', UserRole::FOCAL->value)
                ->where('has_focal_access', true),
            UserRole::FOCAL->value => $query
                ->where('role', UserRole::FOCAL->value)
                ->where('has_focal_access', false),
            default => $query->where('role', $choice),
        };
    }

    /** Accounts a Focal user manages: TUPAD Coordinators (with or without Focal access) and GSIS Enrollment Officers. */
    private function scopeFocalManageable($query)
    {
        return $query->where(function ($accounts): void {
            $accounts
                ->whereIn('role', [UserRole::TC->value, UserRole::GSIS->value])
                ->orWhere(fn ($focalAccess) => $focalAccess
                    ->where('role', UserRole::FOCAL->value)
                    ->where('has_focal_access', true));
        });
    }

    /** @return array<int, string> */
    private function focalRoleChoices(): array
    {
        return [UserRole::TC->value, User::COORDINATOR_FOCAL_ACCESS_CHOICE, UserRole::GSIS->value];
    }

    private function managedAccount(User $actor, User $user): User
    {
        if ($actor->isAdmin()) {
            abort_if($user->role === UserRole::RETIRED, 404);
            return $user;
        }

        abort_unless($user->isTc() || $user->isCoordinatorWithFocalAccess() || $user->isGsis(), 404);

        // Focal-level accounts change their own details from My Account.
        abort_if($actor->is($user), 404);

        return $user;
    }

    private function formData(User $actor, ?User $account = null): array
    {
        return [
            'account' => $account,
            'provinces' => $this->activeProvinces(),
            'roles' => UserRole::assignable(),
            'canManageAllRoles' => $actor->isAdmin(),
            'isSelfAccount' => $account ? $actor->is($account) : false,
        ];
    }

    private function activeProvinces()
    {
        return Province::query()
            ->where('is_active', true)
            ->whereIn('code', array_keys((array) config('tupad_mapping.provinces', [])))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    private function normalizedUsername(string $username): string
    {
        return strtolower(trim($username));
    }

    private function managedEmail(string $username): string
    {
        return $username.self::MANAGED_EMAIL_SUFFIX;
    }

    private function isManagedEmail(?string $email): bool
    {
        return is_string($email) && str_ends_with(strtolower($email), self::MANAGED_EMAIL_SUFFIX);
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
