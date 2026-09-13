<?php

namespace Kraenkvisuell\StatamicKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Structures\Structure;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Nav;
use Statamic\Facades\Site;

/**
 * Demo pages for a fresh site: a start page, five main pages, an "area" (a
 * text-only navigation item) with three sub pages, and the footer pages
 * Impressum, Datenschutz and Kontakt. Every page is localized into every site
 * of the `pages` collection, placed in the collection tree (start page as root)
 * and in the `main` or `footer` navigation.
 *
 * Idempotent: existing pages are matched by slug in the default site and
 * reused; the trees are rebuilt on every run.
 *
 *   php artisan db:seed --class="Kraenkvisuell\StatamicKit\Database\Seeders\DemoPagesSeeder"
 */
class DemoPagesSeeder extends Seeder
{
    protected string $collection = 'pages';

    /** slug => title per site handle (the default site's slug identifies the page) */
    protected array $home = ['default' => 'Startseite', 'en' => 'Home'];

    protected array $mainPages = [
        'lorem' => ['default' => 'Lorem', 'en' => 'Lorem'],
        'ipsum' => ['default' => 'Ipsum', 'en' => 'Ipsum'],
        'dolor' => ['default' => 'Dolor', 'en' => 'Dolor'],
        'sit-amet' => ['default' => 'Sit amet', 'en' => 'Sit amet'],
        'consectetur' => ['default' => 'Consectetur', 'en' => 'Consectetur'],
    ];

    protected array $area = ['default' => 'Adipiscing', 'en' => 'Adipiscing'];

    protected array $areaPages = [
        'elit' => ['default' => 'Elit', 'en' => 'Elit'],
        'sed-do' => ['default' => 'Sed do', 'en' => 'Sed do'],
        'eiusmod' => ['default' => 'Eiusmod', 'en' => 'Eiusmod'],
    ];

    protected array $footerPages = [
        'impressum' => ['default' => 'Impressum', 'en' => 'Imprint'],
        'datenschutz' => ['default' => 'Datenschutz', 'en' => 'Privacy'],
        'kontakt' => ['default' => 'Kontakt', 'en' => 'Contact'],
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
     * The page with this slug in the origin site, created with one text_image
     * set when missing, plus a localization per further site.
     */
    protected function page(string $slug, array $titles, array $sites, string $origin): EntryContract
    {
        $title = $titles[$origin] ?? reset($titles);

        $entry = Entry::query()
            ->where('collection', $this->collection)
            ->where('site', $origin)
            ->where('slug', $slug)
            ->first();

        if (! $entry) {
            $entry = Entry::make()
                ->collection($this->collection)
                ->blueprint('default')
                ->locale($origin)
                ->slug($slug)
                ->published(true)
                ->data(['title' => $title, 'main_content' => [$this->textImageSet($title)]]);

            $entry->save();
        }

        foreach ($sites as $site) {
            if ($site === $origin || $entry->in($site)) {
                continue;
            }

            $entry->makeLocalization($site)
                ->slug($slug)
                ->published(true)
                ->data(['title' => $titles[$site] ?? $title])
                ->save();
        }

        return $entry;
    }

    /** A `text_image` set of the main_content page builder with a lorem paragraph (Bard = ProseMirror). */
    protected function textImageSet(string $headline): array
    {
        return [
            'id' => Str::random(8),
            'type' => 'text_image',
            'enabled' => true,
            'headline' => $headline,
            'text' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.']],
            ]],
        ];
    }

    protected function ensureTree(Structure $structure, string $site): void
    {
        if (! $structure->in($site)) {
            $structure->makeTree($site)->save();
        }
    }

    /**
     * Replace a site's tree. The eloquent driver stores a brand-new tree as
     * empty on its first save, so the tree is created first (ensureTree) and
     * filled here.
     */
    protected function saveTree(Structure $structure, string $site, array $tree): void
    {
        $structure->in($site)->tree($tree)->save();
    }
}
