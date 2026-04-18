import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
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
}

const currencyFormatter = new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
});

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
}: ServicePickerDialogProps) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [search, setSearch] = useState('');
    const [processing, setProcessing] = useState(false);

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return candidates;
        return candidates.filter((row) => {
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
    }, [candidates, search]);

    const allSelected =
        filtered.length > 0 &&
        filtered.every((r) => selectedIds.includes(r.id));

    function toggleOne(id: number) {
        setSelectedIds((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    }

    function toggleAll() {
        if (allSelected) {
            setSelectedIds((prev) =>
                prev.filter((id) => !filtered.some((r) => r.id === id)),
            );
        } else {
            setSelectedIds((prev) =>
                Array.from(new Set([...prev, ...filtered.map((r) => r.id)])),
            );
        }
    }

    function handleSubmit() {
        if (selectedIds.length === 0) return;
        setProcessing(true);
        router.post(
            InvoiceController.attachServices(invoiceId).url,
            { service_ids: selectedIds },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                },
                onSuccess: () => {
                    setSelectedIds([]);
                    setSearch('');
                    onOpenChange(false);
                },
            },
        );
    }

    function handleOpenChange(value: boolean) {
        if (!value) {
            setSelectedIds([]);
            setSearch('');
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

                <div className="px-6">
                    <Input
                        placeholder="Buscar por placa, contrato o conductor..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                    />
                </div>

                <div className="flex-1 overflow-y-auto px-6 py-2">
                    {filtered.length === 0 ? (
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            Sin servicios candidatos.
                        </p>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">
                                        <Checkbox
                                            checked={allSelected}
                                            onCheckedChange={toggleAll}
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
                                {filtered.map((row) => {
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
                            </TableBody>
                        </Table>
                    )}
                </div>

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
