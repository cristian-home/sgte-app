/// <reference types="google.maps" />

import { useMap } from '@vis.gl/react-google-maps';
import { useEffect } from 'react';
import { toLatLng } from '@/lib/gps-map';
import type { ActiveService } from '@/types/gps-map';

/**
 * Pans/zooms the map to a single service when it's picked in the
 * services panel — builds a bounds from that service's location,
 * origin, destination, and route. A no-op when nothing is selected or
 * the selected service has nothing plottable. Renders nothing; must be
 * mounted inside a `<Map>` so `useMap()` resolves.
 */
export function FocusService({
    selectedId,
    services,
}: {
    selectedId: number | null;
    services: ActiveService[];
}) {
    const map = useMap();

    useEffect(() => {
        if (!map || selectedId === null) return;
        const service = services.find((s) => s.service_id === selectedId);
        if (!service) return;

        const bounds = new google.maps.LatLngBounds();
        let count = 0;

        if (service.location) {
            bounds.extend({
                lat: service.location.latitude,
                lng: service.location.longitude,
            });
            count++;
        }
        if (service.origin) {
            bounds.extend(toLatLng(service.origin));
            count++;
        }
        if (service.destination) {
            bounds.extend(toLatLng(service.destination));
            count++;
        }
        if (service.route) {
            for (const p of service.route) {
                bounds.extend(toLatLng(p));
                count++;
            }
        }

        if (count === 0) return;

        if (bounds.getNorthEast().equals(bounds.getSouthWest())) {
            // Every plotted point coincides — fitBounds would slam to the
            // max zoom, so pan and pick a street-level zoom instead.
            map.panTo(bounds.getCenter());
            map.setZoom(15);
        } else {
            map.fitBounds(bounds, 64);
        }
    }, [map, selectedId, services]);

    return null;
}
