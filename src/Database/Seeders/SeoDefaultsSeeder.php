<?php

namespace Kraenkvisuell\StatamicKit\Database\Seeders;

use Illuminate\Database\Seeder;
use Statamic\Facades\Addon;
use Statamic\Facades\Site;
use Statamic\SeoPro\SiteDefaults\SiteDefaults;

/**
 * SEO Pro site defaults for a fresh site. Only what SEO Pro needs to build a
 * title, description and canonical URL is set: the sources point at the
 * entry (`@seo:title`, `@seo:permalink`), the two free-text values (site
 * name, description) are lorem ipsum placeholders the editors replace in the
 * CP. Every other field (JSON-LD, robots, social image, verification codes)
 * stays empty. Sites other than the default one inherit from it until they
 * are localized.
 *
 * The values live in the addon settings table (eloquent driver), so a fresh
 * site only gets them through this seeder. Idempotent: existing defaults are
 * left untouched.
 *
 *   php artisan db:seed --class="Kraenkvisuell\StatamicKit\Database\Seeders\SeoDefaultsSeeder"
 */
class SeoDefaultsSeeder extends Seeder
{
    protected array $defaults = [
        'site_name' => 'Lorem Ipsum',
        'site_name_position' => 'after',
        'site_name_separator' => '|',
        'title' => '@seo:title',
        'description' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
        'canonical_url' => '@seo:permalink',
        'og_type' => 'website',
        'priority' => 0.5,
        'change_frequency' => 'monthly',
    ];

    public function run(): void
    {
        if (Addon::get('statamic/seo-pro')->settings()->get('site_defaults')) {
            $this->command?->info('SEO Pro site defaults exist, left as they are.');

            return;
        }

        $default = Site::default()->handle();

        SiteDefaults::origins(
            Site::all()
                ->reject(fn ($site) => $site->handle() === $default)
                ->mapWithKeys(fn ($site) => [$site->handle() => $default])
                ->all()
        );

        SiteDefaults::in($default)->set($this->defaults)->save();

        $this->command?->info("SEO Pro site defaults seeded for site {$default}; other sites inherit them.");
    }
}
