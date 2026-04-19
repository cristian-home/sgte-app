import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useMemo, useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dateFormatter, parseDueDate } from '@/lib/document-status';

export interface ServicePickerRow {
    id: number;
    service_date: string | null;
    unit_value: string | number | null;
    quantity: number | null;
    service_status: string;
    vehicle?: { id: number; plate: string } | null;
    driver?: {
        id: number;
        first_name: string;
        first_lastname: string;
    } | null;
    contract?: { id: number; contract_number: string } | null;
    service_incidents?: Array<{
        id: number;
        affects_billing: boolean;
        additional_value: string | number | null;
    }>;
}

interface ServicePickerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    invoiceId: number;
    candidates: ServicePickerRow[];
    blockedCandidates?: ServicePickerRow[];
}

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

const JUSTIFICATION_MIN = 10;

function formatDate(date: string | null): string {
    const parsed = parseDueDate(date);
    if (parsed === null) {
        return '—';
    }
    return dateFormatter.format(parsed);
}

function driverName(driver: ServicePickerRow['driver']): string {
    if (!driver) return '—';
    return (
        [driver.first_name, driver.first_lastname]
            .filter(Boolean)
            .join(' ')
            .trim() || '—'
    );
}

function estimatedValue(row: ServicePickerRow): string {
    if (row.unit_value === null || row.quantity === null) {
        return '—';
    }
    const total = Number(row.unit_value) * Number(row.quantity);
    if (Number.isNaN(total)) {
        return '—';
    }
    return currencyFormatter.format(total);
}

function billingIncidentCount(row: ServicePickerRow): number {
    return (row.service_incidents ?? []).filter((i) => i.affects_billing)
        .length;
}

