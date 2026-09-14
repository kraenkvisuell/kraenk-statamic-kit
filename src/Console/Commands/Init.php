<?php

namespace Kraenkvisuell\StatamicKit\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Kraenkvisuell\StatamicKit\Database\Seeders\DemoPagesSeeder;
use Kraenkvisuell\StatamicKit\Database\Seeders\DemoPostsSeeder;
use Kraenkvisuell\StatamicKit\Database\Seeders\DemoProjectsSeeder;
use Kraenkvisuell\StatamicKit\Database\Seeders\SeoDefaultsSeeder;
use Kraenkvisuell\StatamicKit\Database\Seeders\TestUserSeeder;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

use function Laravel\Prompts\confirm;

/**
 * Sets a freshly installed site up to the point where the control panel and
 * the starter website work: the steps in `handle()` run in order (sites,
 * demo pages, blog posts and projects, SEO Pro site defaults, test user),
 * each one idempotent; add further steps at the end. The test user
 * (test@kraenk.de / password, super) is only seeded in the local and staging
 * environments; elsewhere add your login with `php please make:user --super`.
 *
 * The kit ships two sites: `default` (German) at `/` with its collection
 * routes under `/de/…`, so only the start page is `/`, and `en` at `/en`.
 * The default site keeps Statamic's handle so a single-language site of any
 * language needs no rename. The first step asks whether the site needs both;
 * a single-language site keeps the default site alone, drops the language
 * prefix from its routes (sites, collections, globals, `multisite` in
 * config/statamic/system.php) and loses the language switch in the navi. `--multisite` / `--single-site`
 * answer that question up front (CI, scripts); without either, a
 * non-interactive run keeps the sites as they are.
 *
 * The command refuses to run in production altogether: demo content, lorem
 * ipsum defaults and a test login have no place there. Without options
 * nothing is destroyed: the seeders reuse existing pages, defaults and users.
 * `--force` drops every table first (migrate:fresh) to start over; it is
 * limited further, to the local and staging environments.
 */
#[Signature('kit:init
    {--force : Start over: drop all tables and migrate fresh first (local and staging only)}
    {--multisite : Keep both sites (default at / with /de/… routes, en at /en) without asking}
    {--single-site : Reduce the site to the default site without language prefix, without asking}')]
#[Description('Set up a fresh site: choose single- or multisite, seed the demo pages, posts, projects, SEO defaults and the test user')]
class Init extends Command
{
    protected array $freshEnvironments = ['local', 'staging'];

    protected string $naviPartial = 'views/partials/navi.antlers.html';

    protected string $languageSwitchPartial = 'views/partials/navi/language-switch.antlers.html';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->components->error('kit:init sets up demo content and a test login and does not run in production (APP_ENV=production).');

            return self::FAILURE;
        }

        if ($this->option('force') && ! $this->migrateFresh()) {
            return self::FAILURE;
        }

        if ($this->option('multisite') && $this->option('single-site')) {
            $this->components->error('--multisite and --single-site exclude each other.');

            return self::FAILURE;
        }

        $this->configureSites();

        $this->components->info('Seeding the demo pages and navigations');
        $this->call('db:seed', ['--class' => DemoPagesSeeder::class]);

        $this->components->info('Seeding the demo blog posts and projects');
        $this->call('db:seed', ['--class' => DemoPostsSeeder::class]);
        $this->call('db:seed', ['--class' => DemoProjectsSeeder::class]);

        $this->components->info('Seeding the SEO Pro site defaults');
        $this->call('db:seed', ['--class' => SeoDefaultsSeeder::class]);

        $this->components->info('Seeding the test user');
        $this->call('db:seed', ['--class' => TestUserSeeder::class]);

        return self::SUCCESS;
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
     * Single- or multisite. With one site left there is nothing to decide;
     * otherwise the options answer, then the prompt, and a non-interactive
     * run without options keeps the sites as they are.
     */
    protected function configureSites(): void
    {
        if (Site::all()->count() < 2) {
            $this->components->info(sprintf('Single site: %s at %s.', Site::default()->handle(), Site::default()->url()));

            return;
        }

        $sites = Site::all()->map(fn ($site) => sprintf('%s (%s) at %s', $site->handle(), $site->name(), $site->url()))->join(', ');
        $prefix = '/'.Site::default()->shortLocale();

        $multisite = match (true) {
            (bool) $this->option('multisite') => true,
            (bool) $this->option('single-site') => false,
            ! $this->input->isInteractive() => true,
            default => confirm(
                label: 'Will the site have more than one language?',
                default: true,
                hint: "Yes keeps both sites: {$sites}, pages of the default site under {$prefix}/…. No keeps ".Site::default()->handle().' alone, without prefix.',
            ),
        };

        if (! $multisite) {
            $this->makeSingleSite();

            return;
        }

        $this->components->info("Multisite: {$sites}.");
        $this->line("  The start page of the default site is /, its other pages live under {$prefix}/…; {$prefix} redirects to /.");
        $this->line('  Every entry, tree and global has one version per site; the CP switches between them at the top');
        $this->line('  and everything else works like in any Statamic site.');
    }

    /**
     * Keep the default site alone at `/` without the language prefix in its
     * routes: sites.yaml, the collections' routes and site lists, the global
     * sets' site lists, `multisite` off in config/statamic/system.php, and
     * the language switch out of the navi. Everything is file-based, so this
     * is a one-time edit of the site's own files.
     */
    protected function makeSingleSite(): void
    {
        $default = Site::default();
        $handle = $default->handle();
        $removed = Site::all()->keys()->reject(fn ($h) => $h === $handle)->join(', ');

        Site::setSites([$handle => [...$default->rawConfig(), 'url' => '/']])->save();

        $prefix = '/'.$default->shortLocale();
        Collection::all()->each(function ($collection) use ($handle, $prefix) {
            // '/de/blog/{slug}' -> '/blog/{slug}'; the pages route '{{ is_root ? "" : "/de" }}/{{ slug }}' -> '/{{ slug }}'
            if (is_string($route = $collection->route($handle))) {
                $collection->routes(Str::replace(['{{ is_root ? "" : "'.$prefix.'" }}', $prefix.'/'], ['', '/'], $route));
            }

            $collection->sites([$handle])->save();
        });
        GlobalSet::all()->each(fn ($set) => $set->sites([$handle])->save());

        $config = config_path('statamic/system.php');
        File::put($config, str_replace("'multisite' => true", "'multisite' => false", File::get($config)));
        config()->set('statamic.system.multisite', false);

        $navi = resource_path($this->naviPartial);
        if (File::exists($navi)) {
            File::put($navi, preg_replace('/^\s*\{\{\s*partial:navi\/language-switch\s*\}\}\s*\n/m', '', File::get($navi)));
        }
        File::delete(resource_path($this->languageSwitchPartial));

        $this->call('statamic:stache:clear');

        $this->components->info("Single site: {$handle} at /. Removed: {$removed}.");
        $this->line('  resources/sites.yaml, the collections and global sets list only '.$handle.", routes lost the {$prefix} prefix,");
        $this->line('  multisite is off in config/statamic/system.php, and the language switch is gone from partials/navi.');
    }
}
