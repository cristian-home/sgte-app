import { Head, Link, router } from '@inertiajs/react';
import {
    Pencil,
    Send,
    User as UserIcon,
    UserCircle2,
    UserPlus,
} from 'lucide-react';
import { useState } from 'react';
import DriverController from '@/actions/App/Http/Controllers/DriverController';
import { DriverInviteDialog } from '@/components/drivers/driver-invite-dialog';
import { DriverLicensePill } from '@/components/drivers/driver-license-pill';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { dateFormatter, parseDueDate } from '@/lib/document-status';
import drivers from '@/routes/drivers';
import services from '@/routes/services';

import type { BreadcrumbItem } from '@/types';
import type { Driver } from '@/types/models';

// Local shape — does NOT extend Driver because the global Driver
// type uses `relation?: T` (undefined-only) while the show payload
// returns `relation: T | null`. Picking + & relations keeps both
// compatible (matches the vehicles/show.tsx pattern).
type ShowDriver = Pick<
    Driver,
    | 'id'
    | 'identification_number'
    | 'first_name'
    | 'second_name'
    | 'first_lastname'
    | 'second_lastname'
    | 'address'
    | 'phone'
    | 'email'
    | 'license_category'
    | 'timezone'
    | 'license_due_at'
    | 'license_due_date'
    | 'has_social_security'
    | 'active'
> & {
    document_type?: { id: number; code: string; name: string } | null;
    municipality?: {
        id: number;
        name: string;
        department_id: number;
        department?: { id: number; name: string };
    } | null;
    eps?: { id: number; code: string; name: string } | null;
    pension_fund?: { id: number; code: string; name: string } | null;
    severance_fund?: { id: number; code: string; name: string } | null;
    user?: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
    } | null;
};

interface RecentServiceRow {
    id: number;
    service_date: string;
    vehicle?: { id: number; plate: string; internal_code: string } | null;
    contract?: {
        id: number;
        contract_number: string;
        third_party?: {
            id: number;
            company_name: string | null;
            first_name: string | null;
            first_lastname: string | null;
            is_natural_person: boolean;
        } | null;
    } | null;
    service_status: string;
}

const serviceStatusLabels: Record<string, string> = {
    open: 'Abierto',
    closed: 'Cerrado',
};

function formatDate(date: string | null): string {
    const parsed = parseDueDate(date);
    if (parsed === null) {
        return '—';
    }
    return dateFormatter.format(parsed);
}

function fullName(driver: ShowDriver): string {
    return [
        driver.first_name,
        driver.second_name,
        driver.first_lastname,
        driver.second_lastname,
    ]
        .filter(Boolean)
        .join(' ')
        .trim();
}

