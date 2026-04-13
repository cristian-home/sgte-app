import { Head, Link, router } from '@inertiajs/react';
import { AlertCircle, Clock, Flag, MapPin, Play, Truck } from 'lucide-react';
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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Service } from '@/types';

interface Driver {
    id: number;
    first_name: string;
    first_lastname: string;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mis Servicios', href: '#' }];

function formatTime(time: string | null): string {
    if (!time) return '\u2014';
    return time.substring(0, 5);
}

function municipalityName(municipality?: { name: string } | null): string {
    return municipality?.name ?? '\u2014';
}

export default function DriverDashboard({
    services,
    driver,
}: {
    services: Service[];
    driver?: Driver | null;
}) {
    function confirmStart(serviceId: number) {
        router.post(`/driver/services/${serviceId}/confirm-start`);
    }

    function confirmEnd(serviceId: number) {
        router.post(`/driver/services/${serviceId}/confirm-end`);
    }

    const today = new Intl.DateTimeFormat('es-CO', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(new Date());

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis Servicios" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Mis Servicios
                    </h1>
                    <p className="text-sm text-muted-foreground capitalize">
                        {today}
                    </p>
                </div>

                {!driver && (
                    <Card>
                        <CardContent className="py-8 text-center">
                            <p className="text-muted-foreground">
                                Su cuenta no está vinculada a un conductor.
                                Contacte al administrador.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {driver && services.length === 0 && (
                    <Card>
                        <CardContent className="py-8 text-center">
                            <p className="text-muted-foreground">
                                No tiene servicios asignados para hoy.
                            </p>
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    {services.map((service) => {
                        const hasStarted = !!service.actual_start_time;
                        const hasEnded = !!service.actual_end_time;
                        const incidentCount =
                            service.service_incidents_count ?? 0;

                        return (
                            <Card key={service.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-base">
                                            <div className="flex items-center gap-2">
                                                <Truck className="size-4" />
                                                {service.vehicle?.plate ??
                                                    '\u2014'}
                                            </div>
                                        </CardTitle>
                                        <div className="flex items-center gap-2">
                                            {incidentCount > 0 && (
                                                <Badge variant="destructive">
                                                    {incidentCount} novedad
                                                    {incidentCount > 1
                                                        ? 'es'
                                                        : ''}
                                                </Badge>
                                            )}
                                            <Badge
                                                variant={
                                                    service.service_status ===
                                                    'closed'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {ServiceStatusLabel[
                                                    service.service_status as keyof typeof ServiceStatusLabel
                                                ] ?? service.service_status}
                                            </Badge>
                                        </div>
                                    </div>
                                    <CardDescription>
                                        {service.contract?.third_party
                                            ?.company_name ||
                                            (service.contract?.third_party
                                                ? `${service.contract.third_party.first_name} ${service.contract.third_party.first_lastname}`
                                                : 'Sin contrato')}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {/* Route */}
                                    <div className="flex items-start gap-2 text-sm">
                                        <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <div>
                                            <p>
                                                {municipalityName(
                                                    service.origin_municipality,
                                                )}{' '}
                                                →{' '}
                                                {municipalityName(
                                                    service.destination_municipality,
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Schedule */}
                                    <div className="flex items-center gap-2 text-sm">
                                        <Clock className="size-4 shrink-0 text-muted-foreground" />
                                        <span>
                                            Planificado:{' '}
                                            {formatTime(
                                                service.planned_start_time,
                                            )}{' '}
                                            ({service.planned_duration} min)
                                        </span>
                                    </div>

                                    {/* Actual times */}
                                    {hasStarted && (
                                        <div className="flex items-center gap-2 text-sm">
                                            <Play className="size-4 shrink-0 text-green-600" />
                                            <span>
                                                Inicio real:{' '}
                                                {formatTime(
                                                    service.actual_start_time,
                                                )}
                                            </span>
                                            {hasEnded && (
                                                <span className="ml-2">
                                                    | Fin:{' '}
                                                    {formatTime(
                                                        service.actual_end_time,
                                                    )}
                                                </span>
                                            )}
                                        </div>
                                    )}

                                    {/* Action buttons */}
                                    <div className="flex flex-wrap gap-2 pt-2">
                                        {!hasStarted && (
                                            <Button
                                                className="flex-1"
                                                onClick={() =>
                                                    confirmStart(service.id)
                                                }
                                            >
                                                <Play className="mr-1 size-4" />
                                                Confirmar Inicio
                                            </Button>
                                        )}
                                        {hasStarted && !hasEnded && (
                                            <Button
                                                className="flex-1"
                                                variant="secondary"
                                                onClick={() =>
                                                    confirmEnd(service.id)
                                                }
                                            >
                                                <Flag className="mr-1 size-4" />
                                                Confirmar Fin
                                            </Button>
                                        )}
                                        {hasStarted && hasEnded && (
                                            <p className="flex-1 py-2 text-center text-sm text-muted-foreground">
                                                Servicio completado
                                            </p>
                                        )}
                                        <Button
                                            variant="outline"
                                            asChild
                                            className="flex-1"
                                        >
                                            <Link
                                                href={`/service-incidents/create?service_id=${service.id}`}
                                            >
                                                <AlertCircle className="mr-1 size-4" />
                                                Registrar Novedad
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
