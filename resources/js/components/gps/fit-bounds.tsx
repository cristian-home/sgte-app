/// <reference types="google.maps" />

import { useMap } from '@vis.gl/react-google-maps';
import { useEffect } from 'react';
import { toLatLng } from '@/lib/gps-map';
import type { ActiveService } from '@/types/gps-map';

/**
 * Imperatively fits the map viewport to every plotted point — runs
 * whenever the dataset changes. Renders nothing; must be mounted inside
 * a `<Map>` so `useMap()` resolves.
 */
export function FitBounds({ services }: { services: ActiveService[] }) {
    const map = useMap();

    useEffect(() => {
        if (!map) return;
        const bounds = new google.maps.LatLngBounds();
        let count = 0;

        for (const s of services) {
            if (s.location) {
                bounds.extend({
                    lat: s.location.latitude,
                    lng: s.location.longitude,
                });
                count++;
            }
            if (s.origin) {
                bounds.extend(toLatLng(s.origin));
                count++;
            }
            if (s.destination) {
                bounds.extend(toLatLng(s.destination));
                count++;
            }
            if (s.route) {
                for (const p of s.route) {
                    bounds.extend(toLatLng(p));
                    count++;
                }
            }
        }

        if (count > 0) {
            map.fitBounds(bounds, 48);
        }
    }, [map, services]);

    return null;
}
