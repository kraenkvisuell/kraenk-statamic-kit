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
        $this->bootLanguagePrefixRedirect();
    }

    /**
     * Multisite routing: the default site lives at `/` and its start page is
     * `/`, while its collection routes carry the language prefix (`/de/…`),
     * like the other sites (`/en`, `/en/…`). So `/de` itself has no page –
     * redirect it to `/`. Single-site setups have no prefix and no redirect.
     */
    protected function bootLanguagePrefixRedirect(): void
    {
        if (! Site::hasMultiple()) {
            return;
        }

        Route::middleware('web')->get('/'.Site::default()->shortLocale(), fn () => redirect(Site::default()->url()));
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
