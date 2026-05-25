/// <reference types="google.maps" />

/**
 * One active service as shipped by the GPS map controller via Inertia —
 * mirrors the payload assembled in `VehicleLocationMapController::index`.
 */
export interface CoordPair {
    latitude: number;
    longitude: number;
}

export interface ActiveService {
    service_id: number;
    vehicle_plate: string | null;
    driver_name: string | null;
    location: {
        latitude: number;
        longitude: number;
        accuracy: number | null;
        is_manual: boolean;
        recorded_at: string | null;
    } | null;
    origin: CoordPair | null;
    destination: CoordPair | null;
    route: CoordPair[] | null;
    route_distance_m: number | null;
    route_duration_s: number | null;
}

/**
 * A route ready to be drawn on the map — derived from an `ActiveService`
 * inside the GPS map page (origin/destination are required, the path
 * falls back to a straight line when no real geometry was fetched).
 */
export interface RouteData {
    service_id: number;
    color: string;
    path: google.maps.LatLngLiteral[];
    origin: google.maps.LatLngLiteral;
    destination: google.maps.LatLngLiteral;
    /** True when a real fetched route is drawn (solid); false for the estimated straight line (dashed). */
    confirmed: boolean;
}

/**
 * The subset of an `ActiveService` a `VehicleMarker` needs to render its
 * pin + InfoWindow. Built once the location is known to be non-null.
 */
export interface MarkerService {
    service_id: number;
    vehicle_plate: string | null;
    driver_name: string | null;
    position: google.maps.LatLngLiteral;
    is_manual: boolean;
    recorded_at: string | null;
    route_distance_m: number | null;
    route_duration_s: number | null;
}
