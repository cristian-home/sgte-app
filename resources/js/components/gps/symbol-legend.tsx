import { MapPin } from 'lucide-react';

/**
 * The symbol key — explains the glyphs drawn on the map. Rendered at the
 * bottom of the services panel (it used to be a floating map overlay).
 */
export function SymbolLegend() {
    // Use a neutral foreground color for the example glyphs so the
    // legend doesn't pretend to belong to any particular service —
    // each real service uses its own HSL hue (see the swatches above).
    return (
        <div className="border-t p-3 text-xs">
            <div className="mb-2 font-medium">Símbolos</div>
            <ul className="space-y-1.5 text-muted-foreground">
                <li className="flex items-center gap-2">
                    <span className="inline-block size-3 rounded-full bg-foreground" />
                    <span>Origen</span>
                </li>
                <li className="flex items-center gap-2">
                    <span className="inline-block size-3 rounded-full border-2 border-foreground bg-background" />
                    <span>Destino</span>
                </li>
                <li className="flex items-center gap-2">
                    <MapPin className="size-3.5 fill-blue-500 text-blue-500" />
                    <span>Vehículo (GPS)</span>
                </li>
                <li className="flex items-center gap-2">
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 4"
                        className="h-1 w-4 text-foreground"
                    >
                        <line
                            x1="0"
                            y1="2"
                            x2="24"
                            y2="2"
                            stroke="currentColor"
                            strokeWidth="3"
                            strokeLinecap="round"
                        />
                    </svg>
                    <span>Ruta confirmada</span>
                </li>
                <li className="flex items-center gap-2">
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 4"
                        className="h-1 w-4 text-foreground"
                    >
                        <line
                            x1="0"
                            y1="2"
                            x2="24"
                            y2="2"
                            stroke="currentColor"
                            strokeWidth="3"
                            strokeLinecap="round"
                            strokeDasharray="4 4"
                        />
                    </svg>
                    <span>Ruta estimada</span>
                </li>
            </ul>
        </div>
    );
}
