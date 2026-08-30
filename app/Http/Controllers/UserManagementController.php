<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Province;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private const MANAGED_EMAIL_SUFFIX = '@accounts.tupad.invalid';

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $coordinators = User::query()
            ->with('assignedProvince:id,name')
            ->where('role', UserRole::TC->value)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $search = trim($search);

                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('position', 'like', "%{$search}%");
                });
            })
            ->when($filters['province_id'] ?? null, fn ($query, $provinceId) => $query->where('assigned_province_id', $provinceId))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('users.index', [
            'coordinators' => $coordinators,
            'provinces' => $this->activeProvinces(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'provinces' => $this->activeProvinces(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCoordinator($request);
        $username = $this->normalizedUsername($data['username']);

        $coordinator = User::create([
            'name' => trim($data['name']),
            'username' => $username,
            'email' => $this->managedEmail($username),
            'position' => $this->nullableTrim($data['position'] ?? null),
            'role' => UserRole::TC,
            'is_active' => $request->boolean('is_active', true),
            'supervisor_tc_id' => null,
            'assigned_province_id' => (int) $data['assigned_province_id'],
            'password' => 'password',
        ]);

        return redirect()
            ->route('users.edit', $coordinator)
            ->with('success', 'TUPAD Coordinator account created. The default password is "password".');
    }

    public function edit(User $user): View
    {
        $coordinator = $this->managedCoordinator($user);

        return view('users.edit', [
            'coordinator' => $coordinator->load('assignedProvince:id,name'),
            'provinces' => $this->activeProvinces(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $coordinator = $this->managedCoordinator($user);
        $data = $this->validateCoordinator($request, $coordinator);
        $username = $this->normalizedUsername($data['username']);

        $coordinator->fill([
            'name' => trim($data['name']),
            'username' => $username,
            'position' => $this->nullableTrim($data['position'] ?? null),
            'assigned_province_id' => (int) $data['assigned_province_id'],
            'is_active' => $request->boolean('is_active'),
            'role' => UserRole::TC,
            'supervisor_tc_id' => null,
        ]);

        if ($this->isManagedEmail($coordinator->email)) {
            $coordinator->email = $this->managedEmail($username);
        }

        $coordinator->save();

        return redirect()
            ->route('users.edit', $coordinator)
            ->with('success', 'Coordinator account updated successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        $coordinator = $this->managedCoordinator($user);
        $coordinator->is_active = ! $coordinator->is_active;
        $coordinator->save();

        return back()->with(
            'success',
            $coordinator->is_active
                ? 'Coordinator account activated.'
                : 'Coordinator account deactivated.'
        );
    }

    public function resetPassword(User $user): RedirectResponse
    {
        $coordinator = $this->managedCoordinator($user);
        $coordinator->password = Hash::make('password');
        $coordinator->save();

        return back()->with('success', 'Password reset to the default password "password".');
    }

    private function validateCoordinator(Request $request, ?User $coordinator = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($coordinator?->id),
            ],
            'position' => ['nullable', 'string', 'max:255'],
            'assigned_province_id' => [
                'required',
                'integer',
                Rule::exists('provinces', 'id')->where(
                    fn ($query) => $query
                        ->where('is_active', true)
                        ->whereIn('code', array_keys((array) config('tupad_mapping.provinces', [])))
                ),
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'username.regex' => 'The username may contain only letters, numbers, periods, underscores, and hyphens.',
            'assigned_province_id.required' => 'Assign the TUPAD Coordinator to a province.',
        ]);
    }

    private function managedCoordinator(User $user): User
    {
        abort_unless($user->isTc(), 404);

        return $user;
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
