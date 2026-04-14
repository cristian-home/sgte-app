import { Badge } from '@/components/ui/badge';

type DocumentInput = {
    soat_due_date: string | null;
    rtm_due_date: string | null;
    operation_card_due_date: string | null;
};

type DocumentStatus = 'expired' | 'expiring_soon' | 'ok';

interface DocumentSlot {
    label: string;
    dueDate: string | null;
    status: DocumentStatus;
}

const DAYS_IN_MS = 24 * 60 * 60 * 1000;

const dateFormatter = new Intl.DateTimeFormat('es-CO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

function statusFor(dueDate: string | null, todayMs: number): DocumentStatus {
    if (dueDate === null) {
        return 'expired';
    }
    const dueMs = new Date(`${dueDate}T00:00:00`).getTime();
    if (Number.isNaN(dueMs) || dueMs < todayMs) {
        return 'expired';
    }
    const daysOut = Math.round((dueMs - todayMs) / DAYS_IN_MS);
    if (daysOut <= 30) {
        return 'expiring_soon';
    }
    return 'ok';
}

function variantFor(status: DocumentStatus): 'destructive' | 'secondary' | 'outline' {
    switch (status) {
        case 'expired':
            return 'destructive';
        case 'expiring_soon':
            return 'secondary';
        default:
            return 'outline';
    }
}

function tooltipFor(label: string, dueDate: string | null, status: DocumentStatus): string {
    if (dueDate === null) {
        return `${label} sin registrar`;
    }
    const formatted = dateFormatter.format(new Date(`${dueDate}T00:00:00`));
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
    const todayString = today ?? new Date().toISOString().slice(0, 10);
    const todayMs = new Date(`${todayString}T00:00:00`).getTime();

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
                    variant={variantFor(slot.status)}
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
    const todayString = today ?? new Date().toISOString().slice(0, 10);
    const todayMs = new Date(`${todayString}T00:00:00`).getTime();

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
