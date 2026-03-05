import { Head, Link } from '@inertiajs/react';
import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PaymentMethodLabel } from '@/enums/PaymentMethod';
import { Permission } from '@/enums/Permission';
import { ServiceStatusLabel } from '@/enums/ServiceStatus';
import AppLayout from '@/layouts/app-layout';
import services from '@/routes/services';
import { type BreadcrumbItem } from '@/types';
import type { Service } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Servicios', href: services.index().url },
    { title: 'Ver', href: '#' },
];

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

function formatTime(time: string | null): string {
    if (!time) return '—';
    return time.substring(0, 5);
}

function municipalityDisplay(
    municipality?: { name: string; department?: { name: string } } | null,
    address?: string | null,
): string {
    if (!municipality) return address ?? '—';
    const parts = [municipality.name];
    if (municipality.department)
        parts.push(`(${municipality.department.name})`);
    if (address) parts.push(`— ${address}`);
    return parts.join(' ');
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-1">
            <dt className="text-sm font-medium text-muted-foreground">
                {label}
            </dt>
            <dd className="text-sm">{children}</dd>
        </div>
    );
}

export default function ServicesShow({ service }: { service: Service }) {
    const driverName = service.driver
        ? `${service.driver.first_name} ${service.driver.first_lastname}`
        : '—';

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
                  return diff > 0 ? `${diff} min` : '—';
              })()
            : '—';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ver Servicio" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <CardTitle>Detalle del Servicio</CardTitle>
                            {(service.service_incidents_count ?? 0) > 0 && (
                                <Badge variant="destructive">
                                    {service.service_incidents_count} novedad
                                    {(service.service_incidents_count ?? 0) > 1
                                        ? 'es'
                                        : ''}
                                </Badge>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-8">
                        {/* Datos del Servicio */}
                        <div>
                            <h3 className="mb-4 text-lg font-semibold">
                                Datos del Servicio
                            </h3>
                            <dl className="grid gap-4 md:grid-cols-3">
                                <Field label="Fecha del Servicio">
                                    {formatDate(service.service_date)}
                                </Field>
                                <Field label="Contrato">
                                    {service.contract?.contract_number ?? '—'}
                                    {service.contract?.third_party && (
                                        <span className="ml-1 text-muted-foreground">
                                            -{' '}
                                            {service.contract.third_party
                                                .company_name ||
                                                `${service.contract.third_party.first_name} ${service.contract.third_party.first_lastname}`}
                                        </span>
                                    )}
                                </Field>
                                <Field label="Estado">
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
                                </Field>
                                <Field label="Vehiculo">
                                    {service.vehicle?.plate ?? '—'}
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
                                </Field>
                                <Field label="Conductor">{driverName}</Field>
                            </dl>
                        </div>

                        {/* Origen y Destino */}
                        <div>
                            <h3 className="mb-4 text-lg font-semibold">
                                Origen y Destino
                            </h3>
                            <dl className="grid gap-4 md:grid-cols-2">
                                <Field label="Origen">
                                    {municipalityDisplay(
                                        service.origin_municipality,
                                        service.origin_address,
                                    )}
                                </Field>
                                <Field label="Destino">
                                    {municipalityDisplay(
                                        service.destination_municipality,
                                        service.destination_address,
                                    )}
                                </Field>
                            </dl>
                        </div>

                        {/* Horarios */}
                        <div>
                            <h3 className="mb-4 text-lg font-semibold">
                                Horarios
                            </h3>
                            <dl className="grid gap-4 md:grid-cols-3">
                                <Field label="Hora Inicio Planificada">
                                    {formatTime(service.planned_start_time)}
                                </Field>
                                <Field label="Duracion Planificada">
                                    {service.planned_duration} min
                                </Field>
                                <div />
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
                        </div>

                        {/* Facturacion */}
                        <div>
                            <h3 className="mb-4 text-lg font-semibold">
                                Facturacion
                            </h3>
                            <dl className="grid gap-4 md:grid-cols-4">
                                <Field label="Grupo de Facturacion">
                                    {service.billing_group ?? '—'}
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
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-4">
                            <Can
                                permission={
                                    Permission.UPDATE_PROJECTED_SERVICES
                                }
                            >
                                <Link href={services.edit(service.id).url}>
                                    <Button>Editar</Button>
                                </Link>
                            </Can>
                            <Link href={services.index().url}>
                                <Button variant="outline">Volver</Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
