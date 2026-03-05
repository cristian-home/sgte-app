import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { Service } from '@/types/models';

interface ServiceBarProps {
    service: Service;
    position: { left: number; width: number };
    onClick: (serviceId: number) => void;
}

function getClientName(service: Service): string {
    const tp = service.contract?.third_party;
    if (!tp) return 'Servicio';
    if (tp.is_natural_person) {
        return [tp.first_name, tp.first_lastname].filter(Boolean).join(' ');
    }
    return tp.company_name ?? 'Servicio';
}

function getDriverName(service: Service): string {
    if (!service.driver) return '3ro';
    return [service.driver.first_name, service.driver.first_lastname]
        .filter(Boolean)
        .join(' ');
}

function formatTime(time: string | null): string {
    if (!time) return '--:--';
    return time.slice(0, 5);
}

export default function ServiceBar({
    service,
    position,
    onClick,
}: ServiceBarProps) {
    const isOpen = service.service_status === 'open';

    return (
        <TooltipProvider>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button
                        type="button"
                        className={cn(
                            'absolute top-0.5 bottom-0.5 cursor-pointer overflow-hidden rounded px-1.5 py-0.5 text-left text-white shadow-sm transition-colors',
                            isOpen
                                ? 'bg-orange-400 hover:bg-orange-500 dark:bg-orange-500 dark:hover:bg-orange-600'
                                : 'bg-green-500 hover:bg-green-600 dark:bg-green-600 dark:hover:bg-green-700',
                        )}
                        style={{
                            left: `${position.left}%`,
                            width: `${position.width}%`,
                            minWidth: '24px',
                        }}
                        onClick={(e) => {
                            e.stopPropagation();
                            onClick(service.id);
                        }}
                    >
                        <div className="truncate text-[10px] leading-tight font-medium">
                            {getClientName(service)}
                        </div>
                        <div className="truncate text-[9px] leading-tight opacity-80">
                            {getDriverName(service)}
                        </div>
                    </button>
                </TooltipTrigger>
                <TooltipContent side="top" className="max-w-xs">
                    <div className="space-y-1 text-xs">
                        <p className="font-medium">{getClientName(service)}</p>
                        <p>Conductor: {getDriverName(service)}</p>
                        <p>
                            Planificado:{' '}
                            {formatTime(service.planned_start_time)} (
                            {service.planned_duration} min)
                        </p>
                        {service.actual_start_time && (
                            <p>
                                Real: {formatTime(service.actual_start_time)}
                                {service.actual_end_time
                                    ? ` - ${formatTime(service.actual_end_time)}`
                                    : ''}
                            </p>
                        )}
                        <p>Estado: {isOpen ? 'Abierto' : 'Cerrado'}</p>
                    </div>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
