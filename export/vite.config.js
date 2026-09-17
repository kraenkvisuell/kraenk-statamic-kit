import { existsSync } from 'node:fs';
import { homedir } from 'node:os';
import { resolve } from 'node:path';
import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const herdCertificates = resolve(homedir(), 'Library/Application Support/Herd/config/valet/Certificates');

// laravel-vite-plugin looks for the Herd certificate under the *directory* name,
// which is not the host Herd serves the site at whenever the two differ. Take the
// host from APP_URL instead, and only when that site is actually secured –
// otherwise Vite serves over plain http.
function herdTlsHost(mode) {
    const appUrl = loadEnv(mode, process.cwd(), '').APP_URL;

    if (! appUrl) {
        return false;
    }

    const { hostname } = new URL(appUrl);

    return existsSync(resolve(herdCertificates, `${hostname}.crt`)) ? hostname : false;
}

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: ['resources/css/site.css', 'resources/js/site.js', 'resources/js/gallery.js'],
            refresh: true,
            detectTls: herdTlsHost(mode),
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
}));
