import { ExternalLink, Navigation } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

interface OpenInMapsButtonProps {
    /** "lat,lng" string, or null when the destination is unknown. */
    destination: string | null;
    /** Optional origin. When null, the navigation app uses the device's current location. */
    origin?: string | null;
    className?: string;
    variant?: 'default' | 'outline' | 'secondary' | 'ghost';
    /**
     * Force the trigger into a disabled state regardless of the
     * destination coordinates. When `true` (or when the destination
     * cannot be parsed) the button renders but the dropdown does not
     * open — the caller can still place it in a layout grid without
     * leaving a hole.
     */
    disabled?: boolean;
}

function parseCoordinates(value: string | null | undefined): string | null {
    if (!value) {
        return null;
    }
    return /^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/.test(value.trim())
        ? value.trim()
        : null;
}

/**
 * Driver-facing "Ver en el mapa" button. Renders a dropdown of
 * navigation deep links (Google Maps, Waze, Apple Maps) that the OS
 * opens directly in the corresponding app via App Links / Universal
 * Links. We render the picker ourselves because the OS no longer
 * surfaces an "open with" chooser for verified https://… URLs.
 */
export default function OpenInMapsButton({
    destination,
    origin,
    className,
    variant = 'default',
    disabled = false,
}: OpenInMapsButtonProps) {
    const dest = parseCoordinates(destination);
    const orig = parseCoordinates(origin);
    const effectivelyDisabled = disabled || !dest;

    const googleUrl = orig
        ? `https://www.google.com/maps/dir/?api=1&origin=${orig}&destination=${dest}&travelmode=driving`
        : `https://www.google.com/maps/dir/?api=1&destination=${dest}&travelmode=driving`;

    const wazeUrl = `https://www.waze.com/ul?ll=${dest}&navigate=yes`;

    const appleUrl = orig
        ? `https://maps.apple.com/?saddr=${orig}&daddr=${dest}&dirflg=d`
        : `https://maps.apple.com/?daddr=${dest}&dirflg=d`;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild disabled={effectivelyDisabled}>
                <Button
                    type="button"
                    variant={variant}
                    disabled={effectivelyDisabled}
                    className={cn('flex-1', className)}
                >
                    <Navigation className="mr-1 size-4" />
                    Ver en el mapa
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuItem asChild>
                    <a
                        href={googleUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLink className="size-4" />
                        Google Maps
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a href={wazeUrl} target="_blank" rel="noopener noreferrer">
                        <ExternalLink className="size-4" />
                        Waze
                    </a>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <a
                        href={appleUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <ExternalLink className="size-4" />
                        Apple Maps
                    </a>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
