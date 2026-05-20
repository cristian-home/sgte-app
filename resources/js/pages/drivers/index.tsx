import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DataTable } from '@/components/data-table';
import DriverDialog, {
    type EditableDriver,
} from '@/components/drivers/driver-dialog';
import { driverLicenseStatus } from '@/components/drivers/driver-license-pill';
import MunicipalityCombobox, {
    type MunicipalityOption,
} from '@/components/municipality-combobox';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import drivers from '@/routes/drivers';

import { columns, type DriverTableMeta } from './columns';

import type { Row } from '@tanstack/react-table';
import type {
    CatalogOption,
    DocumentTypeOption,
} from '@/components/drivers/driver-form';
import type { BreadcrumbItem, FilterDefinition, PaginatedData } from '@/types';
import type { Driver } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Conductores', href: drivers.index().url },
];

const driverFilters: FilterDefinition[] = [
    {
        name: 'active',
        label: 'Estado',
        options: [
            { value: '1', label: 'Activo' },
            { value: '0', label: 'Inactivo' },
        ],
    },
    {
        name: 'license_category',
        label: 'Categoría',
        options: [
            { value: 'C1', label: 'C1' },
            { value: 'C2', label: 'C2' },
            { value: 'C3', label: 'C3' },
        ],
    },
    {
        name: 'license_status',
        label: 'Documentos',
        options: [
            { value: 'ok', label: 'Al día' },
            { value: 'expiring_soon', label: 'Por vencer (≤30 días)' },
            { value: 'expired', label: 'Vencidos' },
        ],
    },
    {
        name: 'has_social_security',
        label: 'Seguridad Social',
        options: [
            { value: '1', label: 'Sí' },
            { value: '0', label: 'No' },
        ],
    },
];

function rowTintFor(row: Row<Driver>): string | undefined {
    const status = driverLicenseStatus(row.original);
    if (status === 'expired') {
        return 'bg-destructive/10 hover:bg-destructive/15';
    }
    if (status === 'expiring_soon') {
        return 'bg-amber-100/60 hover:bg-amber-100/80 dark:bg-amber-900/20 dark:hover:bg-amber-900/30';
    }
    return undefined;
}

export default function DriversIndex({
    drivers: paginatedDrivers,
    municipalities,
    documentTypes,
    eps,
    pensionFunds,
    severanceFunds,
}: {
    drivers: PaginatedData<Driver>;
    municipalities: MunicipalityOption[];
    documentTypes: DocumentTypeOption[];
    eps: CatalogOption[];
    pensionFunds: CatalogOption[];
    severanceFunds: CatalogOption[];
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
    const [selectedDriver, setSelectedDriver] = useState<EditableDriver | null>(
        null,
    );

    function openCreate() {
        setSelectedDriver(null);
        setDialogMode('create');
        setDialogOpen(true);
    }

    function openEdit(driver: EditableDriver) {
        setSelectedDriver(driver);
        setDialogMode('edit');
        setDialogOpen(true);
    }

    const tableMeta: DriverTableMeta = useMemo(
        () => ({ onEdit: openEdit }),
        [],
    );

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
    } = useServerTable<Driver>({
        data: paginatedDrivers,
        columns,
        meta: tableMeta,
    });

    const selectedMunicipalityId =
        activeFilters['municipality_id']?.[0] ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Conductores" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor="drivers-municipality"
                            className="text-xs text-muted-foreground"
                        >
                            Municipio de residencia
                        </Label>
                        <MunicipalityCombobox
                            id="drivers-municipality"
                            municipalities={municipalities}
                            value={selectedMunicipalityId}
                            onChange={(value) =>
                                setFilter(
                                    'municipality_id',
                                    value ? [value] : [],
                                )
                            }
                            placeholder="Todos los municipios"
                            className="w-64"
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
                    searchPlaceholder="Buscar por identificación, nombre o apellido..."
                    filters={driverFilters}
                    activeFilters={activeFilters}
                    onFilterChange={setFilter}
                    onClearFilters={clearFilters}
                    getRowClassName={rowTintFor}
                    actions={
                        <Button onClick={openCreate} size="sm">
                            <PlusIcon className="mr-2 size-4" />
                            Crear Conductor
                        </Button>
                    }
                />
            </div>

            <DriverDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                mode={dialogMode}
                driver={selectedDriver}
                municipalities={municipalities}
                documentTypes={documentTypes}
                eps={eps}
                pensionFunds={pensionFunds}
                severanceFunds={severanceFunds}
            />
        </AppLayout>
    );
}
