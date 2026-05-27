import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InvoiceController from '@/actions/App/Http/Controllers/InvoiceController';
import ServicePickerTable, {
    JUSTIFICATION_MIN,
    type ServicePickerRow,
} from '@/components/invoices/service-picker-table';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type { ServicePickerRow } from '@/components/invoices/service-picker-table';

interface ServicePickerDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    invoiceId: number;
    candidates: ServicePickerRow[];
    blockedCandidates?: ServicePickerRow[];
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

                <div className="flex-1 overflow-y-auto px-6 py-2">
                    <ServicePickerTable
                        candidates={candidates}
                        blockedCandidates={blockedCandidates}
                        selectedIds={selectedIds}
                        onSelectedIdsChange={setSelectedIds}
                        showBlocked={showBlocked}
                        onShowBlockedChange={setShowBlocked}
                        justification={justification}
                        onJustificationChange={setJustification}
                        justificationError={errors.override_justification}
                        serviceIdsError={errors.service_ids}
                        search={search}
                        onSearchChange={setSearch}
                    />
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
