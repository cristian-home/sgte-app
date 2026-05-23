/// <reference types="google.maps" />

import { useMap } from '@vis.gl/react-google-maps';
import { useEffect } from 'react';

/**
 * Draws a route as a native `google.maps.Polyline` — the library ships
 * no declarative `<Polyline>`. Confirmed routes render solid; estimated
 * straight-line fallbacks render dashed (stroke opacity 0 + dash icons).
 *
 * Emphasis: with nothing selected every route renders at its normal
 * weight. Once a service is picked, its route turns bold, fully opaque,
 * and on top, while every other route (`dimmed`) thins out and fades
 * back — so the chosen one clearly stands out. Renders nothing; must be
 * mounted inside a `<Map>` so `useMap()` resolves.
 */
export function RoutePolyline({
    path,
    color,
    confirmed,
    selected,
    dimmed,
}: {
    path: google.maps.LatLngLiteral[];
    color: string;
    confirmed: boolean;
    selected: boolean;
    dimmed: boolean;
}) {
    const map = useMap();

    useEffect(() => {
        if (!map) return;

        const weight = selected ? 7 : dimmed ? 2 : 4;
        const opacity = selected ? 1 : dimmed ? 0.2 : 0.75;

        const polyline = new google.maps.Polyline({
            map,
            path,
            strokeColor: color,
            strokeOpacity: confirmed ? opacity : 0,
            strokeWeight: weight,
            zIndex: selected ? 10 : 1,
            icons: confirmed
                ? undefined
                : [
                      {
                          icon: {
                              path: 'M 0,-1 0,1',
                              strokeOpacity: opacity,
                              strokeWeight: weight,
                              scale: 3,
                          },
                          offset: '0',
                          repeat: '14px',
                      },
                  ],
        });

        return () => {
            polyline.setMap(null);
        };
    }, [map, path, color, confirmed, selected, dimmed]);

    return null;
}
