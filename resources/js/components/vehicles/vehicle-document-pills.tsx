import { Badge } from '@/components/ui/badge';
import {
    dateFormatter,
    parseDueDate,
    statusBadgeVariant,
    statusForInstant,
    type DocumentStatus,
} from '@/lib/document-status';

type DocumentInput = {
    soat_due_at: string | null;
    rtm_due_at: string | null;
    operation_card_due_at: string | null;
    soat_due_date?: string | null;
    rtm_due_date?: string | null;
    operation_card_due_date?: string | null;
};

interface DocumentSlot {
    label: string;
    visibleDate: string | null;
    status: DocumentStatus;
}

function tooltipFor(
    label: string,
    visibleDate: string | null,
    status: DocumentStatus,
): string {
    const parsed = parseDueDate(visibleDate);
    if (parsed === null) {
        return `${label} sin registrar`;
    }
    const formatted = dateFormatter.format(parsed);
    if (status === 'expired') {
        return `${label} vencido (${formatted})`;
    }
    if (status === 'expiring_soon') {
        return `${label} por vencer (${formatted})`;
    }
    return `${label} vence ${formatted}`;
}

/**
 * Three-pill component summarizing a vehicle's legal document state.
 * Compares half-open `*_due_at` instants against `now`.
 */
export function VehicleDocumentPills({
    vehicle,
    now,
}: {
    vehicle: DocumentInput;
    now?: Date;
}) {
    const reference = now ?? new Date();

    const slots: DocumentSlot[] = [
        {
            label: 'SOAT',
            visibleDate: vehicle.soat_due_date ?? null,
            status: statusForInstant(vehicle.soat_due_at, reference),
        },
        {
            label: 'RTM',
            visibleDate: vehicle.rtm_due_date ?? null,
            status: statusForInstant(vehicle.rtm_due_at, reference),
        },
        {
            label: 'T.O.',
            visibleDate: vehicle.operation_card_due_date ?? null,
            status: statusForInstant(vehicle.operation_card_due_at, reference),
        },
    ];

    return (
        <div className="flex flex-wrap items-center gap-1">
            {slots.map((slot) => (
                <Badge
                    key={slot.label}
                    variant={statusBadgeVariant(slot.status)}
                    title={tooltipFor(
                        slot.label,
                        slot.visibleDate,
                        slot.status,
                    )}
                >
                    {slot.label}
                    {slot.status === 'expired' ? '!' : ''}
                </Badge>
            ))}
        </div>
    );
}

/**
 * Public helper for the vehicles index row tint. Returns the worst of
 * the three slot statuses.
 */
export function vehicleDocsAggregateStatus(
    vehicle: DocumentInput,
    now?: Date,
): DocumentStatus {
    const reference = now ?? new Date();
    const statuses = [
        statusForInstant(vehicle.soat_due_at, reference),
        statusForInstant(vehicle.rtm_due_at, reference),
        statusForInstant(vehicle.operation_card_due_at, reference),
    ];

    if (statuses.includes('expired')) return 'expired';
    if (statuses.includes('expiring_soon')) return 'expiring_soon';
    return 'ok';
}

export default VehicleDocumentPills;
