import { Lock } from 'lucide-react';
import { memo } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

interface VehicleSidebarItemProps {
    vehicle: {
        id: number;
        plate: string;
        is_third_party: boolean;
        soat_due_date: string | null;
        rtm_due_date: string | null;
        operation_card_due_date: string | null;
    };
    isBlocked: boolean;
    hasWarning: boolean;
    expiredDocs: string[];
}

/**
 * Assumes a TooltipProvider is mounted up the tree (the Gantt page
 * provides one in HourlyGrid). Wrapped in `memo` so resize / scroll
 * re-renders of the parent don't churn through ~10 sidebar items.
 */
function VehicleSidebarItem({
    vehicle,
    isBlocked,
    hasWarning,
    expiredDocs,
}: VehicleSidebarItemProps) {
    const expiredDetails = expiredDocs
        .map((doc) => {
            const dateMap: Record<string, string | null> = {
                SOAT: vehicle.soat_due_date,
                RTM: vehicle.rtm_due_date,
                'T.O.': vehicle.operation_card_due_date,
            };
            return `${doc} vencido: ${dateMap[doc] ?? ''}`;
        })
        .join(', ');

    const showLock = isBlocked;
    const showWarn = hasWarning;
    const show3p = vehicle.is_third_party;

    return (
        <div
            className={cn(
                'flex items-center gap-1.5 px-2',
                isBlocked && 'opacity-60',
            )}
        >
            <span className="font-mono text-sm font-semibold">
                {vehicle.plate}
            </span>
            {showLock && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Badge
                            variant="destructive"
                            className="px-1 py-0 text-[10px] leading-tight"
                        >
                            <Lock className="size-2.5" aria-hidden />
                            <span className="sr-only sm:not-sr-only sm:ml-0.5">
                                BLOQ.
                            </span>
                        </Badge>
                    </TooltipTrigger>
                    <TooltipContent side="right">
                        <p className="text-xs">{expiredDetails}</p>
                    </TooltipContent>
                </Tooltip>
            )}
            {showWarn && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Badge className="bg-yellow-100 px-1 py-0 text-[10px] leading-tight text-yellow-700 hover:bg-yellow-100 dark:bg-yellow-900 dark:text-yellow-300">
                            <span className="sm:hidden">P</span>
                            <span className="hidden sm:inline">Prec.</span>
                        </Badge>
                    </TooltipTrigger>
                    <TooltipContent side="right">
                        <p className="text-xs">
                            Precaución — documentos próximos a vencer
                        </p>
                    </TooltipContent>
                </Tooltip>
            )}
            {show3p && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Badge className="bg-blue-100 px-1 py-0 text-[10px] leading-tight text-blue-700 hover:bg-blue-100 dark:bg-blue-900 dark:text-blue-300">
                            <span className="sm:hidden">3</span>
                            <span className="hidden sm:inline">3ro</span>
                        </Badge>
                    </TooltipTrigger>
                    <TooltipContent side="right">
                        <p className="text-xs">Vehículo de tercero</p>
                    </TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}

export default memo(VehicleSidebarItem);
