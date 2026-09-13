<?php

namespace Kraenkvisuell\StatamicKit;

use Illuminate\Foundation\Events\LocaleUpdated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Number;
use Statamic\Providers\AddonServiceProvider;

/**
 * The updatable part of the starter kit. Statamic autoloads what lives next to
 * this file: Console/Commands (assets:copy-to-bunny, bard:fix-list-items,
 * site:reset-postgres-keys) and Modifiers (ensure_url, file_size). The
 * middleware in Http/Middleware is registered by the site's bootstrap/app.php,
 * because it has to run before TrustProxies.
 */
class ServiceProvider extends AddonServiceProvider
{
    public function bootAddon()
    {
        $this->bootNumberLocale();
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
