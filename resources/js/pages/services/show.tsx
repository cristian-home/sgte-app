import { Head, Link, router } from '@inertiajs/react';
import {
    Calendar,
    CircleDot,
    CreditCard,
    DollarSign,
    FileText,
    Hash,
    MapPin,
    Pencil,
    Plus,
    Trash2,
    Truck,
    User,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import ServiceIncidentController from '@/actions/App/Http/Controllers/ServiceIncidentController';
import { Can } from '@/components/can';
import { DeleteConfirmationDialog } from '@/components/delete-confirmation-dialog';
import { ServiceTimelineBar } from '@/components/services/service-timeline-bar';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PaymentMethodLabel } from '@/enums/PaymentMethod';
import { Permission } from '@/enums/Permission';
import { ServiceStatusLabel } from '@/enums/ServiceStatus';
import AppLayout from '@/layouts/app-layout';
import services from '@/routes/services';
import { type BreadcrumbItem } from '@/types';
import type { Service, ServiceIncident } from '@/types/models';

interface DayStatusWithExecutor {
    id: number;
    date: string;
    status: string;
    executor_id: number | null;
    executed_at: string | null;
    executor?: {
        id: number;
        name: string;
    } | null;
}

function formatCurrency(value: string | number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
    }).format(Number(value));
}

