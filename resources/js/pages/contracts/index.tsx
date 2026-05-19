import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';
import ContractCreateDialog from '@/components/contracts/contract-create-dialog';
import { contractRowTint } from '@/components/contracts/contract-period-pill';
import { DataTable } from '@/components/data-table';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import ThirdPartyCombobox, {
    type ThirdPartyOption,
} from '@/components/third-parties/third-party-combobox';
import { type DocumentTypeOption } from '@/components/third-parties/third-party-form';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import contracts from '@/routes/contracts';

import { columns, type ContractRow } from './columns';

import type { Row } from '@tanstack/react-table';
import type { BreadcrumbItem, FilterDefinition, PaginatedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contratos', href: contracts.index().url },
];

const contractFilters: FilterDefinition[] = [
    {
        name: 'contract_status',
        label: 'Estado',
        options: [
            { value: 'vigente', label: 'Vigente' },
            { value: 'por_vencer', label: 'Por vencer (≤60 días)' },
            { value: 'vencido', label: 'Vencido' },
            { value: 'inactivo', label: 'Inactivo' },
        ],
    },
    {
        name: 'contract_object',
        label: 'Objeto',
        options: [
            { value: 'business', label: 'Empresarial' },
            { value: 'tourism', label: 'Turismo' },
            { value: 'health', label: 'Salud' },
            { value: 'occasional', label: 'Ocasional' },
        ],
    },
    {
        name: 'active',
        label: 'Activo',
        options: [
            { value: '1', label: 'Sí' },
            { value: '0', label: 'No' },
        ],
    },
    {
        name: 'is_generic',
        label: 'Genérico',
        options: [
            { value: '1', label: 'Sí' },
            { value: '0', label: 'No' },
        ],
    },
];

function rowTintFor(row: Row<ContractRow>): string | undefined {
    return contractRowTint(row.original);
}

export default function ContractsIndex({
    contracts: paginatedContracts,
    thirdParties,
    documentTypes = [],
    municipalities = [],
}: {
    contracts: PaginatedData<ContractRow>;
    thirdParties: ThirdPartyOption[];
    documentTypes?: DocumentTypeOption[];
    municipalities?: MunicipalityOption[];
}) {
    const [createOpen, setCreateOpen] = useState(false);

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
    } = useServerTable<ContractRow>({
        data: paginatedContracts,
        columns,
    });

    const selectedThirdPartyId = activeFilters['third_party_id']?.[0] ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Contratos" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor="contracts-third-party"
                            className="text-xs text-muted-foreground"
                        >
                            Cliente
                        </Label>
                        <ThirdPartyCombobox
                            id="contracts-third-party"
                            thirdParties={thirdParties}
                            role="customer"
                            value={selectedThirdPartyId}
                            onChange={(value) =>
                                setFilter(
                                    'third_party_id',
                                    value ? [value] : [],
                                )
                            }
                            placeholder="Todos los clientes"
                            className="w-72"
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
                    searchPlaceholder="Buscar por número de contrato..."
                    filters={contractFilters}
                    activeFilters={activeFilters}
                    onFilterChange={setFilter}
                    onClearFilters={clearFilters}
                    getRowClassName={rowTintFor}
                    actions={
                        <Button onClick={() => setCreateOpen(true)} size="sm">
                            <PlusIcon className="mr-2 size-4" />
                            Crear Contrato
                        </Button>
                    }
                />
            </div>

            <ContractCreateDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                thirdParties={thirdParties}
                documentTypes={documentTypes}
                municipalities={municipalities}
            />
        </AppLayout>
    );
}
