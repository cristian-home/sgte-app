import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Plus, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    resetPassword as usersResetPassword,
    toggleActive as usersToggleActive,
} from '@/actions/App/Http/Controllers/UserController';
import { AdminTabs } from '@/components/admin/admin-tabs';
import { DeleteUserDialog } from '@/components/admin/delete-user-dialog';
import { UserDialog } from '@/components/admin/user-dialog';
import { DataTable } from '@/components/data-table';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
    const page = usePage();
    const currentUserId = (page.props.auth as { user: { id: number } }).user.id;

    const [dialogOpen, setDialogOpen] = useState(false);
    const [dialogMode, setDialogMode] = useState<'create' | 'edit'>('create');
    const [selectedUser, setSelectedUser] = useState<UserRow | null>(null);

    const [deleteOpen, setDeleteOpen] = useState(false);
    const [userToDelete, setUserToDelete] = useState<UserRow | null>(null);

    const [pendingIds, setPendingIds] = useState<ReadonlySet<number>>(
        () => new Set(),
    );
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const errorTimerRef = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );

    useEffect(
        () => () => {
            if (errorTimerRef.current) clearTimeout(errorTimerRef.current);
        },
        [],
    );

    function flashError(message: string) {
        setErrorMessage(message);
        if (errorTimerRef.current) clearTimeout(errorTimerRef.current);
        errorTimerRef.current = setTimeout(() => setErrorMessage(null), 5000);
    }

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

    async function toggleActive(user: UserRow) {
        if (user.id === currentUserId) {
            flashError('No puedes desactivar tu propia cuenta.');
            return;
        }
        if (pendingIds.has(user.id)) return;

        setPendingIds((prev) => {
            const next = new Set(prev);
            next.add(user.id);
            return next;
        });

        try {
            const xsrf = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((c) => c.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );
            const res = await fetch(usersToggleActive(user.id).url, {
                method: 'PATCH',
                credentials: 'include',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrf,
                },
            });

            if (res.ok) {
                const json = (await res.json()) as { user: UserRow };
                mutateRow(
                    (r) => r.id === user.id,
                    () => json.user,
                );
                return;
            }

            if (res.status === 422) {
                const body = (await res.json()) as {
                    errors?: Record<string, string[]>;
                    message?: string;
                };
                const firstError =
                    body.errors &&
                    Object.values(body.errors)[0]?.[0] !== undefined
                        ? Object.values(body.errors)[0][0]
                        : null;
                flashError(
                    firstError ??
                        body.message ??
                        'No se pudo cambiar el estado.',
                );
                return;
            }

            if (res.status === 403) {
                flashError('No tienes permiso para esta acción.');
                return;
            }

            flashError('No se pudo cambiar el estado. Intenta de nuevo.');
        } catch {
            flashError('Error de red. Intenta de nuevo.');
        } finally {
            setPendingIds((prev) => {
                const next = new Set(prev);
                next.delete(user.id);
                return next;
            });
        }
    }

    const tableMeta: UserTableMeta = useMemo(
        () => ({
            onEdit: openEdit,
            onDelete: openDelete,
            onResetPassword: resetPassword,
            onToggleActive: toggleActive,
            currentUserId,
            pendingIds,
        }),
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [currentUserId, pendingIds],
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
        mutateRow,
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
                        <p className="text-sm text-muted-foreground">
                            Gestiona las cuentas que acceden al sistema.
                        </p>
                    </div>
                    <Button onClick={openCreate} className="gap-1.5">
                        <Plus className="size-4" />
                        Nuevo usuario
                    </Button>
                </div>

                <AdminTabs current="users" />

                {errorMessage && (
                    <Alert
                        variant="destructive"
                        role="alert"
                        className="flex items-start justify-between gap-3"
                    >
                        <div className="flex flex-1 items-start gap-2">
                            <AlertTriangle className="size-4" />
                            <div>
                                <AlertTitle>No se pudo completar</AlertTitle>
                                <AlertDescription>
                                    {errorMessage}
                                </AlertDescription>
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-7 shrink-0"
                            onClick={() => setErrorMessage(null)}
                            aria-label="Cerrar"
                        >
                            <X className="size-4" />
                        </Button>
                    </Alert>
                )}

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
