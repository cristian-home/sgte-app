import { Head, router } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import serviceIncidents from '@/routes/service-incidents';

import {
    columns,
    incidentSeverityRowTint,
    type ServiceIncidentRow,
} from './columns';

import type { Row } from '@tanstack/react-table';
import type { BreadcrumbItem, FilterDefinition, PaginatedData } from '@/types';

interface IncidentTypeOption {
    id: number;
    code: string;
    name: string;
    severity: string;
    affects_billing_default: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Novedades', href: serviceIncidents.index().url },
];

function rowTintFor(row: Row<ServiceIncidentRow>): string | undefined {
    return incidentSeverityRowTint(
        row.original.incident_type?.severity ?? null,
    );
}

export default function ServiceIncidentsIndex({
    serviceIncidents: paginatedIncidents,
    incidentTypes,
}: {
    serviceIncidents: PaginatedData<ServiceIncidentRow>;
    incidentTypes: IncidentTypeOption[];
}) {
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
    } = useServerTable<ServiceIncidentRow>({
        data: paginatedIncidents,
        columns,
    });

    const incidentFilters: FilterDefinition[] = [
        {
            name: 'incident_type_id',
            label: 'Tipo',
            options: incidentTypes.map((t) => ({
                value: String(t.id),
                label: t.name,
            })),
        },
        {
            name: 'severity',
            label: 'Severidad',
            options: [
                { value: 'informational', label: 'Informativo' },
                { value: 'minor', label: 'Menor' },
                { value: 'major', label: 'Mayor' },
            ],
        },
        {
            name: 'is_driver_report',
            label: 'Reporte del conductor',
            options: [
                { value: '1', label: 'Sí' },
                { value: '0', label: 'No' },
            ],
        },
        {
            name: 'affects_billing',
            label: 'Afecta facturación',
            options: [
                { value: '1', label: 'Sí' },
                { value: '0', label: 'No' },
            ],
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Novedades" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <DataTable
                    table={table}
                    paginatedData={paginatedData}
                    search={search}
                    onSearchChange={setSearch}
                    loading={loading}
                    onNavigate={onNavigate}
                    onPerPageChange={onPerPageChange}
                    searchPlaceholder="Buscar por descripción..."
                    filters={incidentFilters}
                    activeFilters={activeFilters}
                    onFilterChange={setFilter}
                    onClearFilters={clearFilters}
                    getRowClassName={rowTintFor}
                    actions={
                        <Button
                            onClick={() =>
                                router.visit(serviceIncidents.create().url)
                            }
                            size="sm"
                        >
                            <PlusIcon className="mr-2 size-4" />
                            Crear Novedad
                        </Button>
                    }
                />
            </div>
        </AppLayout>
    );
}
