import { Badge } from '@/components/ui/badge';
import { formatDistance, formatDuration, serviceColor } from '@/lib/gps-map';
import { cn } from '@/lib/utils';
import type { ActiveService } from '@/types/gps-map';

/**
 * One clickable row in the services panel. The color swatch uses the
 * exact same hue (`serviceColor`) as the service's route polyline,
 * origin dot, and destination ring on the map.
 */
export function ServiceListItem({
    service,
    selected,
    onSelect,
}: {
    service: ActiveService;
    selected: boolean;
    onSelect: (id: number) => void;
}) {
    const distance = formatDistance(service.route_distance_m);
    const duration = formatDuration(service.route_duration_s);
    const routeSummary = [distance, duration].filter(Boolean).join(' · ');

    return (
        <li>
            <button
                type="button"
                data-dusk={`service-item-${service.service_id}`}
                aria-pressed={selected}
                onClick={() => onSelect(service.service_id)}
                className={cn(
                    'flex w-full items-start gap-2.5 rounded-md border border-transparent px-2.5 py-2 text-left transition-colors',
                    'hover:bg-muted/60',
                    selected && 'border-border bg-muted',
                )}
            >
                <span
                    aria-hidden="true"
                    className="mt-1 size-3.5 shrink-0 rounded-full"
                    style={{ background: serviceColor(service.service_id) }}
                />
                <div className="min-w-0 flex-1 space-y-0.5">
                    <div className="flex items-baseline gap-2">
                        <span className="truncate font-mono text-sm font-medium">
                            {service.vehicle_plate ??
                                `Servicio ${service.service_id}`}
                        </span>
                        <span className="shrink-0 text-xs text-muted-foreground">
                            #{service.service_id}
                        </span>
                    </div>
                    <div className="truncate text-xs text-muted-foreground">
                        {service.driver_name ?? 'Sin conductor'}
                    </div>
                    {routeSummary && (
                        <div className="text-xs text-muted-foreground">
                            {routeSummary}
                        </div>
                    )}
                    <div className="pt-0.5">
                        {service.location ? (
                            service.location.is_manual ? (
                                <Badge variant="outline">Manual</Badge>
                            ) : (
                                <Badge>GPS</Badge>
                            )
                        ) : (
                            <span className="text-xs text-muted-foreground italic">
                                Sin ubicación
                            </span>
                        )}
                    </div>
                </div>
            </button>
        </li>
    );
}
