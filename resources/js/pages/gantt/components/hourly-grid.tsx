import { router, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import {
    create as servicesCreate,
    edit as servicesEdit,
} from '@/actions/App/Http/Controllers/ServiceController';
import { viewerToday } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import {
    computeVehicleDocStatus,
    formatHour,
    GANTT_START_HOUR,
    HOUR_LABELS,
    serviceBarPosition,
    TOTAL_HOURS,
} from '../gantt-utils';
import ServiceBar from './service-bar';
import VehicleSidebarItem from './vehicle-sidebar-item';
import type { Service, Vehicle } from '@/types/models';

interface HourlyGridProps {
    vehicles: Vehicle[];
    servicesByVehicle: Record<number, Service[]>;
    date: string;
    canCreateServices: boolean;
    isExecuted: boolean;
}

export default function HourlyGrid({
    vehicles,
    servicesByVehicle,
    date,
    canCreateServices,
    isExecuted,
}: HourlyGridProps) {
    const sharedConfig = usePage().props.config as
        | { operation_tz?: string }
        | undefined;
    const operationTz = sharedConfig?.operation_tz ?? 'America/Bogota';
    const today = useMemo(() => viewerToday(operationTz), [operationTz]);

    const vehicleStatuses = useMemo(() => {
        const map: Record<
            number,
            { isBlocked: boolean; hasWarning: boolean; expiredDocs: string[] }
        > = {};
        for (const v of vehicles) {
            map[v.id] = computeVehicleDocStatus(v, today);
        }
        return map;
    }, [vehicles, today]);

    function handleEmptyCellClick(
        vehicle: Vehicle,
        e: React.MouseEvent<HTMLDivElement>,
    ) {
        const status = vehicleStatuses[vehicle.id];
        if (status?.isBlocked || isExecuted || !canCreateServices) return;

        const rect = e.currentTarget.getBoundingClientRect();
        const relativeX = e.clientX - rect.left;
        const hourOffset = (relativeX / rect.width) * TOTAL_HOURS;
        const hour = GANTT_START_HOUR + hourOffset;
        const timeStr = formatHour(Math.floor(hour));

        router.get(servicesCreate().url, {
            vehicle_id: vehicle.id,
            planned_start_time: timeStr,
            service_date: date,
        });
    }

    function handleServiceClick(serviceId: number) {
        router.get(servicesEdit(serviceId).url);
    }

    // Compute current time position for the indicator line
    const currentTimePosition = useMemo(() => {
        if (date !== today) return null;
        const now = new Date();
        const currentHour = now.getHours() + now.getMinutes() / 60;
        if (
            currentHour < GANTT_START_HOUR ||
            currentHour > GANTT_START_HOUR + TOTAL_HOURS
        )
            return null;
        return ((currentHour - GANTT_START_HOUR) / TOTAL_HOURS) * 100;
    }, [date, today]);

    return (
        <div className="min-w-225">
            {/* Header row */}
            <div className="flex border-b bg-muted/50">
                <div className="sticky left-0 z-10 flex w-45 shrink-0 items-center border-r bg-background px-2 py-1.5">
                    <span className="text-xs font-medium text-muted-foreground">
                        Vehículo
                    </span>
                </div>
                <div className="flex flex-1">
                    {HOUR_LABELS.map((label) => (
                        <div
                            key={label}
                            className="flex-1 border-l px-1 py-1.5 text-center text-[10px] text-muted-foreground"
                        >
                            {label}
                        </div>
                    ))}
                </div>
            </div>

            {/* Vehicle rows */}
            {vehicles.length === 0 && (
                <div className="flex items-center justify-center py-12 text-sm text-muted-foreground">
                    No hay vehículos activos para mostrar.
                </div>
            )}

            {vehicles.map((vehicle) => {
                const status = vehicleStatuses[vehicle.id];
                const services = servicesByVehicle[vehicle.id] ?? [];
                const clickable =
                    canCreateServices && !status?.isBlocked && !isExecuted;

                return (
                    <div
                        key={vehicle.id}
                        className={cn(
                            'flex border-b',
                            status?.isBlocked &&
                                'bg-neutral-100 dark:bg-neutral-800/50',
                        )}
                    >
                        {/* Sidebar cell */}
                        <div className="sticky left-0 z-10 flex w-45 shrink-0 items-center border-r bg-background">
                            <VehicleSidebarItem
                                vehicle={vehicle}
                                isBlocked={status?.isBlocked ?? false}
                                hasWarning={status?.hasWarning ?? false}
                                expiredDocs={status?.expiredDocs ?? []}
                            />
                        </div>

                        {/* Timeline cell */}
                        <div
                            className={cn(
                                'relative flex-1',
                                clickable ? 'cursor-cell' : 'cursor-default',
                            )}
                            style={{ minHeight: '36px' }}
                            onClick={(e) => handleEmptyCellClick(vehicle, e)}
                        >
                            {/* Grid lines */}
                            {HOUR_LABELS.map((_, i) => (
                                <div
                                    key={i}
                                    className="absolute top-0 bottom-0 border-l border-border/40"
                                    style={{
                                        left: `${(i / TOTAL_HOURS) * 100}%`,
                                    }}
                                />
                            ))}

                            {/* Current time indicator */}
                            {currentTimePosition !== null && (
                                <div
                                    className="absolute top-0 bottom-0 z-10 w-px bg-red-500"
                                    style={{ left: `${currentTimePosition}%` }}
                                />
                            )}

                            {/* Service bars */}
                            {services.map((service) => {
                                const pos = serviceBarPosition(
                                    service.planned_start_at,
                                    service.planned_duration,
                                    service.timezone,
                                );
                                if (!pos) return null;
                                return (
                                    <ServiceBar
                                        key={service.id}
                                        service={service}
                                        position={pos}
                                        onClick={handleServiceClick}
                                    />
                                );
                            })}
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
