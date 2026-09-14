<?php

namespace Kraenkvisuell\StatamicKit\StaticCaching;

use Illuminate\Support\Arr;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Entry as Entries;
use Statamic\StaticCaching\Cacher;
use Statamic\StaticCaching\DefaultInvalidator;

/**
 * Static cache invalidation along the site's content graph. Statamic registers
 * the invalidation listeners only when STATAMIC_STATIC_CACHING_STRATEGY is set
 * (half/full), so locally – with the strategy at null – none of this runs.
 *
 * Besides an item's own URLs this invalidator resolves the pages that *render*
 * the saved item: listing pages built from page-builder sets, entries that
 * reference it, entries carrying its taxonomy term. Whatever appears on every
 * page – globals, navigations, assets (Glide URLs carry the focal point) –
 * flushes everything.
 *
 * Which collections, sets and fields make up that graph is the site's business:
 * `invalidation.content_graph` in config/statamic/static_caching.php. The
 * defaults below are the kit theme's.
 */
class Invalidator extends DefaultInvalidator
{
    /**
     * The collection whose entries render other entries and terms (cards on
     * listing pages, "similar" entries on detail pages). Theme: projects.
     */
    protected string $referencingCollection = 'projects';

    /**
     * Collection handle => field of the referencing collection that holds ids
     * of that collection's entries. Saving such an entry also invalidates every
     * referencing entry that renders it (e.g. team members as directors,
     * "similar" entries). The theme has none.
     *
     * @var array<string, string>
     */
    protected array $references = [];

    /**
     * Collection handle => page-builder set handle(s) that list it. Saving an
     * entry (or reordering the collection) invalidates every page whose builder
     * contains one of the sets – without hard-coding page URLs, so a changed
     * slug never breaks invalidation.
     *
     * @var array<string, list<string>>
     */
    protected array $listingSets = [
        'blog' => ['blog'],
        'projects' => ['projects'],
    ];

    /**
     * Collections rendered in an every-page region (theme: the partner logos
     * above the footer). A saved entry flushes everything; these are typically routeless,
     * so the default invalidator would do nothing at all for them.
     *
     * @var list<string>
     */
    protected array $globalCollections = ['partners'];

    /**
     * Collections whose blueprint carries the page builder, i.e. whose entries
     * can embed a listing set.
     *
     * @var list<string>
     */
    protected array $builderCollections = ['pages'];

    /** The page-builder field (replicator or Bard) on the builder collections. */
    protected string $builderField = 'main_content';

    /**
     * Statamic resolves the configured invalidator through the container, so
     * the config is read here: the rules the parent needs and the content graph.
     */
    public function __construct(Cacher $cacher)
    {
        parent::__construct($cacher, config('statamic.static_caching.invalidation.rules', []));

        $graph = config('statamic.static_caching.invalidation.content_graph', []);

        $this->referencingCollection = $graph['referencing_collection'] ?? $this->referencingCollection;
        $this->references = $graph['references'] ?? $this->references;
        $this->listingSets = $graph['listing_sets'] ?? $this->listingSets;
        $this->globalCollections = $graph['global_collections'] ?? $this->globalCollections;
        $this->builderCollections = $graph['builder_collections'] ?? $this->builderCollections;
        $this->builderField = $graph['builder_field'] ?? $this->builderField;
    }

    protected function getEntryUrls($entry)
    {
        $collection = $entry->collectionHandle();

        if (in_array($collection, $this->globalCollections, true)) {
            return $this->allUrls();
        }

        // Routeless entries have no URL of their own; the parent returns null
        // for them, which would be passed on as a URL to invalidate.
        $urls = array_filter(parent::getEntryUrls($entry));

        if ($sets = $this->listingSets[$collection] ?? null) {
            $urls = array_merge($urls, $this->urlsOfPagesUsingSets($sets));
        }

        // References hold the origin entry's id, also when a localization is
        // the one being saved.
        if ($field = $this->references[$collection] ?? null) {
            $urls = array_merge($urls, $this->urlsOfEntriesReferencing($field, $entry->root()->id()));
        }

        return array_values(array_unique($urls));
    }

    /**
     * Terms have no pages of their own here but their titles render on every
     * card and detail page of the referencing collection, so the pages listing
     * that collection and every entry carrying the term are invalidated. The
     * field handle on the entry equals the taxonomy handle; values are slugs.
     */
    protected function getTermUrls($term)
    {
        return array_values(array_unique([
            ...parent::getTermUrls($term),
            ...$this->urlsOfPagesUsingSets($this->listingSets[$this->referencingCollection] ?? []),
            ...$this->urlsOfEntriesReferencing($term->taxonomyHandle(), $term->slug()),
        ]));
    }

    /**
     * Reordering a collection in the CP changes the order of the cards on the
     * pages listing it. The default invalidator resolves a tree only through
     * config rules, i.e. to nothing here.
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
     * Global sets render through the layout on every page (contact section,
     * footer, video consent). The default invalidator only flushes URLs listed in
     * the config rules, so without this a global edit would silently serve
     * stale pages everywhere.
     */
    protected function getGlobalUrls($variables)
    {
        return $this->allUrls();
    }

    /**
     * The navigations render in the layout (`main` in the navi, `footer` in the
     * footer), so a saved navigation or tree affects every page. The parent only
     * follows `navigation.<handle>.urls` invalidation rules.
     */
    protected function getNavUrls($nav)
    {
        return $this->allUrls();
    }

    protected function getNavTreeUrls($tree)
    {
        return $this->allUrls();
    }

    /**
     * Every cached URL (of the current domain – the sites share one).
     *
     * @return list<string>
     */
    protected function allUrls(): array
    {
        return $this->cacher->getUrls()->values()->all();
    }

    /**
     * Absolute URLs (all sites) of the referencing collection's entries whose
     * field contains the value – an entry id for reference fields, a term slug
     * for taxonomy fields. value() falls back to the origin, so an untranslated
     * localization is matched through its origin's data.
     *
     * @return list<string>
     */
    protected function urlsOfEntriesReferencing(string $field, string $value): array
    {
        return Entries::query()
            ->where('collection', $this->referencingCollection)
            ->get()
            ->filter(fn (Entry $entry) => in_array($value, Arr::wrap($entry->value($field) ?? []), true))
            ->map->absoluteUrl()
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Absolute URLs of every builder-collection entry whose page builder
     * contains one of the given set types.
     *
     * @param  list<string>  $sets
     * @return list<string>
     */
    protected function urlsOfPagesUsingSets(array $sets): array
    {
        if ($sets === []) {
            return [];
        }

        // value() falls back to the origin: a localization that has not been
        // translated yet inherits the origin's sets and must be matched as well.
        return Entries::query()
            ->whereIn('collection', $this->builderCollections)
            ->get()
            ->filter(fn (Entry $entry) => $this->containsSet($entry->value($this->builderField), $sets))
            ->map->absoluteUrl()
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Recursively check whether page-builder data contains one of the set types.
     * Handles both Replicator-style nodes (`type` at the top level) and Bard
     * set nodes (`attrs.values.type`), and nested builders.
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

            if (isset($node[$this->builderField]) && $this->containsSet($node[$this->builderField], $sets)) {
                return true;
            }
        }

        return false;
    }
}
