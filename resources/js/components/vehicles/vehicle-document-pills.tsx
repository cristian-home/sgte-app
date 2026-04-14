import { Badge } from '@/components/ui/badge';
import {
    dateFormatter,
    localTodayMs,
    parseDueDate,
    statusBadgeVariant,
    statusFor,
    type DocumentStatus,
} from '@/lib/document-status';

type DocumentInput = {
    soat_due_date: string | null;
    rtm_due_date: string | null;
    operation_card_due_date: string | null;
};

interface DocumentSlot {
    label: string;
    dueDate: string | null;
    status: DocumentStatus;
}

function tooltipFor(
    label: string,
    dueDate: string | null,
    status: DocumentStatus,
): string {
    const parsed = parseDueDate(dueDate);
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
 *
 * Each pill (SOAT, RTM, T.O.) renders with a Badge variant computed
 * against the supplied `today` (defaulting to the local browser date):
 *
 * - destructive: due date is null or in the past
 * - secondary:   due date is within the next 30 days
 * - outline:     due date is more than 30 days away
 *
 * Reused on the vehicles index Documentos column and on the show page
 * Documentos card.
 */
export function VehicleDocumentPills({
    vehicle,
    today,
}: {
    vehicle: DocumentInput;
    today?: string;
}) {
    const todayMs = localTodayMs(today);

    const slots: DocumentSlot[] = [
        {
            label: 'SOAT',
            dueDate: vehicle.soat_due_date,
            status: statusFor(vehicle.soat_due_date, todayMs),
        },
        {
            label: 'RTM',
            dueDate: vehicle.rtm_due_date,
            status: statusFor(vehicle.rtm_due_date, todayMs),
        },
        {
            label: 'T.O.',
            dueDate: vehicle.operation_card_due_date,
            status: statusFor(vehicle.operation_card_due_date, todayMs),
        },
    ];

    return (
        <div className="flex flex-wrap items-center gap-1">
            {slots.map((slot) => (
                <Badge
                    key={slot.label}
                    variant={statusBadgeVariant(slot.status)}
                    title={tooltipFor(slot.label, slot.dueDate, slot.status)}
                >
                    {slot.label}
                    {slot.status === 'expired' ? '!' : ''}
                </Badge>
            ))}
        </div>
    );
}

/**
 * Public helper exposed so the vehicles index can compute the row tint
 * without re-instantiating the pill component just to read its state.
 *
 * Returns:
 * - 'expired' when any document is expired or null
 * - 'expiring_soon' when at least one document is within 30 days and none is expired
 * - 'ok' otherwise
 */
export function vehicleDocsAggregateStatus(
    vehicle: DocumentInput,
    today?: string,
): DocumentStatus {
    const todayMs = localTodayMs(today);

    const statuses = [
        statusFor(vehicle.soat_due_date, todayMs),
        statusFor(vehicle.rtm_due_date, todayMs),
        statusFor(vehicle.operation_card_due_date, todayMs),
    ];

    if (statuses.includes('expired')) {
        return 'expired';
    }
    if (statuses.includes('expiring_soon')) {
        return 'expiring_soon';
    }
    return 'ok';
}

export default VehicleDocumentPills;
