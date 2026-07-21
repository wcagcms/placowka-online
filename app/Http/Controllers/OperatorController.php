<?php

namespace App\Http\Controllers;

use App\Models\Facility;
use App\Models\User;
use App\Services\SecurityAuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class OperatorController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $status = trim((string) $request->query('status'));

        if (! in_array($status, ['', 'active', 'inactive', 'unassigned'], true)) {
            $status = '';
        }

        $baseQuery = User::query()
            ->where('role', User::ROLE_OPERATOR);

        $stats = [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('is_active', true)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
            'unassigned' => (clone $baseQuery)->doesntHave('facilities')->count(),
        ];

        $operators = (clone $baseQuery)
            ->with('facilities:id,code,name,is_active')
            ->withCount('facilities')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $pattern = '%'.$search.'%';

                    $searchQuery
                        ->where('name', 'like', $pattern)
                        ->orWhere('email', 'like', $pattern)
                        ->orWhereHas('facilities', function (Builder $facilityQuery) use ($pattern): void {
                            $facilityQuery
                                ->where('code', 'like', $pattern)
                                ->orWhere('name', 'like', $pattern);
                        });
                });
            })
            ->when($status === 'active', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query): Builder => $query->where('is_active', false))
            ->when($status === 'unassigned', fn (Builder $query): Builder => $query->doesntHave('facilities'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('operators.index', [
            'operators' => $operators,
            'stats' => $stats,
            'search' => $search,
            'selectedStatus' => $status,
        ]);
    }

    public function create(): View
    {
        return view('operators.create', [
            'operator' => new User([
                'role' => User::ROLE_OPERATOR,
                'is_active' => true,
                'must_change_password' => true,
            ]),
            'facilities' => Facility::query()->orderBy('code')->get(),
            'selectedFacilityIds' => collect(),
        ]);
    }

    public function store(Request $request, SecurityAuditLogger $audit): RedirectResponse
    {
        $validated = $this->validateOperator($request);

        $operator = DB::transaction(function () use ($validated): User {
            $operator = User::query()->create([
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_OPERATOR,
                'is_active' => (bool) ($validated['is_active'] ?? false),
                'must_change_password' => true,
            ]);

            $operator->facilities()->sync($validated['facility_ids'] ?? []);

            return $operator;
        });

        $audit->write('operator_created', $request->user(), $operator, [
            'operator_id' => $operator->id,
            'operator_email' => $operator->email,
            'facility_ids' => $validated['facility_ids'] ?? [],
            'active' => $operator->is_active,
        ], $request);

        return redirect()
            ->route('operators.edit', $operator)
            ->with('success', 'Operator został utworzony. Przy pierwszym logowaniu musi ustawić własne hasło.');
    }

    public function edit(User $operator): View
    {
        $this->ensureOperator($operator);

        return view('operators.edit', [
            'operator' => $operator->load('facilities:id,code,name'),
            'facilities' => Facility::query()->orderBy('code')->get(),
            'selectedFacilityIds' => $operator->facilities->pluck('id'),
        ]);
    }

    public function update(Request $request, User $operator, SecurityAuditLogger $audit): RedirectResponse
    {
        $this->ensureOperator($operator);

        $validated = $this->validateOperator($request, $operator);

        DB::transaction(function () use ($validated, $operator): void {
            $changes = [
                'name' => trim($validated['name']),
                'email' => mb_strtolower(trim($validated['email'])),
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ];

            $mustInvalidateSessions = ! (bool) ($validated['is_active'] ?? false)
                || ! empty($validated['password']);

            if (! empty($validated['password'])) {
                $changes['password'] = Hash::make($validated['password']);
                $changes['must_change_password'] = true;
            }

            if ($mustInvalidateSessions) {
                $changes['auth_version'] = (int) $operator->auth_version + 1;
            }

            $operator->forceFill($changes)->save();
            $operator->facilities()->sync($validated['facility_ids'] ?? []);

            if ($mustInvalidateSessions) {
                $this->invalidateSessions($operator);
            }
        });

        $audit->write('operator_updated', $request->user(), $operator, [
            'operator_id' => $operator->id,
            'operator_email' => $operator->email,
            'facility_ids' => $validated['facility_ids'] ?? [],
            'active' => $operator->is_active,
        ], $request);

        return redirect()
            ->route('operators.edit', $operator)
            ->with('success', 'Dane operatora i przypisanie placówek zostały zapisane.');
    }

    private function validateOperator(Request $request, ?User $operator = null): array
    {
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($operator?->id),
            ],
            'password' => [
                $operator ? 'nullable' : 'required',
                'confirmed',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
                'max:72',
            ],
            'is_active' => ['nullable', 'boolean'],
            'facility_ids' => ['nullable', 'array'],
            'facility_ids.*' => ['integer', 'distinct', Rule::exists('facilities', 'id')],
        ], [
            'name.required' => 'Podaj imię i nazwisko operatora.',
            'name.max' => 'Imię i nazwisko może mieć maksymalnie 255 znaków.',
            'email.required' => 'Podaj adres e-mail operatora.',
            'email.email' => 'Podaj poprawny adres e-mail.',
            'email.max' => 'Adres e-mail może mieć maksymalnie 255 znaków.',
            'email.unique' => 'Konto z tym adresem e-mail już istnieje. Użyj innego adresu albo edytuj istniejące konto.',
            'password.required' => 'Podaj hasło tymczasowe dla operatora.',
            'password.confirmed' => 'Wpisane hasła nie są identyczne.',
            'password.min' => 'Hasło musi mieć co najmniej 12 znaków.',
            'password.max' => 'Hasło może mieć maksymalnie 72 znaki.',
            'password.letters' => 'Hasło musi zawierać co najmniej jedną literę.',
            'password.mixed' => 'Hasło musi zawierać małą i wielką literę.',
            'password.numbers' => 'Hasło musi zawierać co najmniej jedną cyfrę.',
            'password.symbols' => 'Hasło musi zawierać co najmniej jeden znak specjalny.',
            'is_active.boolean' => 'Nieprawidłowa wartość statusu konta.',
            'facility_ids.array' => 'Nieprawidłowa lista placówek.',
            'facility_ids.*.integer' => 'Nieprawidłowy identyfikator placówki.',
            'facility_ids.*.distinct' => 'Ta sama placówka została wybrana więcej niż raz.',
            'facility_ids.*.exists' => 'Jedna z wybranych placówek nie istnieje lub została usunięta.',
        ]);
    }

    private function ensureOperator(User $operator): void
    {
        abort_unless($operator->role === User::ROLE_OPERATOR, 404);
    }

    private function invalidateSessions(User $operator): void
    {
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->where('user_id', $operator->id)->delete();
        }
    }
}
