<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePanelAdmin extends Command
{
    protected $signature = 'placowka:create-admin
        {email? : Adres e-mail administratora}
        {--name= : Imię i nazwisko}
        {--force : Zaktualizuj istniejące konto o tym adresie}';

    protected $description = 'Tworzy lub aktualizuje konto administratora panelu Placówka Online.';

    public function handle(): int
    {
        if (! $this->argument('email') && User::query()->where('role', User::ROLE_ADMIN)->exists()) {
            $this->info('Konto administratora już istnieje. Podaj e-mail i --force, aby je zaktualizować.');

            return self::SUCCESS;
        }

        $email = mb_strtolower(trim((string) ($this->argument('email') ?: $this->ask('Adres e-mail administratora'))));
        $name = trim((string) ($this->option('name') ?: $this->ask('Imię i nazwisko', 'Administrator')));
        $password = (string) $this->secret('Hasło administratora');
        $confirmation = (string) $this->secret('Powtórz hasło');

        if (! hash_equals($password, $confirmation)) {
            $this->error('Hasła nie są takie same.');

            return self::FAILURE;
        }

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => [
                'required',
                Password::min(12)->letters()->mixedCase()->numbers()->symbols(),
                'max:72',
            ],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing && ! $this->option('force')) {
            $this->error('Konto o tym adresie już istnieje. Użyj --force, aby je zaktualizować.');

            return self::FAILURE;
        }

        $admin = $existing ?: new User();
        $admin->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'must_change_password' => false,
            'auth_version' => (int) ($admin->auth_version ?: 0) + 1,
        ])->save();

        $admin->facilities()->detach();

        $this->info('Konto administratora zostało zapisane: '.$admin->email);

        return self::SUCCESS;
    }
}
