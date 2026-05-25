import { Badge } from '@/components/ui/badge';

type SeverityValue = 'informational' | 'minor' | 'major' | string | null;

const LABELS: Record<string, string> = {
    informational: 'Informativo',
    minor: 'Menor',
    major: 'Mayor!',
};

const VARIANTS: Record<
    string,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    informational: 'outline',
    minor: 'secondary',
    major: 'destructive',
};

const TOOLTIPS: Record<string, string> = {
    informational: 'Incidente informativo',
    minor: 'Incidente menor',
    major: 'Incidente mayor — requiere atención',
};

/**
 * Single Badge summarizing an incident's severity.
 *
 * Unlike the document / contract pills, severity is a manual enum
 * axis — not a date-derived state machine. This component lives
 * alongside its feature folder rather than inside
 * `lib/document-status.ts` because payment/severity status is NOT
 * a date-derived axis (same rationale as `<PaymentStatusPill />`).
 */
export function IncidentSeverityPill({
    severity,
    className,
}: {
    severity: SeverityValue;
    className?: string;
}) {
    const key = severity ?? '';
    const label = LABELS[key] ?? severity ?? '—';
    const variant = VARIANTS[key] ?? 'outline';

    return (
        <Badge variant={variant} title={TOOLTIPS[key]} className={className}>
            {label}
        </Badge>
    );
}

/**
 * Public helper exposed so the index can compute the row tint without
 * re-instantiating the pill component. Returns shadcn utility classes
 * to merge onto the row.
 */
export function incidentSeverityRowTint(
    severity: SeverityValue,
): string | undefined {
    switch (severity) {
        case 'major':
            return 'bg-destructive/10 hover:bg-destructive/15';
        case 'minor':
            return 'bg-amber-100/60 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/30';
        default:
            return undefined;
    }
}

export default IncidentSeverityPill;
