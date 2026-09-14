<?php

namespace Kraenkvisuell\StatamicKit\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Kraenkvisuell\StatamicKit\Database\Seeders\DemoPagesSeeder;
use Kraenkvisuell\StatamicKit\Database\Seeders\DemoPostsSeeder;
use Kraenkvisuell\StatamicKit\Database\Seeders\DemoProjectsSeeder;
use Kraenkvisuell\StatamicKit\Database\Seeders\SeoDefaultsSeeder;
use Statamic\Facades\User;

use function Laravel\Prompts\confirm;

/**
 * Sets a freshly installed site up to the point where the control panel and
 * the starter website work: the steps in `handle()` run in order (demo pages,
 * blog posts and projects, SEO Pro site defaults, user), each one idempotent, and the last one always
 * asks the person initializing the site for their own login. Add further
 * steps between the seeders and the user.
 *
 * Without options nothing is destroyed: the seeders reuse existing pages and
 * defaults and the user step asks before adding to existing users. `--force`
 * drops every table first (migrate:fresh) to start over; it refuses to run
 * outside the local and staging environments. Non-interactive runs (`--no-interaction`,
 * CI) skip the user step.
 */
#[Signature('kit:init {--force : Start over: drop all tables and migrate fresh first (local and staging only)}')]
#[Description('Set up a fresh site: seed the demo pages, posts, projects and SEO defaults, then create your user')]
class Init extends Command
{
    protected array $freshEnvironments = ['local', 'staging'];

    public function handle(): int
    {
        if ($this->option('force') && ! $this->migrateFresh()) {
            return self::FAILURE;
        }

        $this->components->info('Seeding the demo pages and navigations');
        $this->call('db:seed', ['--class' => DemoPagesSeeder::class]);

        $this->components->info('Seeding the demo blog posts and projects');
        $this->call('db:seed', ['--class' => DemoPostsSeeder::class]);
        $this->call('db:seed', ['--class' => DemoProjectsSeeder::class]);

        $this->components->info('Seeding the SEO Pro site defaults');
        $this->call('db:seed', ['--class' => SeoDefaultsSeeder::class]);

        return $this->makeUser();
    }

    /**
     * migrate:fresh drops every table of the default connection – entries,
     * trees, globals, users, everything – and runs all migrations again. Only
     * environments where that cannot hit real content are allowed.
     */
    protected function migrateFresh(): bool
    {
        $environment = $this->laravel->environment();

        if (! in_array($environment, $this->freshEnvironments, true)) {
            $this->components->error(sprintf(
                '--force drops all tables (migrate:fresh) and is limited to the %s environments; APP_ENV is "%s".',
                implode(' and ', $this->freshEnvironments),
                $environment
            ));

            return false;
        }

        $this->components->info(sprintf('Dropping all tables and migrating fresh (APP_ENV=%s)', $environment));
        $this->call('migrate:fresh');

        return true;
    }

    /**
     * `php please make:user --super` with its prompts (email, name, password),
     * so the first person on the site gets a super user. With users already
     * present it asks first, so a re-run of kit:init does not force a new one.
     */
    protected function makeUser(): int
    {
        if (! $this->input->isInteractive()) {
            $this->components->warn('No user created (non-interactive). Run `php please make:user --super` to add yours.');

            return self::SUCCESS;
        }

        if (User::all()->isNotEmpty() && ! confirm('Users exist already. Create another one?', false)) {
            return self::SUCCESS;
        }

        $this->components->info('Your user for the control panel');

        return $this->call('statamic:make:user', ['--super' => true]);
    }
}
