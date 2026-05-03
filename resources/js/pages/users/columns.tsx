import { formatDistanceToNow, parseISO } from 'date-fns';
import { es } from 'date-fns/locale';
import { UserAvatar } from '@/components/admin/user-avatar';
import { UserRowActions } from '@/components/admin/user-row-actions';
import { DataTableColumnHeader } from '@/components/data-table';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Role as RoleEnum } from '@/enums/Role';
import { cn } from '@/lib/utils';

import type { ColumnDef, Table } from '@tanstack/react-table';

export interface UserRow {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string | null;
    roles: { id: number; name: string; label: string }[];
}

export interface UserTableMeta {
    onEdit: (user: UserRow) => void;
    onDelete: (user: UserRow) => void;
    onResetPassword: (user: UserRow) => void;
    onToggleActive: (user: UserRow) => void;
}

function lastLoginText(iso: string | null): string {
    if (!iso) return 'Nunca';
    try {
        return formatDistanceToNow(parseISO(iso), {
            addSuffix: true,
            locale: es,
        });
    } catch {
        return iso;
    }
}

function meta(table: Table<UserRow>): UserTableMeta {
    return table.options.meta as UserTableMeta;
}

export const columns: ColumnDef<UserRow, unknown>[] = [
    {
        id: 'name',
        meta: { label: 'Nombre' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Nombre" />
        ),
        accessorKey: 'name',
        cell: ({ row }) => (
            <div className="flex items-center gap-3">
                <UserAvatar id={row.original.id} name={row.original.name} />
                <span className="font-medium">{row.original.name}</span>
            </div>
        ),
    },
    {
        id: 'email',
        meta: { label: 'Correo' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Correo" />
        ),
        accessorKey: 'email',
        cell: ({ row }) => (
            <span className="text-muted-foreground tabular-nums">
                {row.original.email}
            </span>
        ),
    },
    {
        id: 'roles',
        meta: { label: 'Roles' },
        header: 'Roles',
        cell: ({ row }) => (
            <div className="flex flex-wrap gap-1">
                {row.original.roles.map((r) => (
                    <Badge
                        key={r.id}
                        variant={
                            r.name === RoleEnum.SUPER_ADMIN
                                ? 'default'
                                : 'outline'
                        }
                    >
                        {r.label}
                    </Badge>
                ))}
            </div>
        ),
    },
    {
        id: 'last_login_at',
        meta: { label: 'Último acceso' },
        header: ({ column }) => (
            <DataTableColumnHeader column={column} title="Último acceso" />
        ),
        accessorKey: 'last_login_at',
        cell: ({ row }) => (
            <span className="text-muted-foreground tabular-nums">
                {lastLoginText(row.original.last_login_at)}
            </span>
        ),
    },
    {
        id: 'is_active',
        meta: { label: 'Estado' },
        header: 'Estado',
        cell: ({ row, table }) => {
            const { onToggleActive } = meta(table);
            return (
                <div className="flex items-center gap-2">
                    <Switch
                        checked={row.original.is_active}
                        onCheckedChange={() => onToggleActive(row.original)}
                        aria-label={
                            row.original.is_active
                                ? 'Desactivar usuario'
                                : 'Activar usuario'
                        }
                    />
                    <Badge
                        variant={
                            row.original.is_active ? 'default' : 'secondary'
                        }
                        className={cn(
                            row.original.is_active &&
                                'bg-emerald-500/15 text-emerald-700 hover:bg-emerald-500/20 dark:text-emerald-400',
                        )}
                    >
                        {row.original.is_active ? 'Activo' : 'Inactivo'}
                    </Badge>
                </div>
            );
        },
    },
    {
        id: 'actions',
        meta: { label: 'Acciones' },
        header: '',
        cell: ({ row, table }) => {
            const m = meta(table);
            return (
                <div className="flex justify-end">
                    <UserRowActions
                        isActive={row.original.is_active}
                        onEdit={() => m.onEdit(row.original)}
                        onResetPassword={() => m.onResetPassword(row.original)}
                        onToggleActive={() => m.onToggleActive(row.original)}
                        onDelete={() => m.onDelete(row.original)}
                    />
                </div>
            );
        },
    },
];
