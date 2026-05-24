import {
    defineConfig,
    minimal2023Preset as preset,
} from '@vite-pwa/assets-generator/config';

/**
 * Source images for the PWA icon set.
 *
 * - `transparent`: full-color SVG used to derive the standard PWA icons
 *   (pwa-64, pwa-192, pwa-512) and the Apple touch icon. The amber
 *   rounded-square in the SVG matches the dark-mode sidebar-primary token
 *   (oklch(73.902% 0.15403 77.923) ≈ #DF9C00).
 * - `maskable`: same source as `transparent` — the rounded-square already
 *   covers the safe area required by Android adaptive icons.
 * - `monochrome`: black-on-transparent silhouette used for the Android
 *   themed icon (the OS recolors it at runtime).
 *
 * Regenerate the PNGs after editing the SVG sources:
 *
 *     npm run generate-pwa-assets
 */
export default defineConfig({
    preset,
    images: {
        transparent: 'resources/pwa/icon.svg',
        maskable: 'resources/pwa/icon.svg',
        monochrome: 'resources/pwa/icon-monochrome.svg',
    },
});
