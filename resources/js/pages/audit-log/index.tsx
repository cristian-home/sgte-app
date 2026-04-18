import { Head } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AuditLogDetailSheet from '@/components/audit-log/audit-log-detail-sheet';
import { DataTable } from '@/components/data-table';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UserCombobox, {
    type UserOption,
} from '@/components/users/user-combobox';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { type ActivityRow, type SubjectTypeOption } from '@/types/audit-log';

import { auditLogColumns, type AuditLogTableMeta } from './columns';

import type { Row } from '@tanstack/react-table';
import type { BreadcrumbItem, FilterDefinition, PaginatedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Auditoría', href: '/audit-log' },
];

const EVENT_OPTIONS = [
    { value: 'created', label: 'Creado' },
    { value: 'updated', label: 'Actualizado' },
    { value: 'deleted', label: 'Eliminado' },
    { value: 'restored', label: 'Restaurado' },
];

function rowTintFor(row: Row<ActivityRow>): string | undefined {
    if (row.original.properties?.edited_on_executed_day === true) {
        return 'bg-amber-500/10 hover:bg-amber-500/15';
    }
    return undefined;
}

interface AuditLogIndexProps {
    activities: PaginatedData<ActivityRow>;
    users: UserOption[];
    subjectTypes: SubjectTypeOption[];
}

export default function AuditLogIndex({
    activities,
    users,
    subjectTypes,
}: AuditLogIndexProps) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [selectedActivity, setSelectedActivity] =
        useState<ActivityRow | null>(null);

    const auditLogFilters = useMemo<FilterDefinition[]>(
        () => [
            {
                name: 'subject_type',
                label: 'Entidad',
                options: subjectTypes.map((option) => ({
                    value: option.value,
                    label: option.label,
                })),
            },
            {
                name: 'event',
                label: 'Acción',
                options: EVENT_OPTIONS,
            },
        ],
        [subjectTypes],
    );

    const tableMeta = useMemo<AuditLogTableMeta>(
        () => ({
            subjectTypes,
            onSelect: (activity: ActivityRow) => {
                setSelectedActivity(activity);
                setSheetOpen(true);
            },
        }),
        [subjectTypes],
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
    } = useServerTable<ActivityRow>({
        data: activities,
        columns: auditLogColumns,
        meta: tableMeta,
    });

    const selectedCauserId = activeFilters['causer_id']?.[0] ?? null;
    const createdFrom = activeFilters['created_from']?.[0] ?? '';
    const createdTo = activeFilters['created_to']?.[0] ?? '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Auditoría" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex flex-wrap items-end gap-3">
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor="audit-log-causer"
                            className="text-xs text-muted-foreground"
                        >
                            Usuario
                        </Label>
                        <UserCombobox
                            id="audit-log-causer"
                            users={users}
                            value={selectedCauserId}
                            onChange={(value) =>
                                setFilter(
                                    'causer_id',
                                    value === null ? [] : [String(value)],
                                )
                            }
                            placeholder="Todos los usuarios"
                            className="w-64"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor="audit-log-from"
                            className="text-xs text-muted-foreground"
                        >
                            Desde
                        </Label>
                        <Input
                            id="audit-log-from"
                            type="date"
                            value={createdFrom}
                            className="w-40"
                            onChange={(event) =>
                                setFilter(
                                    'created_from',
                                    event.target.value
                                        ? [event.target.value]
                                        : [],
                                )
                            }
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor="audit-log-to"
                            className="text-xs text-muted-foreground"
                        >
                            Hasta
                        </Label>
                        <Input
                            id="audit-log-to"
                            type="date"
                            value={createdTo}
                            className="w-40"
                            onChange={(event) =>
                                setFilter(
                                    'created_to',
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
                    searchPlaceholder="Buscar descripción..."
                    filters={auditLogFilters}
                    activeFilters={activeFilters}
                    onFilterChange={setFilter}
                    onClearFilters={clearFilters}
                    getRowClassName={rowTintFor}
                />
            </div>

            <AuditLogDetailSheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                activity={selectedActivity}
                subjectTypes={subjectTypes}
            />
        </AppLayout>
    );
}