export default function ServicePickerDialog({
    open,
    onOpenChange,
    invoiceId,
    candidates,
    blockedCandidates = [],
}: ServicePickerDialogProps) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [search, setSearch] = useState('');
    const [processing, setProcessing] = useState(false);
    const [showBlocked, setShowBlocked] = useState(false);
    const [justification, setJustification] = useState('');
    const [errors, setErrors] = useState<
        Partial<Record<'service_ids' | 'override_justification', string>>
    >({});

    const blockedIds = useMemo(
        () => new Set(blockedCandidates.map((r) => r.id)),
        [blockedCandidates],
    );

    const hasSelectedBlocked = useMemo(
        () => selectedIds.some((id) => blockedIds.has(id)),
        [selectedIds, blockedIds],
    );

    const filter = (rows: ServicePickerRow[]) => {
        const term = search.trim().toLowerCase();
        if (!term) return rows;
        return rows.filter((row) => {
            const plate = (row.vehicle?.plate ?? '').toLowerCase();
            const contract = (
                row.contract?.contract_number ?? ''
            ).toLowerCase();
            const first = (row.driver?.first_name ?? '').toLowerCase();
            const last = (row.driver?.first_lastname ?? '').toLowerCase();
            return (
                plate.includes(term) ||
                contract.includes(term) ||
                first.includes(term) ||
                last.includes(term)
            );
        });
    };

    const filteredClean = useMemo(
        () => filter(candidates),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [candidates, search],
    );
    const filteredBlocked = useMemo(
        () => filter(blockedCandidates),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [blockedCandidates, search],
    );

    const allCleanSelected =
        filteredClean.length > 0 &&
        filteredClean.every((r) => selectedIds.includes(r.id));

    function toggleOne(id: number) {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    function toggleAllClean() {
        if (allCleanSelected) {
            setSelectedIds((prev) =>
                prev.filter((id) => !filteredClean.some((r) => r.id === id)),
            );
        } else {
            setSelectedIds((prev) =>
                Array.from(
                    new Set([...prev, ...filteredClean.map((r) => r.id)]),
                ),
            );
        }
    }

    function handleSubmit() {
        if (selectedIds.length === 0) return;

        // Pre-flight: any blocked service needs a non-trivial
        // justification before we even hit the server. The server
        // re-checks, but surfacing this inline saves a round-trip.
        if (
            hasSelectedBlocked &&
            justification.trim().length < JUSTIFICATION_MIN
        ) {
            setErrors({
                override_justification: `La justificación debe tener al menos ${JUSTIFICATION_MIN} caracteres.`,
            });
            return;
        }

        setErrors({});
        setProcessing(true);
        const payload = hasSelectedBlocked
            ? {
                  service_ids: selectedIds,
                  override_justification: justification.trim(),
              }
            : { service_ids: selectedIds };

        router.post(InvoiceController.attachServices(invoiceId).url, payload, {
            preserveScroll: true,
            onError: (errs) => {
                setErrors(
                    errs as Partial<
                        Record<'service_ids' | 'override_justification', string>
                    >,
                );
            },
            onFinish: () => {
                setProcessing(false);
            },
            onSuccess: () => {
                setSelectedIds([]);
                setSearch('');
                setShowBlocked(false);
                setJustification('');
                setErrors({});
                onOpenChange(false);
            },
        });
    }

    function handleOpenChange(value: boolean) {
        if (!value) {
            setSelectedIds([]);
            setSearch('');
            setShowBlocked(false);
            setJustification('');
            setErrors({});
        }
        onOpenChange(value);
    }

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="flex max-h-[calc(100vh-4rem)] flex-col px-0 sm:max-w-4xl">
                <DialogHeader className="px-6">
                    <DialogTitle>Asignar Servicios</DialogTitle>
                    <DialogDescription>
                        Selecciona los servicios cerrados del cliente que deseas
                        asociar a esta factura.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-wrap items-center gap-4 px-6">
                    <Input
                        placeholder="Buscar por placa, contrato o conductor..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-md"
                    />
                    {blockedCandidates.length > 0 && (
                        <Label className="ml-auto flex items-center gap-2 text-sm font-normal text-muted-foreground">
                            <Switch
                                checked={showBlocked}
                                onCheckedChange={(value) => {
                                    setShowBlocked(value);
                                    if (!value) {
                                        // Hiding them also deselects any
                                        // blocked rows the operator had
                                        // picked — avoids a confused
                                        // "where did my selection go" on
                                        // toggle off.
                                        setSelectedIds((prev) =>
                                            prev.filter(
                                                (id) => !blockedIds.has(id),
                                            ),
                                        );
                                    }
                                }}
                                aria-label="Mostrar servicios con novedades facturables"
                            />
                            <span>
                                Mostrar servicios con novedades facturables (
                                {blockedCandidates.length})
                            </span>
                        </Label>
                    )}
                </div>

                <div className="flex-1 overflow-y-auto px-6 py-2">
                    {filteredClean.length === 0 && !showBlocked ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            Sin servicios candidatos.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">
                                        <Checkbox
                                            checked={allCleanSelected}
                                            onCheckedChange={toggleAllClean}
                                            aria-label="Seleccionar todos"
                                        />
                                    </TableHead>
                                    <TableHead>Fecha</TableHead>
                                    <TableHead>Vehículo</TableHead>
                                    <TableHead>Conductor</TableHead>
                                    <TableHead>Contrato</TableHead>
                                    <TableHead className="text-right">
                                        Valor estimado
                                    </TableHead>
                                    <TableHead className="text-center">
                                        Novedades
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {filteredClean.map((row) => {
                                    const incidents = billingIncidentCount(row);
                                    return (
                                        <TableRow key={row.id}>
                                            <TableCell>
                                                <Checkbox
                                                    checked={selectedIds.includes(
                                                        row.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleOne(row.id)
                                                    }
                                                    aria-label={`Seleccionar servicio ${row.id}`}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(row.service_date)}
                                            </TableCell>
                                            <TableCell className="font-mono">
                                                {row.vehicle?.plate ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {driverName(row.driver)}
                                            </TableCell>
                                            <TableCell className="font-mono text-sm">
                                                {row.contract
                                                    ?.contract_number ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {estimatedValue(row)}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                {incidents > 0 ? (
                                                    <Badge variant="secondary">
                                                        {incidents}
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        —
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                                {showBlocked && filteredBlocked.length > 0 && (
                                    <>
                                        <TableRow className="bg-muted/60 hover:bg-muted/60">
                                            <TableCell
                                                colSpan={7}
                                                className="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                                            >
                                                Con novedades facturables —
                                                requieren justificación
                                            </TableCell>
                                        </TableRow>
                                        {filteredBlocked.map((row) => {
                                            const incidents =
                                                billingIncidentCount(row);
                                            return (
                                                <TableRow
                                                    key={`blocked-${row.id}`}
                                                    className="bg-amber-500/5"
                                                    data-audit-row="blocked"
                                                >
                                                    <TableCell>
                                                        <Checkbox
                                                            checked={selectedIds.includes(
                                                                row.id,
                                                            )}
                                                            onCheckedChange={() =>
                                                                toggleOne(
                                                                    row.id,
                                                                )
                                                            }
                                                            aria-label={`Seleccionar servicio bloqueado ${row.id}`}
                                                        />
                                                    </TableCell>
                                                    <TableCell>
                                                        {formatDate(
                                                            row.service_date,
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="font-mono">
                                                        {row.vehicle?.plate ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell>
                                                        {driverName(row.driver)}
                                                    </TableCell>
                                                    <TableCell className="font-mono text-sm">
                                                        {row.contract
                                                            ?.contract_number ??
                                                            '—'}
                                                    </TableCell>
                                                    <TableCell className="text-right tabular-nums">
                                                        {estimatedValue(row)}
                                                    </TableCell>
                                                    <TableCell className="text-center">
                                                        <Badge variant="destructive">
                                                            {incidents} nov.
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </>
                                )}
                            </TableBody>
                        </Table>
                    )}
                </div>

                {hasSelectedBlocked && (
                    <div className="space-y-2 px-6">
                        <Alert variant="destructive">
                            <AlertTriangle className="size-4" />
                            <AlertTitle>Justificación obligatoria</AlertTitle>
                            <AlertDescription>
                                Está asignando uno o más servicios con novedades
                                que afectan la facturación. Indique por qué se
                                factura de todos modos.
                            </AlertDescription>
                        </Alert>
                        <Label htmlFor="override_justification">
                            Justificación *
                        </Label>
                        <textarea
                            id="override_justification"
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            value={justification}
                            onChange={(e) => setJustification(e.target.value)}
                            placeholder="Ej: El cliente autorizó la facturación tras revisar la novedad."
                            minLength={JUSTIFICATION_MIN}
                            maxLength={1000}
                            aria-invalid={
                                errors.override_justification ? true : undefined
                            }
                        />
                        {errors.override_justification && (
                            <p className="text-sm text-destructive">
                                {errors.override_justification}
                            </p>
                        )}
                    </div>
                )}

                {errors.service_ids && (
                    <div className="px-6">
                        <Alert variant="destructive">
                            <AlertTriangle className="size-4" />
                            <AlertDescription>
                                {errors.service_ids}
                            </AlertDescription>
                        </Alert>
                    </div>
                )}

                <DialogFooter className="mt-2 gap-2 px-6">
                    <DialogClose asChild>
                        <Button type="button" variant="outline">
                            Cancelar
                        </Button>
                    </DialogClose>
                    <Button
                        type="button"
                        onClick={handleSubmit}
                        disabled={selectedIds.length === 0 || processing}
                    >
                        Asignar
                        {selectedIds.length > 0 && ` (${selectedIds.length})`}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
