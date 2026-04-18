<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Thin wrapper around bacon/bacon-qr-code (already in the project
 * via laravel/fortify's 2FA). Exists so call sites don't carry the
 * four-line rendering boilerplate, and so the choice of backend is
 * centralized — dompdf's SVG support is good enough that we don't
 * need imagick for the FUEC PDF embedding.
 */
class QrCode
{
    /**
     * Render `$payload` as an SVG QR code of the given pixel size.
     * The return value is an SVG document string suitable for
     * embedding in a Blade template (wrap in a data-URI for dompdf
     * if needed, or just echo inline since SVG is supported).
     */
    public static function svg(string $payload, int $size = 200): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd,
        );

        $writer = new Writer($renderer);

        return $writer->writeString($payload);
    }

    /**
     * Render the QR as an SVG data-URI, suitable for the `src` of an
     * `<img>` tag. dompdf honors data URIs reliably.
     */
    public static function dataUri(string $payload, int $size = 200): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(self::svg($payload, $size));
    }
}
