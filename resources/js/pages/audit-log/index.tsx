import { Head } from '@inertiajs/react';
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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface ActivityRow {
    id: number;
    log_name: string | null;
    description: string;
    event: string | null;
    subject_type: string | null;
    subject_id: number | null;
    causer: { id: number; name: string; email: string } | null;
    created_at: string | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Auditoría', href: '/audit-log' },
];

const dateTimeFormatter = new Intl.DateTimeFormat('es-CO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
});

function formatTimestamp(iso: string | null): string {
    if (!iso) return '—';
    return dateTimeFormatter.format(new Date(iso));
}

export default function AuditLogIndex({
    activities,
}: {
    activities: ActivityRow[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Auditoría" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Registro de Auditoría</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Usuario</TableHead>
                                    <TableHead>Acción</TableHead>
                                    <TableHead>Entidad</TableHead>
                                    <TableHead>Descripción</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {activities.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="text-center text-muted-foreground"
                                        >
                                            No hay actividad registrada.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {activities.map((activity) => (
                                    <TableRow key={activity.id}>
                                        <TableCell className="font-mono text-xs whitespace-nowrap">
                                            {formatTimestamp(
                                                activity.created_at,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {activity.causer?.name ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {activity.event && (
                                                <Badge variant="outline">
                                                    {activity.event}
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap">
                                            {activity.subject_type
                                                ? `${activity.subject_type}#${activity.subject_id ?? '—'}`
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="max-w-md truncate">
                                            {activity.description}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
