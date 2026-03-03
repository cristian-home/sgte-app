import { Head, Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Can } from '@/components/can';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
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

export default function ServicesIndex({
    services: paginatedServices,
}: {
    services: PaginatedData<Service>;
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Servicios" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
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
