import {
    defineConfig,
    minimal2023Preset as preset,
} from '@vite-pwa/assets-generator/config';

/**
 * Source image for the PWA icon set.
 *
 * `public/pwa-icon.svg` is the amber rounded-square containing the SGTE logo
 * (matches the dark-mode sidebar-primary token, oklch(73.902% 0.15403 77.923)
 * ≈ #DF9C00). The generator emits PNGs alongside it in `public/`:
 *
 *   - pwa-64x64.png, pwa-192x192.png, pwa-512x512.png
 *   - maskable-icon-512x512.png  (auto-padded to the maskable safe area)
 *   - apple-touch-icon-180x180.png
 *   - favicon.ico
 *
 * Regenerate after editing the source SVG:
 *
 *     npm run generate-pwa-assets
 */
export default defineConfig({
    preset,
    images: ['public/pwa-icon.svg'],
});
