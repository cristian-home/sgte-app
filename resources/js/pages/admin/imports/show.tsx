import { Form, Head, Link, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    Clock,
    Download,
    FileX,
    Loader2,
    RotateCw,
    Trash2,
    XCircle,
} from 'lucide-react';
import { useEffect } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DataImportStatus,
    DataImportStatusLabel,
} from '@/enums/DataImportStatus';
import { DataImportTypeLabel } from '@/enums/DataImportType';
import AppLayout from '@/layouts/app-layout';
import type { DataImportType } from '@/enums/DataImportType';
import type { BreadcrumbItem } from '@/types';

interface ImportRow {
    id: number;
    type: DataImportType;
    original_filename: string;
    status: DataImportStatus;
    dry_run: boolean;
    update_existing: boolean;
    rows_total: number | null;
    rows_processed: number;
    rows_created: number;
    rows_updated: number;
    rows_skipped: number;
    rows_errored: number;
    error_message: string | null;
    started_at: string | null;
    completed_at: string | null;
    files_purged_at: string | null;
    errors_path: string | null;
    path: string | null;
    created_at: string;
    user: { id: number; name: string; email: string } | null;
}

const breadcrumbs = (id: number): BreadcrumbItem[] => [
    { title: 'Administración', href: '#' },
    { title: 'Importaciones', href: '/admin/imports' },
    { title: `#${id}`, href: `/admin/imports/${id}` },
];

function StatusBadge({ status }: { status: DataImportStatus }) {
    const variant: 'default' | 'secondary' | 'destructive' | 'outline' =
        status === DataImportStatus.Completed
            ? 'default'
            : status === DataImportStatus.Failed
              ? 'destructive'
              : status === DataImportStatus.Processing
                ? 'secondary'
                : 'outline';
    const Icon =
        status === DataImportStatus.Completed
            ? CheckCircle2
            : status === DataImportStatus.Failed
              ? XCircle
              : status === DataImportStatus.Processing
                ? Loader2
                : Clock;
    const animate =
        status === DataImportStatus.Processing ? 'animate-spin' : '';
    return (
        <Badge variant={variant} className="text-base">
            <Icon className={`mr-1 size-4 ${animate}`} />
            {DataImportStatusLabel[status]}
        </Badge>
    );
}

function ProgressBar({ value, label }: { value: number; label: string }) {
    return (
        <div className="flex flex-col gap-1">
            <div className="h-3 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className="h-full bg-primary transition-all"
                    style={{ width: `${value}%` }}
                />
            </div>
            <p className="text-xs text-muted-foreground">{label}</p>
        </div>
    );
}

