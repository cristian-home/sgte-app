import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        // PWA registration. We only ship the service worker in production builds
        // (devOptions disabled) and we register it manually from `resources/js/app.tsx`
        // to keep control over update-available UX.
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: false,
            strategies: 'generateSW',
            // Emit manifest + service worker into the same /build directory the
            // Laravel Vite plugin uses, so they live alongside hashed assets.
            // The manifest is then referenced as `/build/manifest.webmanifest`
            // from `resources/views/app.blade.php`.
            manifest: {
                name: 'SGTE',
                short_name: 'SGTE',
                description:
                    'Sistema de Gestión de Transporte Especial (SGTE)',
                lang: 'es',
                scope: '/',
                start_url: '/',
                display: 'standalone',
                // Light background matches `oklch(1 0 0)` from resources/css/app.css.
                background_color: '#ffffff',
                // Theme color matches `--primary` (`oklch(0.216 0.006 56.043)`)
                // — the deep stone tone used across the chrome.
                theme_color: '#292524',
                icons: [
                    // TODO(pwa-icons): generate these PNGs from the brand assets.
                    // Required: 192x192, 512x512, and a 512x512 maskable variant.
                    // Drop them into `public/icons/` with the exact filenames
                    // below. `public/apple-touch-icon.png` and `public/favicon.svg`
                    // are the closest existing references.
                    {
                        src: '/icons/pwa-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/pwa-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/pwa-maskable-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                // Never serve a cached navigation response for auth, API, websocket
                // or operator dashboards. These must always hit the network so
                // CSRF tokens, redirects and live data stay correct.
                navigateFallbackDenylist: [
                    /^\/login/,
                    /^\/logout/,
                    /^\/broadcasting/,
                    /^\/api\//,
                    /^\/horizon/,
                    /^\/telescope/,
                ],
                // Precache only hashed build assets — HTML pages must remain
                // network-first so role/permission gates stay accurate.
                globPatterns: ['**/*.{js,css,woff2,svg,png,ico}'],
                cleanupOutdatedCaches: true,
                runtimeCaching: [
                    {
                        // Hashed Vite assets are immutable; serve from cache forever.
                        urlPattern: ({ url }) =>
                            url.pathname.startsWith('/build/'),
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'app-shell',
                            expiration: {
                                maxEntries: 200,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    {
                        // Bunny font CSS — keep fresh in background but serve fast.
                        urlPattern: ({ url }) =>
                            url.origin === 'https://fonts.bunny.net',
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'bunny-fonts-css',
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                    {
                        // Bunny font files (woff2) — long-lived, hashed URLs.
                        urlPattern: ({ url }) =>
                            url.origin === 'https://fonts.bunnycdn.com' ||
                            (url.hostname.endsWith('bunny.net') &&
                                url.pathname.endsWith('.woff2')),
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'bunny-fonts-files',
                            expiration: {
                                maxEntries: 30,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                            cacheableResponse: {
                                statuses: [0, 200],
                            },
                        },
                    },
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    ssr: {
        noExternal: true,
    },
    esbuild: {
        jsx: 'automatic',
    },
});
