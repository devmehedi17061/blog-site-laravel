import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // app.css is shared by the public site and the admin panel;
            // admin.js is only loaded behind the login. A site pack's own files
            // build separately: npm run build:site (vite.site.config.js).
            input: ['resources/css/app.css', 'resources/js/admin.js', 'resources/js/site.js'],
            refresh: ['resources/views/**'],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
