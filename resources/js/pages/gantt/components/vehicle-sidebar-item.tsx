import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
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

export default function VehicleSidebarItem({
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
            {/* Status badges. Each shows abbreviated single-char text on
                mobile (sidebar w-24 = 96px) and the full label on sm+ so
                three badges + plate never overflow the narrow column. */}
            <TooltipProvider>
                {isBlocked && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Badge
                                variant="destructive"
                                className="px-1 py-0 text-[10px] leading-tight"
                            >
                                <span className="sm:hidden">B</span>
                                <span className="hidden sm:inline">BLOQ.</span>
                            </Badge>
                        </TooltipTrigger>
                        <TooltipContent side="right">
                            <p className="text-xs">{expiredDetails}</p>
                        </TooltipContent>
                    </Tooltip>
                )}
                {hasWarning && (
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
                {vehicle.is_third_party && (
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
            </TooltipProvider>
        </div>
    );
}
