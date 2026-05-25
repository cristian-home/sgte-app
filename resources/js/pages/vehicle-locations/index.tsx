import { Head, Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { ToolbarLabel } from '@/components/data-table/toolbar-label';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VehicleCombobox, {
    type VehicleOption,
} from '@/components/vehicles/vehicle-combobox';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';

import { vehicleLocationColumns, type VehicleLocationRow } from './columns';

import type { BreadcrumbItem, FilterDefinition, PaginatedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'GPS', href: '#' },
    { title: 'Ubicaciones', href: '/vehicle-locations' },
];

const filters: FilterDefinition[] = [
    {
        name: 'is_manual',
        label: 'Origen',
        options: [
            { value: '1', label: 'Manual' },
            { value: '0', label: 'GPS' },
        ],
    },
];

interface Props {
    vehicleLocations: PaginatedData<VehicleLocationRow>;
    vehicles: VehicleOption[];
}

export default function VehicleLocationsIndex({
    vehicleLocations,
    vehicles,
}: Props) {
    'use no memo';
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
    } = useServerTable<VehicleLocationRow>({
        data: vehicleLocations,
        columns: vehicleLocationColumns,
    });

    const selectedVehicleId = activeFilters['vehicle_id']?.[0] ?? null;
    const recordedFrom = activeFilters['recorded_from']?.[0] ?? '';
    const recordedTo = activeFilters['recorded_to']?.[0] ?? '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ubicaciones" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor="locations-vehicle"
                            className="text-xs text-muted-foreground"
                        >
                            Vehículo
                        </Label>
                        <VehicleCombobox
                            id="locations-vehicle"
                            vehicles={vehicles}
                            value={selectedVehicleId}
                            onChange={(value) =>
                                setFilter(
                                    'vehicle_id',
                                    value === null ? [] : [String(value)],
                                )
                            }
                            placeholder="Todos los vehículos"
                            className="w-64"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor="locations-from"
                            className="text-xs text-muted-foreground"
                        >
                            Desde
                        </Label>
                        <Input
                            id="locations-from"
                            type="date"
                            value={recordedFrom}
                            className="w-40"
                            onChange={(event) =>
                                setFilter(
                                    'recorded_from',
                                    event.target.value
                                        ? [event.target.value]
                                        : [],
                                )
                            }
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor="locations-to"
                            className="text-xs text-muted-foreground"
                        >
                            Hasta
                        </Label>
                        <Input
                            id="locations-to"
                            type="date"
                            value={recordedTo}
                            className="w-40"
                            onChange={(event) =>
                                setFilter(
                                    'recorded_to',
                                    event.target.value
                                        ? [event.target.value]
                                        : [],
                                )
                            }
                        />
                    </div>
                </div>

                <DataTable
                    table={table}
                    paginatedData={paginatedData}
                    search={search}
                    onSearchChange={setSearch}
                    loading={loading}
                    onNavigate={onNavigate}
                    onPerPageChange={onPerPageChange}
                    searchPlaceholder="Buscar..."
                    filters={filters}
                    activeFilters={activeFilters}
                    onFilterChange={setFilter}
                    onClearFilters={clearFilters}
                    actions={
                        <Button asChild size="sm">
                            <Link
                                href="/vehicle-locations/create"
                                aria-label="Registrar"
                            >
                                <PlusIcon className="size-4" />
                                <ToolbarLabel>Registrar</ToolbarLabel>
                            </Link>
                        </Button>
                    }
                />
            </div>
        </AppLayout>
    );
}
