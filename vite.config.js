import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // app.css is shared by the public site and the admin panel;
            // admin.js is only loaded behind the login.
            input: ['resources/css/app.css', 'resources/js/admin.js', 'resources/js/site.js'],
            refresh: ['resources/views/**'],
        }),
        tailwindcss(),
    ],
    build: {
        // Readable names in public/build — css/app.css, js/admin.js,
        // js/site.js — instead of app-QWdAwHX-.css. Browsers still get a
        // fresh copy after every build: AppServiceProvider adds ?v=<content
        // hash> to each URL.
        rollupOptions: {
            output: {
                entryFileNames: 'js/[name].js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: ({ names }) => (names?.[0] ?? '').endsWith('.css')
                    ? 'css/[name][extname]'
                    : 'assets/[name][extname]',
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