function recentServiceClient(service: RecentServiceRow): string {
    const tp = service.contract?.third_party;
    if (!tp) return '—';
    if (tp.is_natural_person) {
        return (
            `${tp.first_name ?? ''} ${tp.first_lastname ?? ''}`.trim() || '—'
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
            <p className="font-medium">{children}</p>
        </div>
    );
}

export default function DriversShow({
    driver,
    recentServices,
}: {
    driver: ShowDriver;
    recentServices: RecentServiceRow[];
}) {
    const [inviteOpen, setInviteOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Conductores', href: drivers.index().url },
        { title: fullName(driver) || '#', href: '#' },
    ];

    function handleResend() {
        if (
            !window.confirm('¿Reenviar la invitación al correo del conductor?')
        ) {
            return;
        }
        router.post(
            DriverController.resendInvitation(driver.id).url,
            {},
            { preserveScroll: true },
        );
    }

    const docLabel = driver.document_type
        ? `${driver.document_type.code} ${driver.identification_number}`
        : driver.identification_number;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={fullName(driver) || 'Conductor'} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header card */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <UserCircle2 className="size-8 text-muted-foreground" />
                                <div>
                                    <CardTitle className="text-2xl">
                                        {fullName(driver)}
                                    </CardTitle>
                                    <p className="font-mono text-sm text-muted-foreground">
                                        {docLabel}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge
                                    variant={
                                        driver.active ? 'default' : 'outline'
                                    }
                                >
                                    {driver.active ? 'Activo' : 'Inactivo'}
                                </Badge>
                                <Button asChild size="sm" variant="outline">
                                    <Link href={drivers.edit(driver.id).url}>
                                        <Pencil className="mr-1 size-4" />
                                        Editar
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-center gap-3 text-sm">
                            <UserIcon className="size-4 text-muted-foreground" />
                            <span className="text-muted-foreground">
                                Cuenta de acceso:
                            </span>
                            {driver.user ? (
                                <>
                                    <span className="font-medium">
                                        {driver.user.email}
                                    </span>
                                    <Badge
                                        variant={
                                            driver.user.is_active
                                                ? 'default'
                                                : 'outline'
                                        }
                                    >
                                        {driver.user.is_active
                                            ? 'Cuenta activa'
                                            : 'Cuenta inactiva'}
                                    </Badge>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={handleResend}
                                    >
                                        <Send className="mr-1 size-4" />
                                        Reenviar invitación
                                    </Button>
                                </>
                            ) : (
                                <>
                                    <Badge variant="outline">
                                        Sin cuenta de acceso
                                    </Badge>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setInviteOpen(true)}
                                    >
                                        <UserPlus className="mr-1 size-4" />
                                        Crear cuenta de acceso
                                    </Button>
                                </>
                            )}
                        </div>
                    </CardContent>
                </Card>

                <DriverInviteDialog
                    driverId={driver.id}
                    defaultEmail={driver.email ?? ''}
                    open={inviteOpen}
                    onOpenChange={setInviteOpen}
                />

                {/* Información Personal */}
                <Card>
                    <CardHeader>
                        <CardTitle>Información Personal</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <Field label="Tipo de Documento">
                                {driver.document_type
                                    ? `${driver.document_type.code} — ${driver.document_type.name}`
                                    : '—'}
                            </Field>
                            <Field label="Identificación">
                                {driver.identification_number}
                            </Field>
                            <Field label="Segundo Nombre">
                                {driver.second_name ?? '—'}
                            </Field>
                            <Field label="Segundo Apellido">
                                {driver.second_lastname ?? '—'}
                            </Field>
                            <Field label="Municipio">
                                {driver.municipality
                                    ? `${driver.municipality.name}${
                                          driver.municipality.department
                                              ? ', ' +
                                                driver.municipality.department
                                                    .name
                                              : ''
                                      }`
                                    : '—'}
                            </Field>
                            <Field label="Dirección">{driver.address}</Field>
                            <Field label="Teléfono">{driver.phone}</Field>
                            <Field label="Correo">{driver.email}</Field>
                        </div>
                    </CardContent>
                </Card>

                {/* Licencia y Seguridad Social */}
                <Card>
                    <CardHeader>
                        <CardTitle>Licencia y Seguridad Social</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="Categoría">
                                {driver.license_category}
                            </Field>
                            <Field label="Vencimiento de Licencia">
                                {formatDate(driver.license_due_date)}
                            </Field>
                            <Field label="Estado">
                                <DriverLicensePill driver={driver} />
                            </Field>
                        </div>
                        <div className="flex items-center gap-2 text-sm">
                            <span className="text-muted-foreground">
                                Seguridad social:
                            </span>
                            <Badge
                                variant={
                                    driver.has_social_security
                                        ? 'default'
                                        : 'destructive'
                                }
                            >
                                {driver.has_social_security ? 'Sí' : 'No'}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                {/* Afiliaciones */}
                <Card>
                    <CardHeader>
                        <CardTitle>Afiliaciones</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Field label="EPS">
                                {driver.eps
                                    ? `${driver.eps.name} (${driver.eps.code})`
                                    : '—'}
                            </Field>
                            <Field label="Fondo de Pensiones">
                                {driver.pension_fund
                                    ? `${driver.pension_fund.name} (${driver.pension_fund.code})`
                                    : '—'}
                            </Field>
                            <Field label="Fondo de Cesantías">
                                {driver.severance_fund
                                    ? `${driver.severance_fund.name} (${driver.severance_fund.code})`
                                    : '—'}
                            </Field>
                        </div>
                    </CardContent>
                </Card>

                {/* Servicios Recientes */}
                <Card>
                    <CardHeader>
                        <CardTitle>Servicios Recientes (últimos 5)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentServices.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Sin servicios registrados.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Vehículo</TableHead>
                                        <TableHead>Tercero</TableHead>
                                        <TableHead>Estado</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentServices.map((service) => (
                                        <TableRow key={service.id}>
                                            <TableCell>
                                                <Link
                                                    href={
                                                        services.show(
                                                            service.id,
                                                        ).url
                                                    }
                                                    className="text-primary hover:underline"
                                                >
                                                    {formatDate(
                                                        service.service_date,
                                                    )}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="font-mono">
                                                {service.vehicle?.plate ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {recentServiceClient(service)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        service.service_status ===
                                                        'closed'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {serviceStatusLabels[
                                                        service.service_status
                                                    ] ?? service.service_status}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
