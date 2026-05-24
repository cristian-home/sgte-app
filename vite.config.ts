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
                // Both colors match `--primary` (`oklch(0.216 0.006 56.043)`)
                // — the deep stone tone used across the chrome. Keeping
                // background_color equal to theme_color avoids a white flash
                // on the Android splash screen before the app boots.
                background_color: '#292524',
                theme_color: '#292524',
                // `icons` are intentionally omitted here — `pwaAssets` below
                // loads `pwa-assets.config.ts` and overrides the manifest's
                // icon entries with the generator's output (`pwa-64x64.png`,
                // `pwa-192x192.png`, `pwa-512x512.png`,
                // `maskable-icon-512x512.png`, `apple-touch-icon.png`).
            },
            // Generate PWA icons from `resources/pwa/icon*.svg` via the
            // companion `pwa-assets.config.ts`. Run
            // `npm run generate-pwa-assets` after editing the SVG sources;
            // generated PNGs live in `public/` and are committed.
            pwaAssets: {
                config: true,
                overrideManifestIcons: true,
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
