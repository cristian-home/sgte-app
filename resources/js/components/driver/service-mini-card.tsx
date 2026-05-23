import { ArrowRight, Clock, MapPin, Play, Truck } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ServiceStatusLabel } from '@/enums/ServiceStatus';
import { formatEventTime } from '@/lib/datetime';
import { cn } from '@/lib/utils';
import type { Service } from '@/types';

function formatServiceTime(at: string | null, timezone: string): string {
    return formatEventTime(at, timezone) || '—';
}

function municipalityName(municipality?: { name: string } | null): string {
    return municipality?.name ?? '—';
}

function clientName(service: Service): string {
    const tp = service.contract?.third_party;
    if (!tp) {
        return 'Sin contrato';
    }
    return (
        tp.company_name ||
        `${tp.first_name ?? ''} ${tp.first_lastname ?? ''}`.trim() ||
        'Sin contrato'
    );
}

interface ServiceMiniCardProps {
    service: Service;
    onOpen: () => void;
    className?: string;
}

export function ServiceMiniCard({
    service,
    onOpen,
    className,
}: ServiceMiniCardProps) {
    const hasStarted = !!service.actual_start_at;
    const hasEnded = !!service.actual_end_at;
    const incidentCount = service.service_incidents_count ?? 0;
    const isClosed = service.service_status === 'closed';

    return (
        <Card
            role="button"
            tabIndex={0}
            data-dusk={`service-mini-${service.id}`}
            onClick={onOpen}
            onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onOpen();
                }
            }}
            className={cn(
                'cursor-pointer transition-colors hover:bg-muted/40',
                className,
            )}
        >
            <CardHeader className="pb-2">
                <div className="flex items-center justify-between gap-2">
                    <CardTitle className="text-base">
                        <span className="flex items-center gap-2">
                            <Truck className="size-4" />
                            {service.vehicle?.plate ?? '—'}
                        </span>
                    </CardTitle>
                    <div className="flex items-center gap-2">
                        {incidentCount > 0 && (
                            <Badge variant="destructive">
                                {incidentCount} novedad
                                {incidentCount > 1 ? 'es' : ''}
                            </Badge>
                        )}
                        <Badge variant={isClosed ? 'default' : 'secondary'}>
                            {ServiceStatusLabel[
                                service.service_status as keyof typeof ServiceStatusLabel
                            ] ?? service.service_status}
                        </Badge>
                    </div>
                </div>
                <CardDescription>{clientName(service)}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-1.5 text-sm">
                <div className="flex items-start gap-2">
                    <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    <span>
                        {municipalityName(service.origin_municipality)} &rarr;{' '}
                        {municipalityName(service.destination_municipality)}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <Clock className="size-4 shrink-0 text-muted-foreground" />
                    <span>
                        Planificado:{' '}
                        {formatServiceTime(
                            service.planned_start_at,
                            service.timezone,
                        )}{' '}
                        ({service.planned_duration} min)
                    </span>
                </div>
                {hasStarted && (
                    <div className="flex items-center gap-2">
                        <Play className="size-4 shrink-0 text-green-600" />
                        <span>
                            Inicio real:{' '}
                            {formatServiceTime(
                                service.actual_start_at,
                                service.timezone,
                            )}
                            {hasEnded && (
                                <>
                                    {' '}
                                    | Fin:{' '}
                                    {formatServiceTime(
                                        service.actual_end_at,
                                        service.timezone,
                                    )}
                                </>
                            )}
                        </span>
                    </div>
                )}
                <div className="flex justify-end pt-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={(e) => {
                            e.stopPropagation();
                            onOpen();
                        }}
                    >
                        Ver <ArrowRight className="ml-1 size-4" />
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
