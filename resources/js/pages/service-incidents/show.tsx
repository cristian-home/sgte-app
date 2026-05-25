import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, Pencil } from 'lucide-react';
import { IncidentSeverityPill } from '@/components/incidents/incident-severity-pill';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dateFormatter, parseDueDate } from '@/lib/document-status';
import contracts from '@/routes/contracts';
import serviceIncidents from '@/routes/service-incidents';
import services from '@/routes/services';
import thirdParties from '@/routes/third-parties';
import vehicles from '@/routes/vehicles';

import type { BreadcrumbItem } from '@/types';
import type {
    Contract,
    IncidentType,
    Service,
    ServiceIncident,
    ThirdParty,
    Vehicle,
} from '@/types/models';

type ShowServiceIncident = Pick<
    ServiceIncident,
    | 'id'
    | 'service_id'
    | 'incident_type_id'
    | 'description'
    | 'registrar_id'
    | 'is_driver_report'
    | 'reported_at'
    | 'affects_billing'
    | 'additional_value'
> & {
    service?:
        | (Pick<
              Service,
              'id' | 'service_date' | 'vehicle_id' | 'contract_id'
          > & {
              vehicle?: Pick<Vehicle, 'id' | 'plate'> | null;
              contract?:
                  | (Pick<Contract, 'id' | 'contract_number'> & {
                        third_party?: Pick<
                            ThirdParty,
                            | 'id'
                            | 'is_natural_person'
                            | 'first_name'
                            | 'first_lastname'
                            | 'company_name'
                        > | null;
                    })
                  | null;
          })
        | null;
    incident_type?: Pick<
        IncidentType,
        'id' | 'code' | 'name' | 'severity' | 'affects_billing_default'
    > | null;
    registrar?: { id: number; name: string } | null;
};

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

const reportedAtFormatter = new Intl.DateTimeFormat('es-CO', {
    dateStyle: 'long',
    timeStyle: 'short',
});

function formatDate(date: string | null): string {
    const parsed = parseDueDate(date);
    if (parsed === null) {
        return '—';
    }
    return dateFormatter.format(parsed);
}

function formatReportedAt(reportedAt: string | null): string {
    if (!reportedAt) return '—';
    // Phase 1 fix: `reported_at` is now an ISO string. Defensive
    // fallback parses Unix integers from any legacy log entries.
    const parsed = new Date(reportedAt);
    if (!Number.isNaN(parsed.getTime())) {
        return reportedAtFormatter.format(parsed);
    }
    const ms = Number(reportedAt) * 1000;
    if (Number.isNaN(ms) || ms <= 0) return '—';
    return reportedAtFormatter.format(new Date(ms));
}

function customerName(
    tp: NonNullable<ShowServiceIncident['service']>['contract'] extends infer C
        ? C extends { third_party?: infer TP }
            ? TP
            : null
        : null,
): string {
    if (!tp) return '—';
    if (tp.is_natural_person) {
        return (
            [tp.first_name, tp.first_lastname]
                .filter(Boolean)
                .join(' ')
                .trim() || '—'
        );
    }
    return tp.company_name ?? '—';
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <div className="font-medium">{children}</div>
        </div>
    );
}

