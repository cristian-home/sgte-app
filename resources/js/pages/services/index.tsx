import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Can } from '@/components/can';
import { DataTable } from '@/components/data-table';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import ServicesIndexFilters, {
    type ContractFilterOption,
    type DriverFilterOption,
} from '@/components/services/services-index-filters';
import { Button } from '@/components/ui/button';
import { type VehicleOption } from '@/components/vehicles/vehicle-combobox';
import { Permission } from '@/enums/Permission';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import services from '@/routes/services';
import { columns } from './columns';

import type {
    BreadcrumbItem,
    FilterDefinition,
    PaginatedData,
    Service,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Servicios',
        href: services.index().url,
    },
];

const serviceFilters: FilterDefinition[] = [
    {
        name: 'service_status',
        label: 'Estado',
        options: [
            { value: 'open', label: 'Abierto' },
            { value: 'closed', label: 'Cerrado' },
        ],
    },
    {
        name: 'payment_method',
        label: 'Método de pago',
        options: [
            { value: 'cash', label: 'Efectivo' },
            { value: 'credit', label: 'Crédito' },
            { value: 'transfer', label: 'Transferencia' },
        ],
    },
];

function startOfWeekIso(): string {
    const d = new Date();
    const dayIdx = d.getDay(); // 0 = Sunday
    const mondayOffset = dayIdx === 0 ? -6 : 1 - dayIdx;
    d.setDate(d.getDate() + mondayOffset);
    return d.toISOString().slice(0, 10);
}

function endOfWeekIso(): string {
    const d = new Date();
    const dayIdx = d.getDay();
    const sundayOffset = dayIdx === 0 ? 0 : 7 - dayIdx;
    d.setDate(d.getDate() + sundayOffset);
    return d.toISOString().slice(0, 10);
}

export default function ServicesIndex({
    services: paginatedServices,
    filterContracts,
    filterDrivers,
    filterVehicles,
    filterMunicipalities,
}: {
    services: PaginatedData<Service>;
    filterContracts: ContractFilterOption[];
    filterDrivers: DriverFilterOption[];
    filterVehicles: VehicleOption[];
    filterMunicipalities: MunicipalityOption[];
}) {
    const {
        table,
        paginatedData,
        search,
        setSearch,
        loading,
        onNavigate,
        onPerPageChange,
        activeFilters,
        setFilter,
        clearFilters,
    } = useServerTable({ data: paginatedServices, columns });

    const singleFilter = (name: string): string =>
        (activeFilters[name]?.[0] ?? '') as string;

    const setSingleFilter = (name: string, value: string) => {
        setFilter(name, value ? [value] : []);
    };

    function applyPreset(preset: 'today' | 'this_week' | 'open_only') {
        if (preset === 'today') {
            const today = new Date().toISOString().slice(0, 10);
            setFilter('date_from', [today]);
            setFilter('date_to', [today]);
        } else if (preset === 'this_week') {
            setFilter('date_from', [startOfWeekIso()]);
            setFilter('date_to', [endOfWeekIso()]);
        } else if (preset === 'open_only') {
            setFilter('service_status', ['open']);
        }
    }

    function clearAdvancedFilters() {
        [
            'contract_id',
            'driver_id',
            'vehicle_id',
            'origin_municipality_id',
            'destination_municipality_id',
            'date_from',
            'date_to',
        ].forEach((name) => setFilter(name, []));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Servicios" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <ServicesIndexFilters
                    contracts={filterContracts}
                    drivers={filterDrivers}
                    vehicles={filterVehicles}
                    municipalities={filterMunicipalities}
                    contractId={singleFilter('contract_id')}
                    driverId={singleFilter('driver_id')}
                    vehicleId={singleFilter('vehicle_id')}
                    originMunicipalityId={singleFilter(
                        'origin_municipality_id',
                    )}
                    destinationMunicipalityId={singleFilter(
                        'destination_municipality_id',
                    )}
                    dateFrom={singleFilter('date_from')}
                    dateTo={singleFilter('date_to')}
                    onFilterChange={setSingleFilter}
                    onApplyPreset={applyPreset}
                    onClearAll={clearAdvancedFilters}
                />
                <DataTable
                    table={table}
                    paginatedData={paginatedData}
                    search={search}
                    onSearchChange={setSearch}
                    loading={loading}
                    onNavigate={onNavigate}
                    onPerPageChange={onPerPageChange}
                    searchPlaceholder="Buscar por origen, destino o grupo..."
                    filters={serviceFilters}
                    activeFilters={activeFilters}
                    onFilterChange={setFilter}
                    onClearFilters={clearFilters}
                    actions={
                        <Can permission={Permission.CREATE_SERVICES}>
                            <Button asChild size="sm">
                                <Link href={services.create().url}>
                                    <Plus className="mr-2 size-4" />
                                    Crear Servicio
                                </Link>
                            </Button>
                        </Can>
                    }
                />
            </div>
        </AppLayout>
    );
}
