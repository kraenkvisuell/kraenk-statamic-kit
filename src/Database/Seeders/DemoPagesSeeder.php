<?php

namespace Kraenkvisuell\StatamicKit\Database\Seeders;

use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Collection;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;

/**
 * Demo pages for a fresh site: a start page, five main pages, an "area" (a
 * text-only navigation item) with three sub pages, and the footer pages
 * Impressum, Datenschutz and Kontakt. Every page is localized into every site
 * of the `pages` collection, placed in the collection tree (start page as root)
 * and in the `main` or `footer` navigation. The start page also gets the
 * `projects` and `blog` listing sets, so the seeded projects and posts show.
 *
 * Idempotent: existing pages are matched by slug in the default site and
 * reused; the trees are rebuilt on every run.
 *
 *   php artisan db:seed --class="Kraenkvisuell\StatamicKit\Database\Seeders\DemoPagesSeeder"
 */
class DemoPagesSeeder extends DemoSeeder
{
    protected string $collection = 'pages';

    protected string $blueprint = 'default';

    /** slug => title per site handle (the default site's slug identifies the page) */
    protected array $home = ['de' => 'Startseite', 'en' => 'Home'];

    protected array $mainPages = [
        'lorem' => ['de' => 'Lorem', 'en' => 'Lorem'],
        'ipsum' => ['de' => 'Ipsum', 'en' => 'Ipsum'],
        'dolor' => ['de' => 'Dolor', 'en' => 'Dolor'],
        'sit-amet' => ['de' => 'Sit amet', 'en' => 'Sit amet'],
        'consectetur' => ['de' => 'Consectetur', 'en' => 'Consectetur'],
    ];

    protected array $area = ['de' => 'Adipiscing', 'en' => 'Adipiscing'];

    protected array $areaPages = [
        'elit' => ['de' => 'Elit', 'en' => 'Elit'],
        'sed-do' => ['de' => 'Sed do', 'en' => 'Sed do'],
        'eiusmod' => ['de' => 'Eiusmod', 'en' => 'Eiusmod'],
    ];

    protected array $footerPages = [
        'impressum' => ['de' => 'Impressum', 'en' => 'Imprint'],
        'datenschutz' => ['de' => 'Datenschutz', 'en' => 'Privacy'],
        'kontakt' => ['de' => 'Kontakt', 'en' => 'Contact'],
    ];

    public function run(): void
    {
        $collection = Collection::findByHandle($this->collection);
        $sites = $collection->sites()->all();
        $origin = Site::default()->handle();

        // Trees must exist before entries are saved into a structured collection.
        foreach ($sites as $site) {
            $this->ensureTree($collection->structure(), $site);
            $this->ensureTree(Nav::find('main'), $site);
            $this->ensureTree(Nav::find('footer'), $site);
        }

        $home = $this->page('home', $this->home, $sites, $origin);
        $this->ensureSets($home, [$this->listingSet('projects', 'Dolor sit amet'), $this->listingSet('blog', 'Consectetur adipiscing')]);
        $main = collect($this->mainPages)->map(fn ($titles, $slug) => $this->page($slug, $titles, $sites, $origin));
        $area = collect($this->areaPages)->map(fn ($titles, $slug) => $this->page($slug, $titles, $sites, $origin));
        $footer = collect($this->footerPages)->map(fn ($titles, $slug) => $this->page($slug, $titles, $sites, $origin));

        foreach ($sites as $site) {
            $in = fn (EntryContract $entry) => $entry->in($site);

            $this->saveTree($collection->structure(), $site, [
                ['entry' => $in($home)->id()],
                ...$main->map(fn ($entry) => ['entry' => $in($entry)->id()])->values(),
                ...$area->map(fn ($entry) => ['entry' => $in($entry)->id()])->values(),
                ...$footer->map(fn ($entry) => ['entry' => $in($entry)->id()])->values(),
            ]);

            $this->saveTree(Nav::find('main'), $site, [
                ...$main->map(fn ($entry) => ['id' => (string) Str::uuid(), 'entry' => $in($entry)->id()])->values(),
                [
                    'id' => (string) Str::uuid(),
                    'title' => $this->area[$site] ?? $this->area[$origin],
                    'children' => $area->map(fn ($entry) => ['id' => (string) Str::uuid(), 'entry' => $in($entry)->id()])->values()->all(),
                ],
            ]);

            $this->saveTree(Nav::find('footer'), $site, $footer->map(
                fn ($entry) => ['id' => (string) Str::uuid(), 'entry' => $in($entry)->id()]
            )->values()->all());
        }

        $this->command?->info(sprintf(
            '%d pages in %d site(s); main and footer navigations rebuilt.',
            1 + $main->count() + $area->count() + $footer->count(),
            count($sites)
        ));
    }

    /**
     * Append the given main_content sets to the entry unless a set of that
     * type is there already (the listings are one-per-page sections).
     */
    protected function ensureSets(EntryContract $entry, array $sets): void
    {
        $content = collect($entry->get('main_content', []));
        $missing = collect($sets)->reject(fn ($set) => $content->contains('type', $set['type']));

        if ($missing->isEmpty()) {
            return;
        }

        $entry->set('main_content', $content->merge($missing)->values()->all())->save();
    }

    /** A `projects` or `blog` listing set: topline, headline and a one-sentence copy above the listing. */
    protected function listingSet(string $type, string $headline): array
    {
        return [
            'id' => Str::random(8),
            'type' => $type,
            'enabled' => true,
            'topline' => 'Lorem ipsum',
            'headline' => $headline,
            'copy' => $this->paragraph(1),
        ];
    }

    /** The page with this slug, created with one text_image set when missing (see DemoSeeder::entry). */
    protected function page(string $slug, array $titles, array $sites, string $origin): EntryContract
    {
        $title = $titles[$origin] ?? reset($titles);

        return $this->entry($slug, $titles, $sites, $origin, ['main_content' => [$this->textImageSet($title)]]);
    }
}
