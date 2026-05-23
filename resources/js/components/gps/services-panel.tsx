import { ServiceListItem } from '@/components/gps/service-list-item';
import { SymbolLegend } from '@/components/gps/symbol-legend';
import type { ActiveService } from '@/types/gps-map';

/**
 * The services panel body — a scrollable list of every active service
 * plus the symbol legend. Shared between the desktop sidebar and the
 * mobile slide-in sheet.
 */
export function ServicesPanel({
    services,
    selectedId,
    onSelect,
}: {
    services: ActiveService[];
    selectedId: number | null;
    onSelect: (id: number) => void;
}) {
    return (
        <div className="flex h-full min-h-0 w-full flex-col">
            <div className="border-b px-3 py-2 text-sm font-medium">
                Servicios
            </div>
            {services.length === 0 ? (
                <div className="flex-1 px-3 py-6 text-xs text-muted-foreground">
                    No hay servicios activos hoy.
                </div>
            ) : (
                <ul className="min-h-0 flex-1 space-y-1 overflow-y-auto p-2">
                    {services.map((service) => (
                        <ServiceListItem
                            key={service.service_id}
                            service={service}
                            selected={selectedId === service.service_id}
                            onSelect={onSelect}
                        />
                    ))}
                </ul>
            )}
            <SymbolLegend />
        </div>
    );
}
