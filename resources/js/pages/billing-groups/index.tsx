import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import BillingGroupController from '@/actions/App/Http/Controllers/BillingGroupController';
import BillingGroupDialog from '@/components/billing-groups/billing-group-dialog';
import {
    DataTable,
    DataTableColumnHeader,
    DataTableRowActions,
} from '@/components/data-table';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';

import type { ColumnDef } from '@tanstack/react-table';
import type { BillingGroup, BreadcrumbItem } from '@/types';

type BillingGroupRow = BillingGroup & { services_count?: number };

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Grupos de Facturación',
        href: BillingGroupController.index.url(),
    },
];

const columns: ColumnDef<BillingGroupRow>[] = [
    {
        accessorKey: 'code',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Código" />
        ),
        cell: ({ row }) => (
            <span className="font-mono text-sm">{row.original.code}</span>
        ),
    },
    {
        accessorKey: 'name',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Nombre" />
        ),
    },
    {
        accessorKey: 'services_count',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Servicios" />
        ),
        cell: ({ row }) => (
            <Badge variant="secondary">
                {row.original.services_count ?? 0}
            </Badge>
        ),
    },
    {
        accessorKey: 'active',
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Activo" />
        ),
        cell: ({ row }) =>
            row.original.active ? (
                <Check className="size-4 text-green-600" />
            ) : (
                <X className="size-4 text-muted-foreground" />
            ),
    },
];

export default function BillingGroupsIndex({
    billingGroups,
}: {
    billingGroups: BillingGroupRow[];
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
    const [selected, setSelected] = useState<BillingGroup | null>(null);

    function openCreate() {
        setSelected(null);
        setDialogMode('create');
        setDialogOpen(true);
    }

    function openEdit(record: BillingGroup) {
        setSelected(record);
        setDialogMode('edit');
        setDialogOpen(true);
    }

    const columnsWithActions: ColumnDef<BillingGroupRow>[] = [
        ...columns,
        {
            id: 'actions',
            cell: ({ row }) => (
                <DataTableRowActions
                    onEdit={() => openEdit(row.original)}
                    onDelete={() =>
                        router.delete(
                            BillingGroupController.destroy.url(row.original.id),
                            { preserveScroll: true },
                        )
                    }
                />
            ),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Grupos de Facturación" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <PageHeader
                    title="Grupos de Facturación"
                    onCreate={openCreate}
                    createLabel="Nuevo Grupo"
                />
                <DataTable
                    columns={columnsWithActions}
                    data={billingGroups}
                    searchKey="name"
                    searchPlaceholder="Buscar por nombre..."
                />
            </div>
            <BillingGroupDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                mode={dialogMode}
                billingGroup={selected}
            />
        </AppLayout>
    );
}
