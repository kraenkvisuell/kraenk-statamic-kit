<?php

/**
 * Runs once at the end of `starter-kit:install`, after the files were copied
 * and the dependencies required. Prints the manual steps that are left.
 */
class StarterKitPostInstall
{
    public function handle($console)
    {
        $console->newLine();
        $console->info('kraenkvisuell/kraenk-statamic-kit is installed. Next:');
        $console->line('  1. .env: DB_CONNECTION=pgsql + credentials, QUEUE_CONNECTION=redis, BUNNY_S3_* + BUNNY_PUBLIC_URL, GLIDE_CACHE_DISK=bunny-glide-cache, STATAMIC_PRO_ENABLED (multi-site or more than one user).');
        $console->line('  2. php artisan migrate   (entries, trees, globals, terms and users in the database)');
        $console->line('  3. php artisan kit:init  (demo pages and navigations, then your super user)');
        $console->line('  4. bootstrap/app.php: behind Bunny CDN prepend Kraenkvisuell\StatamicKit\Http\Middleware\UseCdnClientIp and trust only the X-Forwarded-Host/Port/Proto headers – see vendor/kraenkvisuell/kraenk-statamic-kit/README.md.');
        $console->line('  5. npm install && npm run build  (Vite: resources/css/site.css, resources/js/site.js, resources/js/gallery.js)');
        $console->line('  6. Start the CLAUDE.md with `@~/Code/coding-guidelines/CLAUDE.md` and keep only what is specific to the site.');
        $console->newLine();
    }
}