function formatDate(date: string): string {
    return new Intl.DateTimeFormat('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(new Date(date));
}

function formatDateTime(date: string): string {
    return new Intl.DateTimeFormat('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date));
}

function formatTime(time: string | null): string {
    if (!time) return '\u2014';
    return time.substring(0, 5);
}

function municipalityDisplay(
    municipality?: { name: string; department?: { name: string } } | null,
    address?: string | null,
): string {
    if (!municipality) return address ?? '\u2014';
    const parts = [municipality.name];
    if (municipality.department)
        parts.push(`(${municipality.department.name})`);
    if (address) parts.push(`\u2014 ${address}`);
    return parts.join(' ');
}

function IconField({
    icon: Icon,
    label,
    children,
}: {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex items-start gap-3">
            <Icon className="mt-0.5 size-5 shrink-0 text-muted-foreground" />
            <div className="space-y-0.5">
                <dt className="text-xs font-medium text-muted-foreground">
                    {label}
                </dt>
                <dd className="text-sm">{children}</dd>
            </div>
        </div>
    );
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-0.5">
            <dt className="text-xs font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}

function formatIncidentTimestamp(reportedAt: string | null): string {
    if (!reportedAt) return '\u2014';
    const ms = Number(reportedAt) * 1000;
    if (isNaN(ms)) return '\u2014';
    return formatDateTime(new Date(ms).toISOString());
}

export default function ServicesShow({
    service,
    dayStatus,
}: {
    service: Service;
    dayStatus?: DayStatusWithExecutor | null;
}) {
    const clientName = service.contract?.third_party
        ? service.contract.third_party.company_name ||
          `${service.contract.third_party.first_name} ${service.contract.third_party.first_lastname}`
        : '';

    const pageTitle = `Detalle de Servicio ${service.contract?.contract_number ?? ''}${clientName ? ` - ${clientName}` : ''}`;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Servicios', href: services.index().url },
        {
            title: service.contract?.contract_number ?? 'Ver',
            href: '#',
        },
    ];

    const driverName = service.driver
        ? `${service.driver.first_name} ${service.driver.first_lastname}`
        : '\u2014';

    const actualDuration =
        service.actual_start_time && service.actual_end_time
            ? (() => {
                  const [sh, sm] = service.actual_start_time
                      .split(':')
                      .map(Number);
                  const [eh, em] = service.actual_end_time
                      .split(':')
                      .map(Number);
                  const diff = eh * 60 + em - (sh * 60 + sm);
                  return diff > 0 ? `${diff} min` : '\u2014';
              })()
            : '\u2014';

    const incidents = service.service_incidents ?? [];
    const [deleteUrl, setDeleteUrl] = useState<string | null>(null);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={pageTitle} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Heading row */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <h1 className="text-2xl font-bold tracking-tight">
                            {pageTitle}
                        </h1>
                        {(service.service_incidents_count ?? 0) > 0 && (
                            <Badge variant="destructive">
                                {service.service_incidents_count} novedad
                                {(service.service_incidents_count ?? 0) > 1
                                    ? 'es'
                                    : ''}
                            </Badge>
                        )}
                    </div>
                    <div className="flex shrink-0 items-center gap-2">
                        <Can permission={Permission.UPDATE_PROJECTED_SERVICES}>
                            <Link href={services.edit(service.id).url}>
                                <Button>Editar</Button>
                            </Link>
                        </Can>
                        <Link href={services.index().url}>
                            <Button variant="outline">Volver</Button>
                        </Link>
                    </div>
                </div>

                {/* Day-executed alert */}
                {dayStatus?.status === 'executed' && (
                    <Alert>
                        <AlertTitle>
                            <Badge variant="secondary">Dia Ejecutado</Badge>
                        </AlertTitle>
                        <AlertDescription>
                            Ejecutado por{' '}
                            {dayStatus.executor?.name ?? 'Usuario'} el{' '}
                            {dayStatus.executed_at
                                ? formatDateTime(
                                      new Date(
                                          Number(dayStatus.executed_at) * 1000,
                                      ).toISOString(),
                                  )
                                : '\u2014'}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Row 1: Datos Generales + Detalle de la Ruta */}
                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Datos Generales del Servicio */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Datos Generales del Servicio</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <IconField icon={Calendar} label="Fecha">
                                    {formatDate(service.service_date)}
                                </IconField>
                                <IconField icon={FileText} label="Contrato">
                                    {service.contract?.contract_number ??
                                        '\u2014'}
                                    {service.contract?.third_party && (
                                        <span className="ml-1 text-muted-foreground">
                                            -{' '}
                                            {service.contract.third_party
                                                .company_name ||
                                                `${service.contract.third_party.first_name} ${service.contract.third_party.first_lastname}`}
                                        </span>
                                    )}
                                </IconField>
                                <IconField icon={Truck} label="Vehiculo">
                                    {service.vehicle?.plate ?? '\u2014'}
                                    {service.vehicle?.is_third_party &&
                                        service.vehicle?.third_party && (
                                            <span className="ml-1 text-muted-foreground">
                                                (Tercero:{' '}
                                                {service.vehicle.third_party
                                                    .company_name ||
                                                    `${service.vehicle.third_party.first_name} ${service.vehicle.third_party.first_lastname}`}
                                                )
                                            </span>
                                        )}
                                </IconField>
                                <IconField icon={User} label="Conductor">
                                    {driverName}
                                </IconField>
                                <IconField icon={MapPin} label="Origen">
                                    {municipalityDisplay(
                                        service.origin_municipality,
                                    )}
                                </IconField>
                                <IconField icon={CircleDot} label="Estado">
                                    <Badge
                                        variant={
                                            service.service_status === 'open'
                                                ? 'secondary'
                                                : 'default'
                                        }
                                    >
                                        {ServiceStatusLabel[
                                            service.service_status as keyof typeof ServiceStatusLabel
                                        ] ?? service.service_status}
                                    </Badge>
                                </IconField>
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Detalle de la Ruta */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Detalle de la Ruta</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="relative flex min-h-[200px] flex-col justify-between rounded-lg border border-dashed border-muted-foreground/20 bg-muted/30 p-4">
                                {/* Origin */}
                                <div className="flex items-start gap-3">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-white">
                                        A
                                    </span>
                                    <div>
                                        <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                            Origen
                                        </p>
                                        <p className="text-sm font-medium">
                                            {municipalityDisplay(
                                                service.origin_municipality,
                                                service.origin_address,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                {/* Connecting line */}
                                <div className="absolute top-14 bottom-14 left-[1.95rem] w-px border-l border-dashed border-muted-foreground/30" />

                                {/* Destination */}
                                <div className="flex items-start gap-3">
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-white">
                                        B
                                    </span>
                                    <div>
                                        <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                            Destino
                                        </p>
                                        <p className="text-sm font-medium">
                                            {municipalityDisplay(
                                                service.destination_municipality,
                                                service.destination_address,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                {/* Map placeholder note */}
                                <p className="absolute right-3 bottom-2 text-[10px] text-muted-foreground/50 italic">
                                    Mapa en desarrollo
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Row 2: Cronograma y Tiempos + Resumen de Facturacion */}
                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Cronograma y Tiempos */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Cronograma y Tiempos</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <ServiceTimelineBar
                                plannedStartTime={service.planned_start_time}
                                plannedDuration={service.planned_duration}
                                actualStartTime={service.actual_start_time}
                                actualEndTime={service.actual_end_time}
                            />
                            <dl className="grid gap-3 sm:grid-cols-2">
                                <Field label="Hora Inicio Planificada">
                                    {formatTime(service.planned_start_time)}
                                </Field>
                                <Field label="Duracion Planificada">
                                    {service.planned_duration} min
                                </Field>
                                <Field label="Hora Inicio Real">
                                    {formatTime(service.actual_start_time)}
                                </Field>
                                <Field label="Hora Fin Real">
                                    {formatTime(service.actual_end_time)}
                                </Field>
                                <Field label="Duracion Real">
                                    {actualDuration}
                                </Field>
                            </dl>
                        </CardContent>
                    </Card>

                    {/* Resumen de Facturacion */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Resumen de Facturacion</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Decorative icons row */}
                            <div className="flex justify-around">
                                <Users className="size-6 text-muted-foreground/50" />
                                <DollarSign className="size-6 text-muted-foreground/50" />
                                <Hash className="size-6 text-muted-foreground/50" />
                                <CreditCard className="size-6 text-muted-foreground/50" />
                            </div>
                            {/* Billing fields */}
                            <dl className="grid grid-cols-4 gap-3 text-center">
                                <Field label="Grupo de Facturacion">
                                    {service.billing_group ?? '\u2014'}
                                </Field>
                                <Field label="Valor Unitario">
                                    {formatCurrency(service.unit_value)}
                                </Field>
                                <Field label="Cantidad">
                                    {service.quantity}
                                </Field>
                                <Field label="Metodo de Pago">
                                    <Badge variant="outline">
                                        {PaymentMethodLabel[
                                            service.payment_method as keyof typeof PaymentMethodLabel
                                        ] ?? service.payment_method}
                                    </Badge>
                                </Field>
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                {/* Row 3: Incidentes */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <CardTitle>Incidentes</CardTitle>
                                {incidents.length > 0 && (
                                    <Badge variant="secondary">
                                        {incidents.length}
                                    </Badge>
                                )}
                            </div>
                            <Can permission={Permission.CREATE_INCIDENTS}>
                                <Link
                                    href={`${ServiceIncidentController.create.url()}?service_id=${service.id}`}
                                >
                                    <Button size="sm">
                                        <Plus className="mr-1 size-4" />
                                        Registrar Novedad
                                    </Button>
                                </Link>
                            </Can>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {incidents.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                No se han registrado incidentes para este
                                servicio.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left text-xs text-muted-foreground">
                                            <th className="pb-2 font-medium">
                                                Tipo
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Descripcion
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Fecha Reporte
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Registrador
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Facturacion
                                            </th>
                                            <th className="pb-2 font-medium">
                                                Acciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {incidents.map(
                                            (incident: ServiceIncident) => (
                                                <tr key={incident.id}>
                                                    <td className="py-2 pr-3">
                                                        {incident.incident_type
                                                            ?.name ?? '\u2014'}
                                                    </td>
                                                    <td
                                                        className="max-w-[200px] truncate py-2 pr-3"
                                                        title={
                                                            incident.description
                                                        }
                                                    >
                                                        {incident.description}
                                                    </td>
                                                    <td className="py-2 pr-3 whitespace-nowrap">
                                                        {formatIncidentTimestamp(
                                                            incident.reported_at,
                                                        )}
                                                    </td>
                                                    <td className="py-2 pr-3">
                                                        {incident.registrar
                                                            ?.name ?? '\u2014'}
                                                    </td>
                                                    <td className="py-2 pr-3">
                                                        {incident.affects_billing && (
                                                            <Badge variant="destructive">
                                                                Afecta
                                                            </Badge>
                                                        )}
                                                    </td>
                                                    <td className="py-2">
                                                        <div className="flex items-center gap-1">
                                                            <Can
                                                                permission={
                                                                    Permission.UPDATE_INCIDENTS
                                                                }
                                                            >
                                                                <Link
                                                                    href={ServiceIncidentController.edit.url(
                                                                        incident.id,
                                                                    )}
                                                                >
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="size-7"
                                                                    >
                                                                        <Pencil className="size-3.5" />
                                                                    </Button>
                                                                </Link>
                                                            </Can>
                                                            <Can
                                                                permission={
                                                                    Permission.DELETE_INCIDENTS
                                                                }
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="size-7 text-destructive hover:text-destructive"
                                                                    onClick={() =>
                                                                        setDeleteUrl(
                                                                            ServiceIncidentController.destroy.url(
                                                                                incident.id,
                                                                            ),
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="size-3.5" />
                                                                </Button>
                                                            </Can>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ),
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
            <DeleteConfirmationDialog
                open={deleteUrl !== null}
                onOpenChange={(open) => !open && setDeleteUrl(null)}
                deleteUrl={deleteUrl ?? ''}
            />
        </AppLayout>
    );
}
