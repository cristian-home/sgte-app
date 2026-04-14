import { Badge } from '@/components/ui/badge';
import {
    contractDaysRemaining,
    contractPeriodStatus,
    contractStatusBadgeVariant,
    type ContractPeriodStatus,
} from '@/lib/document-status';

type ContractInput = {
    start_date: string | null;
    end_date: string | null;
    active: boolean;
};

const STATUS_LABELS: Record<ContractPeriodStatus, string> = {
    vigente: 'Vigente',
    por_vencer: 'Por vencer',
    vencido: 'Vencido!',
    inactivo: 'Inactivo',
};

function tooltipFor(
    contract: ContractInput,
    status: ContractPeriodStatus,
    days: number | null,
): string {
    if (status === 'inactivo') {
        return 'Contrato inactivo';
    }
    if (days === null) {
        return 'Contrato sin fecha de fin';
    }
    if (status === 'vencido') {
        return `Vencido hace ${Math.abs(days)} días`;
    }
    if (status === 'por_vencer') {
        return `Vence en ${days} días`;
    }
    return `Vigente (vence en ${days} días)`;
}

/**
 * Single Badge summarizing a contract's temporal state.
 *
 * Four-state machine — `'vigente' | 'por_vencer' | 'vencido' | 'inactivo'`
 * against today's date with a 60-day "por vencer" window. Mirrors the
 * server-side `contract_status` filter in ContractController. Used on
 * the contracts index Estado column and on the show page header card.
 */
export function ContractPeriodPill({
    contract,
    today,
    showDays = false,
}: {
    contract: ContractInput;
    today?: string;
    showDays?: boolean;
}) {
    const status = contractPeriodStatus(contract, today);
    const days = contractDaysRemaining(contract.end_date, today);
    const label = STATUS_LABELS[status];
    const suffix =
        showDays &&
        days !== null &&
        (status === 'por_vencer' || status === 'vencido')
            ? ` (${days} días)`
            : '';

    return (
        <Badge
            variant={contractStatusBadgeVariant(status)}
            title={tooltipFor(contract, status, days)}
        >
            {label}
            {suffix}
        </Badge>
    );
}

/**
 * Public helper exposed so the contracts index can compute the row
 * tint without re-instantiating the pill component just to read its
 * state. Returns the shadcn utility class(es) to merge onto the row.
 */
export function contractRowTint(
    contract: ContractInput,
    today?: string,
): string | undefined {
    const status = contractPeriodStatus(contract, today);
    if (status === 'vencido') {
        return 'bg-destructive/10 hover:bg-destructive/15';
    }
    if (status === 'por_vencer') {
        return 'bg-amber-100/60 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/30';
    }
    if (status === 'inactivo') {
        return 'bg-muted/60 hover:bg-muted/70';
    }
    return undefined;
}

export default ContractPeriodPill;
