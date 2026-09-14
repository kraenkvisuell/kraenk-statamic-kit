<?php

namespace Kraenkvisuell\StatamicKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Structures\Structure;
use Statamic\Facades\Entry;

/**
 * Shared pieces of the demo content seeders: an entry per slug that is
 * created once in the origin site and localized into every further site,
 * lorem ipsum in the shapes the blueprints expect (a `text_image` set of the
 * main_content page builder, a Bard paragraph), and the tree helpers for
 * structured collections.
 */
abstract class DemoSeeder extends Seeder
{
    protected string $collection;

    protected string $blueprint;

    protected string $lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.';

    /**
     * The entry with this slug in the origin site, created with the given
     * data when missing, plus a localization per further site (title only,
     * everything else falls back to the origin). Existing entries are reused
     * untouched.
     *
     * @param  array<string, string>  $titles  site handle => title
     */
    protected function entry(string $slug, array $titles, array $sites, string $origin, array $data = [], ?Carbon $date = null): EntryContract
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
                ->blueprint($this->blueprint)
                ->locale($origin)
                ->slug($slug)
                ->published(true)
                ->data(['title' => $title, ...$data]);

            if ($date) {
                $entry->date($date);
            }

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

    /** A `text_image` set of the main_content page builder with a lorem paragraph. */
    protected function textImageSet(string $headline): array
    {
        return [
            'id' => Str::random(8),
            'type' => 'text_image',
            'enabled' => true,
            'headline' => $headline,
            'text' => $this->paragraph(),
        ];
    }

    /** One Bard paragraph (ProseMirror: paragraph > text) with lorem ipsum, optionally cut to a number of sentences. */
    protected function paragraph(?int $sentences = null): array
    {
        $text = $sentences
            ? implode(' ', array_slice(preg_split('/(?<=\.)\s+/', $this->lorem), 0, $sentences))
            : $this->lorem;

        return [[
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ]];
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
