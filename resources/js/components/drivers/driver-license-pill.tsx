import { Badge } from '@/components/ui/badge';
import {
    dateFormatter,
    documentStatus,
    parseDueDate,
    statusBadgeVariant,
    type DocumentStatus,
} from '@/lib/document-status';

type DriverInput = {
    license_due_date: string | null;
};

function tooltipFor(dueDate: string | null, status: DocumentStatus): string {
    const parsed = parseDueDate(dueDate);
    if (parsed === null) {
        return 'Licencia sin registrar';
    }
    const formatted = dateFormatter.format(parsed);
    if (status === 'expired') {
        return `Licencia vencida (${formatted})`;
    }
    if (status === 'expiring_soon') {
        return `Licencia por vencer (${formatted})`;
    }
    return `Licencia vence ${formatted}`;
}

/**
 * Single Badge summarizing a driver's license state.
 *
 * Reuses the same three-state machine as `<VehicleDocumentPills />`:
 *
 * - destructive + `!` suffix: license is null or already expired
 * - secondary: license is within the next 30 days
 * - outline: license is more than 30 days away
 *
 * Used on the drivers index Licencia column and on the show page
 * Licencia y Seguridad Social card.
 */
export function DriverLicensePill({
    driver,
    today,
}: {
    driver: DriverInput;
    today?: string;
}) {
    const status = documentStatus(driver.license_due_date, today);

    return (
        <Badge
            variant={statusBadgeVariant(status)}
            title={tooltipFor(driver.license_due_date, status)}
        >
            Licencia
            {status === 'expired' ? '!' : ''}
        </Badge>
    );
}

/**
 * Public helper exposed so the drivers index can compute the row tint
 * without re-instantiating the pill component just to read its state.
 *
 * Returns `'expired' | 'expiring_soon' | 'ok'` against today's date
 * with the shared 30-day window.
 */
export function driverLicenseStatus(
    driver: DriverInput,
    today?: string,
): DocumentStatus {
    return documentStatus(driver.license_due_date, today);
}

export default DriverLicensePill;
