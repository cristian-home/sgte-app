import { Head, router } from '@inertiajs/react';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
} from '@tanstack/react-table';
import { ChevronLeft, ChevronRight, Download, PlayCircle } from 'lucide-react';
import { useState } from 'react';
import { execute as dayStatusExecute } from '@/actions/App/Http/Controllers/DayStatusController';
import {
    index as daySummaryIndex,
    exportMethod as daySummaryExport,
} from '@/actions/App/Http/Controllers/DaySummaryController';
import { index as ganttIndex } from '@/actions/App/Http/Controllers/GanttController';
import { show as serviceShow } from '@/actions/App/Http/Controllers/ServiceController';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { columns } from './columns';
import type { BreadcrumbItem, DayStatus, Service } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Resumen del Día', href: daySummaryIndex().url },
];

interface Props {
    services: Service[];
    dayStatus: (DayStatus & { executor?: { id: number; name: string } }) | null;
    summary: {
        total: number;
        closed: number;
        open: number;
        with_incidents: number;
        third_party: number;
        pending_reassignment: number;
    };
    date: string;
    canExecuteDay: boolean;
}

function addDays(dateStr: string, days: number): string {
    const d = new Date(dateStr + 'T12:00:00');
    d.setDate(d.getDate() + days);
    return d.toISOString().slice(0, 10);
}