export default function ServiceIncidentsShow({
    serviceIncident,
}: {
    serviceIncident: ShowServiceIncident;
}) {
    const service = serviceIncident.service ?? null;
    const contract = service?.contract ?? null;
    const thirdParty = contract?.third_party ?? null;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Novedades', href: serviceIncidents.index().url },
        {
            title: serviceIncident.incident_type?.name ?? 'Novedad',
            href: '#',
        },
    ];

    const additionalValueFormatted =
        serviceIncident.additional_value !== null &&
        serviceIncident.additional_value !== undefined
            ? currencyFormatter.format(Number(serviceIncident.additional_value))
            : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={serviceIncident.incident_type?.name ?? 'Novedad'} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header card */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <AlertTriangle className="size-8 text-muted-foreground" />
                                <div>
                                    <CardTitle className="text-2xl">
                                        {serviceIncident.incident_type?.name ??
                                            'Novedad'}
                                    </CardTitle>
                                    <p className="text-sm text-muted-foreground">
                                        Reportada el{' '}
                                        {formatReportedAt(
                                            serviceIncident.reported_at,
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <IncidentSeverityPill
                                    severity={
                                        serviceIncident.incident_type
                                            ?.severity ?? null
                                    }
                                />
                                {serviceIncident.is_driver_report && (
                                    <Badge variant="outline">Conductor</Badge>
                                )}
                                <Button asChild size="sm" variant="outline">
                                    <Link
                                        href={
                                            serviceIncidents.edit(
                                                serviceIncident.id,
                                            ).url
                                        }
                                    >
                                        <Pencil className="mr-1 size-4" />
                                        Editar
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Descripción */}
                <Card>
                    <CardHeader>
                        <CardTitle>Descripción</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {serviceIncident.description ? (
                            <p className="text-sm whitespace-pre-wrap">
                                {serviceIncident.description}
                            </p>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Sin descripción.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Servicio */}
                <Card>
                    <CardHeader>
                        <CardTitle>Servicio</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {service ? (
                            <div className="space-y-4">
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div className="grid gap-4 md:grid-cols-4">
                                        <Field label="Fecha">
                                            {formatDate(service.service_date)}
                                        </Field>
                                        <Field label="Vehículo">
                                            <span className="font-mono">
                                                {service.vehicle?.plate ?? '—'}
                                            </span>
                                        </Field>
                                        <Field label="Contrato">
                                            <span className="font-mono">
                                                {contract?.contract_number ??
                                                    '—'}
                                            </span>
                                        </Field>
                                        <Field label="Cliente">
                                            {customerName(thirdParty)}
                                        </Field>
                                    </div>
                                    <Button asChild size="sm" variant="outline">
                                        <Link
                                            href={services.show(service.id).url}
                                        >
                                            Ver servicio
                                        </Link>
                                    </Button>
                                </div>
                                <div className="flex flex-wrap gap-2 border-t pt-4 text-sm">
                                    {service.vehicle && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link
                                                href={
                                                    vehicles.show(
                                                        service.vehicle.id,
                                                    ).url
                                                }
                                            >
                                                Ver vehículo
                                            </Link>
                                        </Button>
                                    )}
                                    {contract && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link
                                                href={
                                                    contracts.show(contract.id)
                                                        .url
                                                }
                                            >
                                                Ver contrato
                                            </Link>
                                        </Button>
                                    )}
                                    {thirdParty && (
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link
                                                href={
                                                    thirdParties.show(
                                                        thirdParty.id,
                                                    ).url
                                                }
                                            >
                                                Ver tercero
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Sin servicio asociado.
                            </p>
                        )}
                    </CardContent>
                </Card>

                {/* Registrado */}
                <Card>
                    <CardHeader>
                        <CardTitle>Registrado</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="Registrador">
                                {serviceIncident.registrar?.name ?? '—'}
                            </Field>
                            <Field label="Fecha de Reporte">
                                {formatReportedAt(serviceIncident.reported_at)}
                            </Field>
                            <Field label="Origen">
                                <Badge
                                    variant={
                                        serviceIncident.is_driver_report
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {serviceIncident.is_driver_report
                                        ? 'Conductor'
                                        : 'Operador'}
                                </Badge>
                            </Field>
                        </div>
                    </CardContent>
                </Card>

                {/* Impacto en Facturación */}
                <Card>
                    <CardHeader>
                        <CardTitle>Impacto en Facturación</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {serviceIncident.affects_billing ? (
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <Badge variant="destructive">
                                    Afecta facturación
                                </Badge>
                                {additionalValueFormatted !== null && (
                                    <div className="text-right">
                                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                            Valor Adicional
                                        </p>
                                        <p className="text-xl font-bold tabular-nums">
                                            {additionalValueFormatted}
                                        </p>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Sin impacto en facturación.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
