<?php

namespace App\StaticCaching;

use Illuminate\Support\Arr;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\StaticCaching\DefaultInvalidator;

/**
 * Static cache invalidation for this site. Statamic registers the invalidation
 * listeners only when STATAMIC_STATIC_CACHING_STRATEGY is set (half/full), so
 * locally – with the strategy at null – none of this runs.
 *
 * Most content is shared between pages here: the navi on every page is built
 * from the start page's sets, every page renders the contact section and the
 * footer from `site_settings`, film cards (start page and "similar films" on
 * film pages) show the other films' titles, stills and terms, and every photo
 * is a Glide URL with the asset's focal point baked in. So besides an entry's
 * own URLs this invalidator resolves the pages that *render* the saved item –
 * or flushes everything where the item appears on every page.
 */
class Invalidator extends DefaultInvalidator
{
    /** The collection whose entries render other entries and terms (film cards, film pages). */
    protected string $filmCollection = 'portfolio';

    /**
     * Collection handle => field of a film that references entries of that
     * collection. Saving such an entry also invalidates every film page that
     * renders it: team members appear as directors, films as "similar films".
     *
     * @var array<string, string>
     */
    protected array $filmReferences = [
        'team' => 'own_directors',
        'portfolio' => 'similar',
    ];

    /**
     * Map of collection handle => page-builder set handle(s) that render it.
     *
     * When an entry in one of these collections is saved, every page whose
     * `main_content` builder contains the associated "Übersicht" set is invalidated
     * too. This keeps overview/listing pages fresh without hard-coding their
     * URLs, so changing a page's slug never breaks invalidation.
     *
     * @var array<string, list<string>>
     */
    protected array $listingSets = [
        'blog' => ['blog'],
        'jobs' => ['team'],
        'team' => ['team'],
        'portfolio' => ['portfolio'],
    ];

    /**
     * Collections rendered in a global, every-page region. If it appears in every
     * URL, a change to one of these entries affects every
     * cached page and the whole static cache must be invalidated. These
     * collections are also routeless, meaning the default invalidator would
     * otherwise do nothing at all on save.
     *
     * @var list<string>
     */
    protected array $globalCollections = ['awards'];

    /**
     * Collections whose blueprint imports the `main_content` page builder,
     * i.e. any collection whose entries can embed an overview set. Saving a
     * listed-collection entry must invalidate matching pages across all of them,
     * not just `pages`.
     *
     * @var list<string>
     */
    protected array $builderCollections = ['pages'];

    protected function getEntryUrls($entry)
    {
        $collection = $entry->collectionHandle();

        if (in_array($collection, $this->globalCollections, true)) {
            return $this->allUrls();
        }

        // The start page's sets define the navi menu of every page (labels,
        // anchors, order), so saving it must flush everything, not just itself.
        if (in_array($collection, $this->builderCollections, true) && $entry->uri() === '/') {
            return $this->allUrls();
        }

        // Routeless entries (team, awards) have no URL of their own; the parent
        // returns null for them, which would be passed on as a URL to invalidate.
        $urls = array_filter(parent::getEntryUrls($entry));

        if ($sets = $this->listingSets[$collection] ?? null) {
            $urls = array_merge($urls, $this->urlsOfPagesUsingSets($sets));
        }

        // Films reference the German (root) entry's id, also when the English
        // localization is the one being saved.
        if ($field = $this->filmReferences[$collection] ?? null) {
            $urls = array_merge($urls, $this->urlsOfFilmsReferencing($field, $entry->root()->id()));
        }

        return array_values(array_unique($urls));
    }

    /**
     * Terms (clients, categories, cameras, ...) have no pages of their own but
     * their titles render on every film card and film page, so the pages with
     * the portfolio set and every film carrying the term are invalidated. The
     * field handle on the film equals the taxonomy handle; values are slugs.
     */
    protected function getTermUrls($term)
    {
        return array_values(array_unique([
            ...parent::getTermUrls($term),
            ...$this->urlsOfPagesUsingSets($this->listingSets[$this->filmCollection] ?? []),
            ...$this->urlsOfFilmsReferencing($term->taxonomyHandle(), $term->slug()),
        ]));
    }

    /**
     * Reordering a collection in the CP (films, team members, jobs) changes the
     * order of the cards on the pages listing it. The default invalidator
     * resolves a tree only through config rules, i.e. to nothing here.
     */
    protected function getCollectionTreeUrls($tree)
    {
        return array_values(array_unique([
            ...parent::getCollectionTreeUrls($tree),
            ...$this->urlsOfPagesUsingSets($this->listingSets[$tree->collection()->handle()] ?? []),
        ]));
    }

    /**
     * Every photo is output through Glide with the asset's focal point in the
     * URL and its alt text next to it, and assets are not tracked per page –
     * so a changed focus point, alt text or title flushes everything. The
     * alt-text addon saves assets from its queued job, which flushes too.
     */
    protected function getAssetUrls($asset)
    {
        return $this->allUrls();
    }

    /**
     * Every global set is rendered on every page: `site_settings` through the
     * layout (contact section, footer, legal texts). So a change affects all
     * cached pages. Statamic's default invalidator only flushes
     * URLs listed in the config rules, so without this a global edit would
     * silently serve stale pages everywhere.
     */
    protected function getGlobalUrls($variables)
    {
        return $this->allUrls();
    }

    /**
     * Every cached URL (of the current domain – both sites share one).
     *
     * @return list<string>
     */
    protected function allUrls(): array
    {
        return $this->cacher->getUrls()->values()->all();
    }

    /**
     * Absolute URLs (both sites) of the films whose field contains the value –
     * an entry id for `own_directors`/`similar`, a term slug for the taxonomy
     * fields. value() falls back to the origin, so an untranslated English film
     * is matched through its German data.
     *
     * @return list<string>
     */
    protected function urlsOfFilmsReferencing(string $field, string $value): array
    {
        return Entries::query()
            ->where('collection', $this->filmCollection)
            ->get()
            ->filter(fn (Entry $film) => in_array($value, Arr::wrap($film->value($field) ?? []), true))
            ->map->absoluteUrl()
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Absolute URLs of every builder-bearing entry whose `main_content` page
     * builder contains one of the given set types.
     *
     * @param  list<string>  $sets
     * @return list<string>
     */
    protected function urlsOfPagesUsingSets(array $sets): array
    {
        // value() falls back to the origin: an English page that has not been
        // localized yet inherits the German sets and must be matched as well.
        return Entries::query()
            ->whereIn('collection', $this->builderCollections)
            ->get()
            ->filter(fn (Entry $entry) => $this->containsSet($entry->value('main_content'), $sets))
            ->map->absoluteUrl()
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Recursively check whether page-builder data contains one of the set types.
     * Handles both Replicator-style nodes (`type` at the top level) and Bard
     * set nodes (`attrs.values.type`).
     *
     * @param  list<string>  $sets
     */
    protected function containsSet(mixed $data, array $sets): bool
    {
        if (! is_array($data)) {
            return false;
        }

        foreach ($data as $node) {
            if (! is_array($node)) {
                continue;
            }

            $type = $node['type'] ?? ($node['attrs']['values']['type'] ?? null);

            if (in_array($type, $sets, true)) {
                return true;
            }

            if (isset($node['main_content']) && $this->containsSet($node['main_content'], $sets)) {
                return true;
            }
        }

        return false;
    }
}
