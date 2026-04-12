import { Head, Link } from '@inertiajs/react';
import ServiceIncidentController from '@/actions/App/Http/Controllers/ServiceIncidentController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import services from '@/routes/services';
import type { BreadcrumbItem, ServiceIncident } from '@/types';

function formatTimestamp(reportedAt: string | null): string {
    if (!reportedAt) return '\u2014';
    const ms = Number(reportedAt) * 1000;
    if (isNaN(ms)) return '\u2014';
    return new Intl.DateTimeFormat('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(ms));
}

function formatCurrency(value: string | number | null): string {
    if (value === null || value === undefined) return '\u2014';
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
    }).format(Number(value));
}

export default function ServiceIncidentsShow({
    serviceIncident,
}: {
    serviceIncident: ServiceIncident;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Novedades',
            href: ServiceIncidentController.index.url(),
        },
        { title: 'Detalle', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Detalle de Novedad" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>
                                    {serviceIncident.incident_type?.name ??
                                        'Novedad'}
                                </CardTitle>
                                <CardDescription>
                                    Registrada el{' '}
                                    {formatTimestamp(
                                        serviceIncident.reported_at,
                                    )}
                                </CardDescription>
                            </div>
                            <div className="flex gap-2">
                                <Link
                                    href={ServiceIncidentController.edit.url(
                                        serviceIncident.id,
                                    )}
                                >
                                    <Button variant="outline">Editar</Button>
                                </Link>
                                <Link
                                    href={
                                        services.show(
                                            serviceIncident.service_id,
                                        ).url
                                    }
                                >
                                    <Button variant="outline">
                                        Ver Servicio
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">
                                Descripción
                            </p>
                            <p className="mt-1">
                                {serviceIncident.description}
                            </p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Registrado por
                                </p>
                                <p>
                                    {serviceIncident.registrar?.name ??
                                        '\u2014'}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Afecta Facturación
                                </p>
                                {serviceIncident.affects_billing ? (
                                    <Badge variant="destructive">
                                        Si -{' '}
                                        {formatCurrency(
                                            serviceIncident.additional_value,
                                        )}
                                    </Badge>
                                ) : (
                                    <p>No</p>
                                )}
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Reporte de Conductor
                                </p>
                                <p>
                                    {serviceIncident.is_driver_report
                                        ? 'Si'
                                        : 'No'}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
