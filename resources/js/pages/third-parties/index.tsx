import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table';
import MunicipalityCombobox, {
    type MunicipalityOption,
} from '@/components/municipality-combobox';
import ThirdPartyCreateDialog from '@/components/third-parties/third-party-create-dialog';
import { type DocumentTypeOption } from '@/components/third-parties/third-party-form';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import thirdParties from '@/routes/third-parties';

import { columns } from './columns';

import type { BreadcrumbItem, FilterDefinition, PaginatedData } from '@/types';
import type { ThirdParty } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Terceros', href: thirdParties.index().url },
];

const thirdPartyFilters: FilterDefinition[] = [
    {
        name: 'active',
        label: 'Estado',
        options: [
            { value: '1', label: 'Activo' },
            { value: '0', label: 'Inactivo' },
        ],
    },
    {
        name: 'is_natural_person',
        label: 'Tipo persona',
        options: [
            { value: '1', label: 'Natural' },
            { value: '0', label: 'Jurídica' },
        ],
    },
    {
        name: 'is_customer',
        label: 'Es cliente',
        options: [
            { value: '1', label: 'Sí' },
            { value: '0', label: 'No' },
        ],
    },
    {
        name: 'is_provider',
        label: 'Es proveedor',
        options: [
            { value: '1', label: 'Sí' },
            { value: '0', label: 'No' },
        ],
    },
];

// Negative-test marker: this index intentionally does NOT pass
// getRowClassName to <DataTable>. Third parties have no compliance
// axis (no expiring documents, no license state machine), so there's
// nothing to row-tint. If any future change tries to add row tinting
// here, that's a signal the abstraction is leaking — please open a
// discussion before adding it.

export default function ThirdPartiesIndex({
    thirdParties: paginatedThirdParties,
    municipalities,
    documentTypes,
}: {
    thirdParties: PaginatedData<ThirdParty>;
    municipalities: MunicipalityOption[];
    documentTypes: DocumentTypeOption[];
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
    } = useServerTable<ThirdParty>({
        data: paginatedThirdParties,
        columns,
    });

    const selectedMunicipalityId =
        activeFilters['municipality_id']?.[0] ?? null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Terceros" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor="third-parties-municipality"
                            className="text-xs text-muted-foreground"
                        >
                            Municipio
                        </Label>
                        <MunicipalityCombobox
                            id="third-parties-municipality"
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
                    searchPlaceholder="Buscar por identificación, nombre o razón social..."
                    filters={thirdPartyFilters}
                    activeFilters={activeFilters}
                    onFilterChange={setFilter}
                    onClearFilters={clearFilters}
                    actions={
                        <Button onClick={() => setCreateOpen(true)} size="sm">
                            <PlusIcon className="mr-2 size-4" />
                            Crear Tercero
                        </Button>
                    }
                />
            </div>

            <ThirdPartyCreateDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                documentTypes={documentTypes}
                municipalities={municipalities}
            />
        </AppLayout>
    );
}
