import { Head, Link } from '@inertiajs/react';
import { Database, Eye, FileSpreadsheet, Plus, Upload } from 'lucide-react';
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
import {
    DataImportStatus,
    DataImportStatusLabel,
} from '@/enums/DataImportStatus';
import { DataImportTypeLabel } from '@/enums/DataImportType';
import AppLayout from '@/layouts/app-layout';
import type { DataImportType } from '@/enums/DataImportType';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedData } from '@/types/pagination';

interface ImportRow {
    id: number;
    type: DataImportType;
    original_filename: string;
    status: DataImportStatus;
    dry_run: boolean;
    rows_total: number | null;
    rows_processed: number;
    rows_created: number;
    rows_updated: number;
    rows_skipped: number;
    rows_errored: number;
    created_at: string;
    completed_at: string | null;
    user: { id: number; name: string; email: string } | null;
}

interface TypeOption {
    value: DataImportType;
    label: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Importaciones', href: '/admin/imports' },
];

const TEMPLATE_TYPES: { type: string; label: string }[] = [
    { type: 'users', label: 'Usuarios' },
    { type: 'third-parties', label: 'Terceros' },
    { type: 'drivers', label: 'Conductores' },
    { type: 'vehicles', label: 'Vehículos' },
];

const REFERENCE_CATALOGS: { catalog: string; label: string }[] = [
    { catalog: 'eps', label: 'EPS' },
    { catalog: 'pension-funds', label: 'Fondos de Pensiones' },
    { catalog: 'severance-funds', label: 'Fondos de Cesantías' },
    { catalog: 'municipalities', label: 'Ciudades' },
    { catalog: 'departments', label: 'Departamentos' },
    { catalog: 'document-types', label: 'Tipos de Documento' },
    { catalog: 'incident-types', label: 'Tipos de Novedad' },
];

function statusVariant(
    status: DataImportStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case DataImportStatus.Completed:
            return 'default';
        case DataImportStatus.Failed:
            return 'destructive';
        case DataImportStatus.Processing:
            return 'secondary';
        case DataImportStatus.Queued:
        default:
            return 'outline';
    }
}

function summary(row: ImportRow): string {
    return `+${row.rows_created} ~${row.rows_updated} ⊘${row.rows_skipped} ✗${row.rows_errored}`;
}

export default function ImportsIndex({
    imports,
}: {
    imports: PaginatedData<ImportRow>;
    types: TypeOption[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Importaciones" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card className="border-amber-500/30 bg-amber-500/5">
                    <CardContent className="pt-4 text-sm">
                        Los archivos se eliminan automáticamente 90 días después
                        de completarse. El histórico (sin archivos) se conserva
                        indefinidamente.
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileSpreadsheet className="size-5" />
                                Plantillas
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2">
                            {TEMPLATE_TYPES.map(({ type, label }) => (
                                <Button
                                    asChild
                                    key={type}
                                    variant="outline"
                                    className="justify-start"
                                >
                                    <a
                                        href={`/admin/imports/templates/${type}`}
                                        download
                                    >
                                        <Upload className="mr-2 size-4 rotate-180" />
                                        Descargar plantilla — {label}
                                    </a>
                                </Button>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Database className="size-5" />
                                Catálogos de referencia
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {REFERENCE_CATALOGS.map(({ catalog, label }) => (
                                <Button
                                    asChild
                                    key={catalog}
                                    variant="ghost"
                                    size="sm"
                                >
                                    <a
                                        href={`/admin/imports/reference/${catalog}`}
                                        download
                                    >
                                        {label}
                                    </a>
                                </Button>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Historial</CardTitle>
                        <Button asChild>
                            <Link href="/admin/imports/create">
                                <Plus className="mr-1 size-4" />
                                Nueva carga
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Archivo</TableHead>
                                    <TableHead>Estado</TableHead>
                                    <TableHead>Resumen</TableHead>
                                    <TableHead>Usuario</TableHead>
                                    <TableHead className="text-right">
                                        Acciones
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {imports.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={7}
                                            className="text-center text-muted-foreground"
                                        >
                                            Aún no hay imports.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {imports.data.map((row) => (
                                    <TableRow key={row.id}>
                                        <TableCell className="font-mono text-xs">
                                            {new Date(
                                                row.created_at,
                                            ).toLocaleString('es-CO', {
                                                dateStyle: 'short',
                                                timeStyle: 'short',
                                            })}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {DataImportTypeLabel[
                                                    row.type
                                                ] ?? row.type}
                                            </Badge>
                                            {row.dry_run && (
                                                <Badge
                                                    variant="secondary"
                                                    className="ml-1"
                                                >
                                                    Dry-run
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell
                                            className="max-w-xs truncate"
                                            title={row.original_filename}
                                        >
                                            {row.original_filename}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant={statusVariant(
                                                    row.status,
                                                )}
                                            >
                                                {DataImportStatusLabel[
                                                    row.status
                                                ] ?? row.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs">
                                            {summary(row)}
                                        </TableCell>
                                        <TableCell>
                                            {row.user ? (
                                                <span className="text-xs">
                                                    {row.user.email}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                asChild
                                            >
                                                <Link
                                                    href={`/admin/imports/${row.id}`}
                                                >
                                                    <Eye className="size-4" />
                                                    <span className="sr-only">
                                                        Ver detalle
                                                    </span>
                                                </Link>
                                            </Button>
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
