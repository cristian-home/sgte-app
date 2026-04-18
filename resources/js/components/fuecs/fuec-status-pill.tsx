import { Badge } from '@/components/ui/badge';

/**
 * Manual-enum state pill for `Fuec.status` — Vigente (active) or
 * Anulado (cancelled). Mirrors the convention used by
 * <PaymentStatusPill /> and <IncidentSeverityPill />: lives alongside
 * the feature folder, not in `lib/document-status.ts`, because the
 * state is not date-derived.
 */
export function FuecStatusPill({
    status,
}: {
    status: 'active' | 'cancelled' | string;
}) {
    if (status === 'cancelled') {
        return <Badge variant="destructive">Anulado</Badge>;
    }
    return <Badge>Vigente</Badge>;
}

export default FuecStatusPill;
