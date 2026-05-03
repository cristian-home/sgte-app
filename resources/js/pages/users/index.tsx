import { Head, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    resetPassword as usersResetPassword,
    toggleActive as usersToggleActive,
} from '@/actions/App/Http/Controllers/UserController';
import { AdminTabs } from '@/components/admin/admin-tabs';
import { DeleteUserDialog } from '@/components/admin/delete-user-dialog';
import { UserDialog } from '@/components/admin/user-dialog';
import { DataTable } from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { useServerTable } from '@/hooks/use-server-table';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import users from '@/routes/users';

import { columns, type UserRow, type UserTableMeta } from './columns';

import type { Row } from '@tanstack/react-table';
import type { BreadcrumbItem, FilterDefinition, PaginatedData } from '@/types';

interface RoleOption {
    value: string;
    label: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Usuarios', href: users.index().url },
];

function rowTint(row: Row<UserRow>): string | undefined {
    return row.original.is_active ? undefined : 'opacity-60';
}

export default function UsersIndex({
    users: paginated,
    availableRoles,
}: {
    users: PaginatedData<UserRow>;
    availableRoles: RoleOption[];
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
    const [selectedUser, setSelectedUser] = useState<UserRow | null>(null);

    const [deleteOpen, setDeleteOpen] = useState(false);
    const [userToDelete, setUserToDelete] = useState<UserRow | null>(null);

    function openCreate() {
        setSelectedUser(null);
        setDialogMode('create');
        setDialogOpen(true);
    }

    function openEdit(user: UserRow) {
        setSelectedUser(user);
        setDialogMode('edit');
        setDialogOpen(true);
    }

    function openDelete(user: UserRow) {
        setUserToDelete(user);
        setDeleteOpen(true);
    }

    function resetPassword(user: UserRow) {
        if (
            !confirm(
                `Se enviará un enlace para restablecer la contraseña de ${user.name}. ¿Continuar?`,
            )
        ) {
            return;
        }
        router.post(usersResetPassword(user.id).url, undefined, {
            preserveScroll: true,
        });
    }

    function toggleActive(user: UserRow) {
        router.patch(usersToggleActive(user.id).url, undefined, {
            preserveScroll: true,
        });
    }

    const tableMeta: UserTableMeta = useMemo(
        () => ({
            onEdit: openEdit,
            onDelete: openDelete,
            onResetPassword: resetPassword,
            onToggleActive: toggleActive,
        }),
         
        [],
    );

    const filterDefs: FilterDefinition[] = useMemo(
        () => [
            {
                name: 'roles',
                label: 'Rol',
                options: availableRoles.map((r) => ({
                    value: r.value,
                    label: r.label,
                })),
            },
            {
                name: 'is_active',
                label: 'Estado',
                options: [
                    { value: 'true', label: 'Activo' },
                    { value: 'false', label: 'Inactivo' },
                ],
            },
        ],
        [availableRoles],
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
    } = useServerTable<UserRow>({
        data: paginated,
        columns,
        meta: tableMeta,
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuarios" />
            <div className="flex h-full flex-1 flex-col gap-4 p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-[26px] font-semibold tracking-tight">
                            Usuarios
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            Gestiona las cuentas que acceden al sistema.
                        </p>
                    </div>
                    <Button onClick={openCreate} className="gap-1.5">
                        <Plus className="size-4" />
                        Nuevo usuario
                    </Button>
                </div>

                <AdminTabs current="users" />

                <DataTable
                    table={table}
                    paginatedData={paginatedData}
                    search={search}
                    onSearchChange={setSearch}
                    loading={loading}
                    onNavigate={onNavigate}
                    onPerPageChange={onPerPageChange}
                    searchPlaceholder="Buscar por nombre o correo…"
                    filters={filterDefs}
                    activeFilters={activeFilters}
                    onFilterChange={setFilter}
                    onClearFilters={clearFilters}
                    getRowClassName={(r) => cn(rowTint(r))}
                />
            </div>

            <UserDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                mode={dialogMode}
                availableRoles={availableRoles}
                user={selectedUser}
            />
            <DeleteUserDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                user={userToDelete}
            />
        </AppLayout>
    );
}
