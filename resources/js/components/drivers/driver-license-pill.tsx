import { Badge } from '@/components/ui/badge';
import {
    dateFormatter,
    parseDueDate,
    statusBadgeVariant,
    statusForInstant,
    type DocumentStatus,
} from '@/lib/document-status';

type DriverInput = {
    /** UTC instant exclusive end-of-validity of the license (half-open). */
    license_due_at: string | null;
    /** Y-m-d projection of license_due_at in the driver's TZ (used for the tooltip). */
    license_due_date?: string | null;
};

function tooltipFor(driver: DriverInput, status: DocumentStatus): string {
    const visible = driver.license_due_date ?? null;
    const parsed = parseDueDate(visible);
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
 * Compares the half-open `license_due_at` instant against `now`:
 * - destructive + `!` suffix: license already lapsed
 * - secondary: license is within the next 30 days
 * - outline: license is more than 30 days away
 */
export function DriverLicensePill({
    driver,
    now,
}: {
    driver: DriverInput;
    now?: Date;
}) {
    const status = statusForInstant(driver.license_due_at, now);

    return (
        <Badge
            variant={statusBadgeVariant(status)}
            title={tooltipFor(driver, status)}
        >
            Licencia
            {status === 'expired' ? '!' : ''}
        </Badge>
    );
}

/**
 * Public helper exposed so the drivers index can compute the row tint
 * without re-instantiating the pill component just to read its state.
 */
export function driverLicenseStatus(
    driver: DriverInput,
    now?: Date,
): DocumentStatus {
    return statusForInstant(driver.license_due_at, now);
}

export default DriverLicensePill;
