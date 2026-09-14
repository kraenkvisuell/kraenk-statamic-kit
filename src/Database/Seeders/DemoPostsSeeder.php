<?php

namespace Kraenkvisuell\StatamicKit\Database\Seeders;

use Illuminate\Support\Carbon;
use Statamic\Facades\Collection;
use Statamic\Facades\Site;

/**
 * Three lorem ipsum blog posts for a fresh site, dated 30, 20 and 10 days
 * back so they are public, each with a one-sentence teaser and one
 * `text_image` set, localized into every site of the `blog` collection.
 *
 * Idempotent: existing posts are matched by slug in the default site and
 * reused.
 *
 *   php artisan db:seed --class="Kraenkvisuell\StatamicKit\Database\Seeders\DemoPostsSeeder"
 */
class DemoPostsSeeder extends DemoSeeder
{
    protected string $collection = 'blog';

    protected string $blueprint = 'post';

    /** slug => [title per site handle, days back] */
    protected array $posts = [
        'lorem-ipsum-dolor' => [['de' => 'Lorem ipsum dolor', 'en' => 'Lorem ipsum dolor'], 30],
        'sit-amet-consectetur' => [['de' => 'Sit amet consectetur', 'en' => 'Sit amet consectetur'], 20],
        'adipiscing-elit' => [['de' => 'Adipiscing elit', 'en' => 'Adipiscing elit'], 10],
    ];

    public function run(): void
    {
        $sites = Collection::findByHandle($this->collection)->sites()->all();
        $origin = Site::default()->handle();

        foreach ($this->posts as $slug => [$titles, $daysBack]) {
            $title = $titles[$origin] ?? reset($titles);

            $this->entry($slug, $titles, $sites, $origin, [
                'teaser' => $this->paragraph(1),
                'main_content' => [$this->textImageSet($title)],
            ], Carbon::now()->startOfDay()->subDays($daysBack));
        }

        $this->command?->info(sprintf('%d blog posts in %d site(s).', count($this->posts), count($sites)));
    }
}
