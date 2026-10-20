import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { globSync } from 'node:fs';

/*
 * This site's pack (sites/<SITE_PACK>/): its own stylesheets and scripts,
 * built into public/site-build/ — apart from the core's public/build/, so a
 * core update (which replaces public/build/) never touches them.
 *
 *   npm run build:site
 *
 * Files whose name starts with "_" are partials: imported, not built.
 */
export default defineConfig(({ mode }) => {
    const pack = (loadEnv(mode, process.cwd(), '').SITE_PACK || '').trim();
    const input = pack
        ? [...globSync(`sites/${pack}/resources/css/*.css`), ...globSync(`sites/${pack}/resources/js/*.js`)]
            .map((file) => file.replaceAll('\\', '/'))
            .filter((file) => !file.split('/').pop().startsWith('_'))
        : [];

    if (!input.length) {
        throw new Error(pack ? `sites/${pack}/resources has no css or js to build.` : 'SITE_PACK is not set in .env.');
    }

    return {
        plugins: [
            laravel({ input, buildDirectory: 'site-build', hotFile: 'public/site-hot' }),
            tailwindcss(),
        ],
    };
});
