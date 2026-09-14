<?php

namespace Kraenkvisuell\StatamicKit;

use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Number;
use Statamic\Facades\Site;
use Statamic\Providers\AddonServiceProvider;

/**
 * The updatable part of the starter kit. Statamic autoloads what lives next to
 * this file: Console/Commands (kit:init, kit:copy-assets-to-bunny,
 * kit:fix-bard-list-items, kit:reset-postgres-keys) and Modifiers (ensure_url,
 * file_size). StaticCaching/Invalidator is bound by the exported
 * config/statamic/static_caching.php. The
 * middleware in Http/Middleware is registered by the site's bootstrap/app.php,
 * because it has to run before TrustProxies.
 */
class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        $this->bootNumberLocale();
        $this->bootRootRedirect();
    }

    /**
     * Multisite routing keeps every site under its own prefix (`/de`, `/en`),
     * so nothing answers at `/`. Redirect it to the default site. Skipped as
     * soon as a site lives at `/` (single-site setups).
     */
    protected function bootRootRedirect(): void
    {
        $rootTaken = Site::all()->contains(
            fn ($site) => rtrim(parse_url($site->absoluteUrl(), PHP_URL_PATH) ?: '/', '/') === ''
        );

        if ($rootTaken) {
            return;
        }

        Route::middleware('web')->get('/', fn () => redirect(Site::default()->url()));
    }

    /**
     * Numbers are formatted the way the current language does it (intl):
     * "1.234,5 MB" on a German site, "1,234.5 MB" on /en. Statamic sets the
     * app locale from the site (Localize middleware) and fires LocaleUpdated,
     * so Number follows it.
     */
    protected function bootNumberLocale(): void
    {
        Number::useLocale($this->app->getLocale());

        Event::listen(LocaleUpdated::class, fn (LocaleUpdated $event) => Number::useLocale($event->locale));
    }
}
