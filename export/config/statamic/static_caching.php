<?php

use Kraenkvisuell\StatamicKit\StaticCaching\Invalidator;
use Statamic\StaticCaching\Replacers\CsrfTokenReplacer;
use Statamic\StaticCaching\Replacers\NoCacheReplacer;

return [

    /*
    |--------------------------------------------------------------------------
    | Active Static Caching Strategy
    |--------------------------------------------------------------------------
    |
    | To enable Static Caching, you should choose a strategy from the ones
    | you have defined below. Leave this null to disable static caching.
    |
    */

    'strategy' => env('STATAMIC_STATIC_CACHING_STRATEGY', null),

    /*
    |--------------------------------------------------------------------------
    | Caching Strategies
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the static caching strategies for your
    | application as well as their drivers.
    |
    | Supported drivers: "application", "file"
    |
    */

    'strategies' => [

        'half' => [
            'driver' => 'application',
            'expiry' => null,
        ],

        'full' => [
            'driver' => 'file',
            'path' => public_path('static'),
            'lock_hold_length' => 0,
            'permissions' => [
                'directory' => 0755,
                'file' => 0644,
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Exclusions
    |--------------------------------------------------------------------------
    |
    | Here you may define a list of URLs to be excluded from static
    | caching. You may want to exclude URLs containing dynamic
    | elements like contact forms, or shopping carts.
    |
    */

    'exclude' => [

        'class' => null,

        'urls' => [
            //
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Invalidation Rules
    |--------------------------------------------------------------------------
    |
    | Here you may define the rules that trigger when and how content would be
    | flushed from the static cache. See the documentation for more details.
    | If a custom class is not defined, the default invalidator is used.
    |
    | https://statamic.dev/static-caching
    |
    */

    'invalidation' => [

        'class' => Invalidator::class,

        'rules' => [
            // SEO Pro's site defaults (title format, default image, ...) are in
            // the <head> of every page; its listener only reads this rule key.
            'seo_pro_site_defaults' => [
                'urls' => ['/*'],
            ],
        ],

        // The kit's invalidator (see the class) follows the content graph: which
        // entries and terms render on which pages. Adapt this when collections,
        // page-builder sets or reference fields change.
        'content_graph' => [
            // Entries of this collection render other entries and terms (cards, "similar" entries).
            'referencing_collection' => 'projects',
            // collection => field on the referencing collection holding that collection's entry ids
            'references' => [],
            // collection => page-builder set(s) listing it
            'listing_sets' => [
                'blog' => ['blog'],
                'projects' => ['projects'],
            ],
            // Collections rendered on every page: a save flushes everything.
            'global_collections' => ['partners'],
            // Collections whose blueprint carries the page builder, and its field handle.
            'builder_collections' => ['pages'],
            'builder_field' => 'main_content',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Ignoring Query Strings
    |--------------------------------------------------------------------------
    |
    | Statamic will cache pages of the same URL but with different query
    | parameters separately. This is useful for pages with pagination.
    | If you'd like to ignore the query strings, you may do so.
    |
    */

    'ignore_query_strings' => false,

    'allowed_query_strings' => [
        //
    ],

    'disallowed_query_strings' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Nocache
    |--------------------------------------------------------------------------
    |
    | Here you may define where the nocache data is stored.
    |
    | https://statamic.dev/tags/nocache#database
    |
    | Supported drivers: "cache", "database"
    |
    */

    'nocache' => 'cache',

    'nocache_db_connection' => env('STATAMIC_NOCACHE_DB_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Replacers
    |--------------------------------------------------------------------------
    |
    | Here you may define replacers that dynamically replace content within
    | the response. Each replacer must implement the Replacer interface.
    |
    */

    'replacers' => [
        CsrfTokenReplacer::class,
        NoCacheReplacer::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Warm Queue
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue name and connection
    | that will be used when warming the static cache and
    | optionally set the "--insecure" flag by default.
    |
    */

    'warm_queue' => env('STATAMIC_STATIC_WARM_QUEUE'),

    'warm_queue_connection' => env('STATAMIC_STATIC_WARM_QUEUE_CONNECTION'),

    'warm_insecure' => env('STATAMIC_STATIC_WARM_INSECURE', false),

    /*
    |--------------------------------------------------------------------------
    | Background Re-cache
    |--------------------------------------------------------------------------
    |
    | When this is enabled, Statamic will re-cache URLs in the background,
    | overwriting the existing cache, without removing it first.
    |
    */

    'background_recache' => env('STATAMIC_BACKGROUND_RECACHE', false),

    'recache_token' => env('STATAMIC_RECACHE_TOKEN'),

    'recache_token_parameter' => '__recache',

    /*
    |--------------------------------------------------------------------------
    | Shared Error Pages
    |--------------------------------------------------------------------------
    |
    | You may choose to share the same statically generated error page across
    | all errors. For example, the first time a 404 is encountered it will
    | be generated and cached, and then served for all subsequent 404s.
    |
    | This is only supported for half measure.
    |
    */

    'share_errors' => false,

];