function formatDateEs(dateStr: string): string {
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('es-CO', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatExecutedAt(ts: string | null): string {
    if (!ts) return '';
    const d = new Date(ts);
    return d.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function DaySummaryIndex({
    services,
    dayStatus,
    summary,
    date,
    canExecuteDay,
}: Props) {
    'use no memo';
    const [executing, setExecuting] = useState(false);
    const isExecuted = dayStatus?.status === 'executed';

    const table = useReactTable({
        data: services,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    function navigate(newDate: string) {
        router.get(
            daySummaryIndex().url,
            { date: newDate },
            { preserveState: true, preserveScroll: true },
        );
    }

    function handleExecuteDay() {
        if (!dayStatus) return;
        setExecuting(true);
        router.post(
            dayStatusExecute(dayStatus.id).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setExecuting(false),
            },
        );
    }

    function handleExport() {
        window.location.href = daySummaryExport({ query: { date } }).url;
    }

    const canExecute =
        canExecuteDay &&
        dayStatus !== null &&
        !isExecuted &&
        summary.open === 0 &&
        summary.total > 0;

    const showExecuteButton =
        canExecuteDay && dayStatus !== null && !isExecuted;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Resumen del Día" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {/* Header */}
                <div className="flex flex-wrap items-center gap-3">
                    <div className="flex items-center gap-1">
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => navigate(addDays(date, -1))}
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-8 w-8"
                            onClick={() => navigate(addDays(date, 1))}
                        >
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    </div>

                    <Input
                        type="date"
                        value={date}
                        onChange={(e) => {
                            if (e.target.value) navigate(e.target.value);
                        }}
                        className="h-8 w-auto"
                    />

                    <span className="text-sm font-medium capitalize">
                        {formatDateEs(date)}
                    </span>

                    <div className="ml-auto flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-8"
                            asChild
                        >
                            <a href={ganttIndex({ query: { date } }).url}>
                                Ver Gantt
                            </a>
                        </Button>

                        {dayStatus ? (
                            <Badge
                                className={
                                    isExecuted
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300'
                                        : 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300'
                                }
                            >
                                {isExecuted ? 'Ejecutado' : 'Proyectado'}
                            </Badge>
                        ) : (
                            <Badge variant="secondary">Sin Datos</Badge>
                        )}
                    </div>
                </div>

                {/* Executed banner */}
                {isExecuted && dayStatus?.executor && (
                    <div className="rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                        Ejecutado por {dayStatus.executor.name} el{' '}
                        {formatExecutedAt(dayStatus.executed_at)}
                    </div>
                )}

                {/* Executive Summary */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-6">
                            <div className="text-center">
                                <p className="text-2xl font-bold">
                                    {summary.total}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Total Servicios
                                </p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-green-600">
                                    {summary.closed}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Cerrados
                                </p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-orange-600">
                                    {summary.open}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Abiertos
                                </p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-yellow-600">
                                    {summary.with_incidents}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Con Novedades
                                </p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-blue-600">
                                    {summary.third_party}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Vehículos 3ros
                                </p>
                            </div>
                            <div className="text-center">
                                <p className="text-2xl font-bold text-destructive">
                                    {summary.pending_reassignment}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Pend. reasignación
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Pendientes de reasignación (REQ-012) */}
                {summary.pending_reassignment > 0 && (
                    <Card className="border-destructive/40">
                        <CardContent className="space-y-3 pt-6">
                            <div className="flex items-center gap-2">
                                <h2 className="text-base font-semibold text-destructive">
                                    Pendientes de reasignación
                                </h2>
                                <Badge variant="destructive">
                                    {summary.pending_reassignment}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Servicios declinados por el conductor antes del
                                inicio. Asigne otro conductor o cierre el
                                servicio para continuar con el día.
                            </p>
                            <ul className="divide-y rounded-md border">
                                {services
                                    .filter(
                                        (s) =>
                                            s.driver_declined_at !== null &&
                                            s.service_status === 'open',
                                    )
                                    .map((s) => (
                                        <li
                                            key={s.id}
                                            className="flex cursor-pointer items-center justify-between px-3 py-2 text-sm hover:bg-muted/50"
                                            onClick={() =>
                                                router.get(
                                                    serviceShow(s.id).url,
                                                )
                                            }
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {s.vehicle?.plate ?? '—'} ·{' '}
                                                    {s.driver?.first_name}{' '}
                                                    {s.driver?.first_lastname}
                                                </p>
                                                {s.driver_decline_reason && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            s.driver_decline_reason
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                            <span className="text-xs text-muted-foreground">
                                                {s.planned_start_local}
                                            </span>
                                        </li>
                                    ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}

                {/* Services Table */}
                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            {table.getHeaderGroups().map((headerGroup) => (
                                <TableRow key={headerGroup.id}>
                                    {headerGroup.headers.map((header) => (
                                        <TableHead key={header.id}>
                                            {header.isPlaceholder
                                                ? null
                                                : flexRender(
                                                      header.column.columnDef
                                                          .header,
                                                      header.getContext(),
                                                  )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {table.getRowModel().rows.length ? (
                                table.getRowModel().rows.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        className="cursor-pointer hover:bg-muted/50"
                                        onClick={() =>
                                            router.get(
                                                serviceShow(row.original.id)
                                                    .url,
                                            )
                                        }
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id}>
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={columns.length}
                                        className="h-24 text-center"
                                    >
                                        Sin servicios para esta fecha.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Action Bar */}
                <div className="flex items-center gap-2">
                    {showExecuteButton && (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <span>
                                        <AlertDialog>
                                            <AlertDialogTrigger asChild>
                                                <Button
                                                    disabled={
                                                        !canExecute || executing
                                                    }
                                                >
                                                    <PlayCircle className="mr-2 size-4" />
                                                    Ejecutar Día
                                                </Button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>
                                                        Ejecutar Día
                                                    </AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        ¿Está seguro que desea
                                                        ejecutar el día? Esta
                                                        acción bloqueará la
                                                        edición de los
                                                        servicios.
                                                    </AlertDialogDescription>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>
                                                        Cancelar
                                                    </AlertDialogCancel>
                                                    <AlertDialogAction
                                                        onClick={
                                                            handleExecuteDay
                                                        }
                                                    >
                                                        Ejecutar
                                                    </AlertDialogAction>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    </span>
                                </TooltipTrigger>
                                {summary.open > 0 && (
                                    <TooltipContent>
                                        Para ejecutar el día, todos los
                                        servicios deben estar cerrados.
                                    </TooltipContent>
                                )}
                            </Tooltip>
                        </TooltipProvider>
                    )}

                    <Button variant="outline" onClick={handleExport}>
                        <Download className="mr-2 size-4" />
                        Exportar CSV
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