export default function ImportsShow({ import: imp }: { import: ImportRow }) {
    const isTerminal =
        imp.status === DataImportStatus.Completed ||
        imp.status === DataImportStatus.Failed;
    const isPurged = imp.files_purged_at !== null;
    const hasFiles = !isPurged && imp.path !== null;
    const canRetryAsReal =
        imp.status === DataImportStatus.Completed && imp.dry_run && hasFiles;

    const { start, stop } = usePoll(
        2000,
        { only: ['import'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isTerminal) {
            stop();
        } else {
            start();
        }
        return () => stop();
    }, [isTerminal, start, stop]);

    const pct =
        imp.rows_total && imp.rows_total > 0
            ? Math.round((imp.rows_processed / imp.rows_total) * 100)
            : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs(imp.id)}>
            <Head title={`Carga #${imp.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <div className="flex flex-row items-center justify-between gap-4">
                            <div>
                                <CardTitle>
                                    Carga #{imp.id} —{' '}
                                    {DataImportTypeLabel[imp.type] ?? imp.type}
                                </CardTitle>
                                <p className="text-sm text-muted-foreground">
                                    {imp.original_filename}
                                </p>
                            </div>
                            <StatusBadge status={imp.status} />
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <span className="text-muted-foreground">
                                Subido por:
                            </span>{' '}
                            {imp.user?.email ?? '—'}
                        </div>
                        <div>
                            <span className="text-muted-foreground">
                                Subido:
                            </span>{' '}
                            {new Date(imp.created_at).toLocaleString('es-CO')}
                        </div>
                        {imp.started_at && (
                            <div>
                                <span className="text-muted-foreground">
                                    Inicio:
                                </span>{' '}
                                {new Date(imp.started_at).toLocaleString(
                                    'es-CO',
                                )}
                            </div>
                        )}
                        {imp.completed_at && (
                            <div>
                                <span className="text-muted-foreground">
                                    Finalizado:
                                </span>{' '}
                                {new Date(imp.completed_at).toLocaleString(
                                    'es-CO',
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {imp.dry_run && (
                    <Alert className="border-amber-500/40 bg-amber-500/10">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Modo dry-run</AlertTitle>
                        <AlertDescription>
                            Solo validación — no se persistió ningún registro.
                        </AlertDescription>
                    </Alert>
                )}

                {isPurged && (
                    <Alert>
                        <FileX className="size-4" />
                        <AlertTitle>Archivos eliminados</AlertTitle>
                        <AlertDescription>
                            Los archivos de esta carga ya fueron purgados (más
                            de 90 días o purga manual). El histórico se
                            conserva.
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Progreso</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {imp.status === DataImportStatus.Queued && (
                            <p className="text-sm text-muted-foreground">
                                En cola…
                            </p>
                        )}
                        {imp.status === DataImportStatus.Processing &&
                            imp.rows_total === null && (
                                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Loader2 className="size-4 animate-spin" />
                                    Contando filas en el archivo…
                                </p>
                            )}
                        {imp.status === DataImportStatus.Processing &&
                            imp.rows_total !== null && (
                                <ProgressBar
                                    value={pct}
                                    label={`${imp.rows_processed} / ${imp.rows_total} (${pct}%)`}
                                />
                            )}
                        {isTerminal && (
                            <div className="grid gap-2 sm:grid-cols-4">
                                <Stat
                                    label="Creados"
                                    value={imp.rows_created}
                                />
                                <Stat
                                    label="Actualizados"
                                    value={imp.rows_updated}
                                />
                                <Stat
                                    label="Saltados"
                                    value={imp.rows_skipped}
                                />
                                <Stat
                                    label="Errados"
                                    value={imp.rows_errored}
                                    tone={
                                        imp.rows_errored > 0
                                            ? 'destructive'
                                            : undefined
                                    }
                                />
                            </div>
                        )}
                    </CardContent>
                </Card>

                {imp.status === DataImportStatus.Failed &&
                    imp.error_message && (
                        <Alert variant="destructive">
                            <XCircle className="size-4" />
                            <AlertTitle>Error</AlertTitle>
                            <AlertDescription className="font-mono text-xs">
                                {imp.error_message}
                            </AlertDescription>
                        </Alert>
                    )}

                {isTerminal && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Acciones</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-wrap gap-2">
                            {hasFiles && (
                                <Button asChild variant="outline">
                                    <a
                                        href={`/admin/imports/${imp.id}/download/source`}
                                        download
                                    >
                                        <Download className="mr-1 size-4" />
                                        Descargar archivo original
                                    </a>
                                </Button>
                            )}
                            {hasFiles && imp.errors_path && (
                                <Button asChild variant="outline">
                                    <a
                                        href={`/admin/imports/${imp.id}/download/errors`}
                                        download
                                    >
                                        <Download className="mr-1 size-4" />
                                        Descargar errores
                                    </a>
                                </Button>
                            )}
                            {canRetryAsReal && (
                                <Form
                                    action="/admin/imports"
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    <input
                                        type="hidden"
                                        name="from_import_id"
                                        value={imp.id}
                                    />
                                    {imp.update_existing && (
                                        <input
                                            type="hidden"
                                            name="update_existing"
                                            value="1"
                                        />
                                    )}
                                    <Button type="submit" variant="default">
                                        <RotateCw className="mr-1 size-4" />
                                        Reintentar como import real
                                    </Button>
                                </Form>
                            )}
                            {hasFiles && (
                                <Form
                                    action={`/admin/imports/${imp.id}/files`}
                                    method="delete"
                                    options={{ preserveScroll: true }}
                                >
                                    <Button
                                        type="submit"
                                        variant="ghost"
                                        onClick={(e) => {
                                            if (
                                                !confirm(
                                                    '¿Eliminar archivos de esta carga? La fila histórica se conserva.',
                                                )
                                            ) {
                                                e.preventDefault();
                                            }
                                        }}
                                    >
                                        <Trash2 className="mr-1 size-4 text-destructive" />
                                        Eliminar archivos
                                    </Button>
                                </Form>
                            )}
                            <Button asChild variant="ghost">
                                <Link href="/admin/imports">
                                    <ArrowLeft className="mr-1 size-4" />
                                    Volver
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

function Stat({
    label,
    value,
    tone,
}: {
    label: string;
    value: number;
    tone?: 'destructive';
}) {
    return (
        <div
            className={`rounded border p-3 ${tone === 'destructive' && value > 0 ? 'border-destructive/40 bg-destructive/5' : ''}`}
        >
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className="font-mono text-2xl">{value}</p>
        </div>
    );
}
