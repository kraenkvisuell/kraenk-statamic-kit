<?php

namespace Kraenkvisuell\StatamicKit\Database\Seeders;

use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;

/**
 * Three lorem ipsum projects for a fresh site, each with one `text_media`
 * set, localized into every site of the `projects` collection and placed in
 * its tree (the collection is orderable) in this order.
 *
 * Idempotent: existing projects are matched by slug in the default site and
 * reused; the trees are rebuilt on every run.
 *
 *   php artisan db:seed --class="Kraenkvisuell\StatamicKit\Database\Seeders\DemoProjectsSeeder"
 */
class DemoProjectsSeeder extends DemoSeeder
{
    protected string $collection = 'projects';

    protected string $blueprint = 'project';

    /** slug => title per site handle */
    protected array $projects = [
        'tempor-incididunt' => ['default' => 'Tempor incididunt', 'en' => 'Tempor incididunt'],
        'labore-et-dolore' => ['default' => 'Labore et dolore', 'en' => 'Labore et dolore'],
        'magna-aliqua' => ['default' => 'Magna aliqua', 'en' => 'Magna aliqua'],
    ];

    public function run(): void
    {
        $collection = Collection::findByHandle($this->collection);
        $sites = $collection->sites()->all();
        $origin = Site::default()->handle();

        foreach ($sites as $site) {
            $this->ensureTree($collection->structure(), $site);
        }

        $projects = collect($this->projects)->map(function ($titles, $slug) use ($sites, $origin) {
            $title = $titles[$origin] ?? reset($titles);

            return $this->entry($slug, $titles, $sites, $origin, ['main_content' => [$this->textMediaSet($title)]]);
        });

        foreach ($sites as $site) {
            $this->saveTree($collection->structure(), $site, $projects->map(
                fn (EntryContract $entry) => ['entry' => $entry->in($site)->id()]
            )->values()->all());
        }

        $this->command?->info(sprintf('%d projects in %d site(s); projects tree rebuilt.', $projects->count(), count($sites)));
    }
}
