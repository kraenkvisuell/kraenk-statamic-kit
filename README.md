# kraenkvisuell/kraenk-statamic-kit

Updatable [Statamic starter kit](https://statamic.dev/starter-kits/creating-a-starter-kit) for kraenkvisuell's eloquent-driven Statamic 6 sites. It carries the setup every site shares – content in the database (statamic/eloquent-driver, Postgres, uuid ids), asset container and Glide cache on Bunny Storage behind Bunny CDN, static caching, Horizon, Livewire-shipped Alpine, SEO Pro – the PHP that goes with it, and a complete theme as the starting point: the vollbild.film site (blueprints, fieldsets, Antlers views, Tailwind CSS, Alpine JS), to be made generic step by step. The conventions themselves live in `~/Code/coding-guidelines`.

**This repo is generated.** Its source of truth is the `package/` folder of a sandbox site plus that site's files; `php please starter-kit:export` writes this repo (see "Working on the kit"). Don't edit files here by hand – they are overwritten by the next export. The exception is `.github/`, which lives only here.

## Two kinds of content

**Package code (`src/`)** stays a Composer dependency in every site (`updatable: true`) and is updated with `composer update kraenkvisuell/kraenk-statamic-kit`. Statamic autoloads it through the `ServiceProvider` (an `AddonServiceProvider`, namespace `Kraenkvisuell\StatamicKit`):

- `Console/Commands/` – `kit:init` (sets a fresh site up: asks whether the site is multisite (`de` at /de and `en` at /en, the kit's default) or single-site (`de` alone at /: rewrites `resources/sites.yaml`, the collections' and global sets' site lists, turns `multisite` off in `config/statamic/system.php` and removes `partials/navi/language-switch`; `--multisite` / `--single-site` skip the prompt), runs the demo pages, posts, projects and SEO defaults seeders, then `make:user --super` with its prompts for the person initializing the site; non-destructive and repeatable, more steps go in between; `--force` runs `migrate:fresh` first and only works with `APP_ENV` local or staging), `kit:copy-assets-to-bunny` (`--from`, `--to`, `--dry-run`, `--force`), `kit:fix-bard-list-items` (`--dry-run`), `kit:reset-postgres-keys` (`--dry-run`). Listed by `php artisan list`; `php please list` shows only `statamic:` commands.
- The `kit:` prefix plus the kebab-case class name is the convention for every command.
- `Modifiers/` – `ensure_url`, `file_size` (locale-aware via `Number::fileSize`).
- `Database/Seeders/DemoPagesSeeder` – demo content for a fresh site: start page, five main pages, an area with three sub pages, footer pages Impressum/Datenschutz/Kontakt, localized into every site, placed in the `pages` tree and the `main`/`footer` navigations; the start page gets the `projects` and `blog` listing sets (added when missing). Idempotent: `php artisan db:seed --class="Kraenkvisuell\StatamicKit\Database\Seeders\DemoPagesSeeder"`.
- `Database/Seeders/DemoPostsSeeder`, `DemoProjectsSeeder` – three lorem ipsum blog posts (dated 30, 20 and 10 days back, one-sentence teaser) and three projects (placed in the projects tree), one `text_image` set each, localized into every site. Idempotent like the pages seeder; `DemoSeeder` is the abstract base with the shared entry, lorem and tree helpers.
- `Database/Seeders/SeoDefaultsSeeder` – SEO Pro site defaults for a fresh site: lorem ipsum site name and description as placeholders, `@seo:title` and `@seo:permalink` as sources, the other sites inheriting from the default site, everything else empty. Only runs when no defaults are stored yet.
- `StaticCaching/Invalidator` – static cache invalidation along the site's content graph (listing pages, referencing entries, term carriers; globals, navigations and assets flush everything). Bound by the exported `config/statamic/static_caching.php`, which also holds the graph under `invalidation.content_graph`.
- `ServiceProvider` also redirects `/` to the default site while no site lives at `/` (multisite keeps every site under its prefix, `/de` and `/en`).
- `Http/Middleware/UseCdnClientIp` – takes the visitor's IP from Bunny's `X-Real-IP`. Not registered automatically: the site's `bootstrap/app.php` has to prepend it (see below), because it must run before `TrustProxies`.
- `ServiceProvider::bootNumberLocale()` – `Number::useLocale()` follows the site's locale (`LocaleUpdated`), so "210,6 KB" on `/` and "210.6 KB" on `/en`.

**Exported files (`export/`, listed under `export_paths` in `starter-kit.yaml`)** are copied into the new site once and are the site's own from then on:

- Infrastructure: `config/app.php` (locale `de`, fallback `en`), eloquent-driver and users config (eloquent entries, trees, globals, terms, users and addon settings, so SEO Pro site defaults live in the database too; everything else file-based), `config/statamic/seo-pro.php` (redirects and 404 errors on the database driver), the migrations (uuid entries, uuid users with the blueprint columns, auth tables, addon settings, SEO Pro redirects and errors), `app/Models/User.php` (HasUuids), `config/filesystems.php` with the `bunny-assets` and `bunny-glide-cache` disks, `config/statamic/assets.php` (`GLIDE_CACHE_DISK`), `config/statamic/static_caching.php` (binds the kit's invalidator and describes the theme's content graph under `invalidation.content_graph`), `config/horizon.php` (256 MB) + `HorizonServiceProvider` + `bootstrap/providers.php`, `.env.example` with all keys, `.npmrc`, `.bloom/` for Bloom workspaces.
- Content model: the collection, global-set and asset-container definitions of the theme (`content/**/*.yaml`; entries, trees and global variables live in the database and are not part of the kit).
- Theme: `resources/` (blueprints, fieldsets, forms, views, css, js, roles, `sites.yaml`, SEO Pro settings, macros), `lang/`, `public/images/`, and the Vite build (`vite.config.js`, `package.json`, `package-lock.json`; entries `resources/css/site.css`, `resources/js/site.js`, `resources/js/gallery.js`).

`dependencies` are required into the site's `composer.json` at the versions the sandbox has (eloquent-driver, seo-pro, horizon, flysystem-aws-s3-v3, livewire, statamic-livewire; dev: pint, debugbar, error-solutions). The `auto_alt_text` module (default yes) adds el-schneider/statamic-auto-alt-text and its config.

## Installing into a new site

```bash
statamic new my-site kraenkvisuell/kraenk-statamic-kit
# or, in an existing skeleton:
php please starter-kit:install kraenkvisuell/kraenk-statamic-kit
```

The package is on Packagist (`kraenkvisuell/kraenk-statamic-kit`), so a plain `composer require` works and no `repositories` entry is needed. Afterwards (also printed by the post-install hook):

1. `.env`: `DB_CONNECTION=pgsql` + credentials, `QUEUE_CONNECTION=redis`, `BUNNY_S3_*`, `BUNNY_PUBLIC_URL`, `GLIDE_CACHE_DISK=bunny-glide-cache`, `STATAMIC_PRO_ENABLED=true` (the theme is multi-site de/en, see `resources/sites.yaml`).
2. `php artisan migrate`, then `php artisan kit:init` (single- or multisite, demo pages and navigations, blog posts and projects, SEO Pro site defaults, then your super user).
3. `npm install && npm run build` (or `npm run dev`).
4. Behind Bunny CDN, in `bootstrap/app.php`:

   ```php
   use Illuminate\Http\Request;
   use Kraenkvisuell\StatamicKit\Http\Middleware\UseCdnClientIp;

   ->withMiddleware(function (Middleware $middleware): void {
       $middleware->prepend(UseCdnClientIp::class);
       $middleware->trustProxies(
           at: '*',
           headers: Request::HEADER_X_FORWARDED_HOST
               | Request::HEADER_X_FORWARDED_PORT
               | Request::HEADER_X_FORWARDED_PROTO,
       );
   })
   ```

5. Point the site's `CLAUDE.md` at `@~/Code/coding-guidelines/CLAUDE.md` and keep only what is specific to the site.
6. Static caching: set `STATAMIC_STATIC_CACHING_STRATEGY=half` in production; adapt `invalidation.content_graph` in `config/statamic/static_caching.php` when collections, sets or reference fields change.

## Updating a site

```bash
composer update kraenkvisuell/kraenk-statamic-kit
```

updates the package code (`src/`). Exported files are not touched by updates – compare them with this repo's `export/` folder by hand when something there changed.

## Working on the kit

The kit is developed inside a *sandbox* site (a "dummy" Statamic site), never in this repo directly:

1. **Set up the sandbox once.** Create a Statamic site (or use an existing one), copy this repo's root files – `composer.json`, `starter-kit.yaml`, `StarterKitPostInstall.php`, `README.md`, `src/` – into `<sandbox>/package/`, and require the package through a path repository in the sandbox's `composer.json`:

   ```json
   "require": { "kraenkvisuell/kraenk-statamic-kit": "dev-main" },
   "repositories": [
       { "type": "path", "url": "package", "options": { "versions": { "kraenkvisuell/kraenk-statamic-kit": "dev-main" } } }
   ]
   ```

   `composer update kraenkvisuell/kraenk-statamic-kit` links `vendor/kraenkvisuell/kraenk-statamic-kit` to `package/`. Then install the exported files into the sandbox (`php please starter-kit:install kraenkvisuell/kraenk-statamic-kit --local` from a clone, or copy `export/` over the sandbox), so the sandbox runs the theme.
2. **Edit in the sandbox.** Code in `package/src` is live immediately (a new command or modifier only needs the file). Theme and config files are edited where they live in the sandbox (`resources/`, `content/*.yaml`, `config/`, …) – add new files to `export_paths` in `package/starter-kit.yaml`. Generic PHP goes into `package/src`; site-specific code stays in `app/` and is not exported.
3. **Export and publish.**

   ```bash
   composer export   # php please starter-kit:export ../kraenk-statamic-kit --clear, then restores .github from git
   cd ~/Code/kraenk-statamic-kit && git add -A && git commit -m "…" && git push
   ```

   `--clear` empties the clone (except `.git`) before writing, so removed files disappear too. `starter-kit.yaml` is written with the dependency versions from the sandbox's `composer.json`. Packagist updates from GitHub (auto-update hook), so sites pick the change up with `composer update kraenkvisuell/kraenk-statamic-kit` (package code). Tag releases as `vX.Y.Z` and push the tag; `.github/workflows/release.yml` then creates the GitHub Release with generated notes. `.github/` is the one folder maintained in this repo by hand: `--clear` removes it on every export and `composer export` restores it with `git checkout -- .github`.

Rules of thumb: whether a file belongs in `src/` or in `export_paths` depends on who should be able to change it later – the kit (`src/`) or the site (`export_paths`). Anything in neither place is not part of the kit.
