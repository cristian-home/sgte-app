import { Link } from '@inertiajs/react';
import { show as servicesShow } from '@/actions/App/Http/Controllers/ServiceController';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatEventTime } from '@/lib/datetime';

export interface DayServiceEntry {
    id: number;
    service_date_local: string;
    origin_address: string | null;
    destination_address: string | null;
    unit_value: string;
    service_status: string;
    planned_start_at: string | null;
    timezone: string;
    contract: { id: number; contract_number: string } | null;
    vehicle: { id: number; plate: string } | null;
    driver: {
        id: number;
        first_name: string;
        first_lastname: string;
    } | null;
}

interface DayServicesTableProps {
    date: string;
    services: DayServiceEntry[];
}

const statusLabels: Record<string, string> = {
    open: 'Abierto',
    closed: 'Cerrado',
};

function formatCurrency(value: string | number): string {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0,
    }).format(Number(value));
}

function formatPlannedStart(at: string | null, timezone: string): string {
    return formatEventTime(at, timezone) || '—';
}

export default function DayServicesTable({
    date,
    services,
}: DayServicesTableProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Servicios del {date}</CardTitle>
            </CardHeader>
            <CardContent>
                {services.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No hay servicios para este día.
                    </p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Hora</TableHead>
                                <TableHead>Ruta</TableHead>
                                <TableHead>Vehículo</TableHead>
                                <TableHead>Conductor</TableHead>
                                <TableHead>Valor</TableHead>
                                <TableHead>Estado</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {services.map((service) => (
                                <TableRow key={service.id}>
                                    <TableCell>
                                        {formatPlannedStart(
                                            service.planned_start_at,
                                            service.timezone,
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <Link
                                            href={servicesShow(service.id).url}
                                            className="text-primary hover:underline"
                                        >
                                            <div className="flex flex-col">
                                                <span className="font-medium">
                                                    {service.origin_address ??
                                                        '—'}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    →{' '}
                                                    {service.destination_address ??
                                                        '—'}
                                                </span>
                                            </div>
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        {service.vehicle?.plate ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        {service.driver
                                            ? `${service.driver.first_name} ${service.driver.first_lastname}`
                                            : '—'}
                                    </TableCell>
                                    <TableCell className="tabular-nums">
                                        {formatCurrency(service.unit_value)}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant={
                                                service.service_status ===
                                                'open'
                                                    ? 'secondary'
                                                    : 'default'
                                            }
                                        >
                                            {statusLabels[
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
    );
}
