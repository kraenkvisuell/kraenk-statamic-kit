<?php

namespace Kraenkvisuell\StatamicKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Statamic\Facades\User;

/**
 * A super user for testing a fresh site: test@kraenk.de / gogogoLilien1898!!!. Only in
 * the local and staging environments – a known login must never reach a
 * production site. Idempotent: an existing user with that email is left alone.
 *
 *   php artisan db:seed --class="Kraenkvisuell\StatamicKit\Database\Seeders\TestUserSeeder"
 */
class TestUserSeeder extends Seeder
{
    protected string $email = 'test@kraenk.de';

    protected string $password = 'gogogoLilien1898!!!';

    protected array $environments = ['local', 'staging'];

    public function run(): void
    {
        if (! app()->environment($this->environments)) {
            $this->command?->warn(sprintf('Test user not seeded: APP_ENV is "%s", allowed are %s.', app()->environment(), implode(' and ', $this->environments)));

            return;
        }

        if (User::findByEmail($this->email)) {
            $this->command?->info("Test user {$this->email} exists, left as is.");

            return;
        }

        User::make()
            ->email($this->email)
            ->password($this->password)
            ->makeSuper()
            ->data(['first_name' => 'Test', 'last_name' => 'Kraenk'])
            ->save();

        $this->command?->info("Test user {$this->email} (super) created, password \"{$this->password}\".");
    }
}
