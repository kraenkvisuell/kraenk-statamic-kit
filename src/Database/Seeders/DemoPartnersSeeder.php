<?php

namespace Kraenkvisuell\StatamicKit\Database\Seeders;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;

/**
 * Three lorem ipsum partners for a fresh site, localized into every site of
 * the `partners` collection and placed in its tree in this order. They have
 * no icon: partials/partners shows the dummy logos from public/placeholders
 * (partner-01.svg …) while a partner's icon is empty, so nothing has to be
 * uploaded to the asset container.
 *
 * Idempotent: existing partners are matched by slug in the default site and
 * reused; the trees are rebuilt on every run.
 *
 *   php artisan db:seed --class="Kraenkvisuell\StatamicKit\Database\Seeders\DemoPartnersSeeder"
 */
class DemoPartnersSeeder extends DemoSeeder
{
    protected string $collection = 'partners';

    protected string $blueprint = 'partner';

    /** slug => title per site handle */
    protected array $partners = [
        'lorem-gmbh' => ['default' => 'Lorem GmbH', 'en' => 'Lorem GmbH'],
        'ipsum-ag' => ['default' => 'Ipsum AG', 'en' => 'Ipsum AG'],
        'dolor-partner' => ['default' => 'Dolor & Partner', 'en' => 'Dolor & Partner'],
    ];

    public function run(): void
    {
        $collection = Collection::findByHandle($this->collection);
        $sites = $collection->sites()->all();
        $origin = Site::default()->handle();

        foreach ($sites as $site) {
            $this->ensureTree($collection->structure(), $site);
        }

        $partners = collect($this->partners)->map(fn ($titles, $slug) => $this->entry($slug, $titles, $sites, $origin));

        foreach ($sites as $site) {
            $this->saveTree($collection->structure(), $site, $partners->map(
                fn (EntryContract $entry) => ['entry' => $entry->in($site)->id()]
            )->values()->all());
        }

        $this->command?->info(sprintf('%d partners in %d site(s); partners tree rebuilt.', $partners->count(), count($sites)));
    }
}
