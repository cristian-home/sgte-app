import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Lock, Pencil } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { update as rolesUpdate } from '@/actions/App/Http/Controllers/RoleController';
import { AdminTabs } from '@/components/admin/admin-tabs';
import {
    PermissionMatrix,
    type PermissionGroupBlock,
} from '@/components/admin/permission-matrix';
import { SaveBar } from '@/components/admin/save-bar';
import { UserAvatar } from '@/components/admin/user-avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import roles from '@/routes/roles';
import users from '@/routes/users';

import type { BreadcrumbItem } from '@/types';

interface RoleDetail {
    id: number;
    name: string;
    label: string;
    description: string | null;
    users_count: number;
    locked: boolean;
}

interface AssignedUser {
    id: number;
    name: string;
    email: string;
}

interface Props {
    role: RoleDetail;
    users: AssignedUser[];
    permissionGroups: PermissionGroupBlock[];
    assignedPermissions: string[];
}

export default function RoleShow({
    role,
    users: roleUsers,
    permissionGroups,
    assignedPermissions,
}: Props) {
    const baseline = useMemo(
        () => new Set(assignedPermissions),
        [assignedPermissions],
    );

    const [current, setCurrent] = useState<Set<string>>(
        () => new Set(assignedPermissions),
    );
    const [description, setDescription] = useState<string>(
        role.description ?? '',
    );
    const [editingDescription, setEditingDescription] = useState(false);
    const [saving, setSaving] = useState(false);
    const [expandSignal, setExpandSignal] = useState<boolean | null>(null);
    const textareaRef = useRef<HTMLTextAreaElement | null>(null);

    useEffect(() => {
        if (editingDescription) {
            textareaRef.current?.focus();
        }
    }, [editingDescription]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Administración', href: '#' },
        { title: 'Roles', href: roles.index().url },
        { title: role.label, href: roles.show(role.name).url },
    ];

    const dirty = useMemo(() => {
        if (description !== (role.description ?? '')) return true;
        if (current.size !== baseline.size) return true;
        for (const p of current) if (!baseline.has(p)) return true;
        for (const p of baseline) if (!current.has(p)) return true;
        return false;
    }, [current, baseline, description, role.description]);

    const added = useMemo(
        () => [...current].filter((p) => !baseline.has(p)),
        [current, baseline],
    );
    const removed = useMemo(
        () => [...baseline].filter((p) => !current.has(p)),
        [current, baseline],
    );
    const totalPermissions = useMemo(
        () =>
            permissionGroups.reduce(
                (acc, g) => acc + g.permissions.length,
                0,
            ),
        [permissionGroups],
    );

    function discard() {
        setCurrent(new Set(assignedPermissions));
        setDescription(role.description ?? '');
        setEditingDescription(false);
    }

    function save() {
        setSaving(true);
        router.put(
            rolesUpdate(role.name).url,
            {
                description: description === '' ? null : description,
                permissions: [...current],
            },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Rol — ${role.label}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-6 pb-28">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <h1 className="text-[26px] font-semibold tracking-tight">
                                {role.label}
                            </h1>
                            {role.locked && (
                                <span className="bg-muted text-muted-foreground inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs">
                                    <Lock className="size-3" /> Bloqueado
                                </span>
                            )}
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Edita la información del rol y los permisos que
                            otorga.
                        </p>
                    </div>
                    <Button asChild variant="outline" size="sm">
                        <Link href={roles.index().url}>
                            <ChevronLeft className="size-4" /> Volver a roles
                        </Link>
                    </Button>
                </div>

                <AdminTabs current="roles" />

                <div className="grid gap-4 lg:grid-cols-[340px_1fr] lg:items-start">
                    <div className="flex flex-col gap-4 lg:sticky lg:top-[76px]">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Información del rol
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                <div>
                                    <span className="text-muted-foreground text-xs">
                                        Nombre
                                    </span>
                                    <p className="text-sm font-medium">
                                        {role.label}
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">
                                        Etiqueta
                                    </span>
                                    <p>
                                        <code className="bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-xs">
                                            {role.name}
                                        </code>
                                    </p>
                                </div>
                                <div>
                                    <span className="text-muted-foreground text-xs">
                                        Descripción
                                    </span>
                                    {role.locked ? (
                                        <p className="text-muted-foreground text-sm">
                                            {role.description ??
                                                'Sin descripción.'}
                                        </p>
                                    ) : editingDescription ? (
                                        <textarea
                                            ref={textareaRef}
                                            value={description}
                                            onChange={(e) =>
                                                setDescription(e.target.value)
                                            }
                                            onBlur={() =>
                                                setEditingDescription(false)
                                            }
                                            rows={3}
                                            className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-2 py-1 text-sm focus-visible:ring-2 focus-visible:outline-none"
                                        />
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setEditingDescription(true)
                                            }
                                            className="hover:bg-muted/40 flex w-full items-start gap-1.5 rounded p-1 text-left text-sm transition-colors"
                                        >
                                            <span className="flex-1">
                                                {description ||
                                                    'Sin descripción.'}
                                            </span>
                                            <Pencil className="text-muted-foreground size-3" />
                                        </button>
                                    )}
                                </div>
                                <Separator />
                                <div className="flex items-baseline gap-6">
                                    <div>
                                        <p className="text-[20px] font-semibold tabular-nums">
                                            {current.size}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            permisos
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-[20px] font-semibold tabular-nums">
                                            {role.users_count}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            usuarios
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <div>
                                    <CardTitle className="text-base">
                                        Usuarios con este rol
                                    </CardTitle>
                                    <p className="text-muted-foreground text-xs">
                                        {role.users_count}{' '}
                                        {role.users_count === 1
                                            ? 'usuario'
                                            : 'usuarios'}
                                    </p>
                                </div>
                                <Button
                                    asChild
                                    variant="ghost"
                                    size="sm"
                                    className="gap-0.5"
                                >
                                    <Link
                                        href={
                                            users.index({
                                                query: {
                                                    'filter[roles]': role.name,
                                                },
                                            }).url
                                        }
                                    >
                                        Ver todos
                                        <ChevronRight className="size-3.5" />
                                    </Link>
                                </Button>
                            </CardHeader>
                            <CardContent>
                                {roleUsers.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        Aún no hay usuarios con este rol.
                                    </p>
                                ) : (
                                    <div className="flex items-center">
                                        {roleUsers.map((u, idx) => (
                                            <UserAvatar
                                                key={u.id}
                                                id={u.id}
                                                name={u.name}
                                                size={28}
                                                className={
                                                    idx === 0
                                                        ? ''
                                                        : '-ml-2 ring-2 ring-card'
                                                }
                                            />
                                        ))}
                                        {role.users_count > roleUsers.length && (
                                            <span className="text-muted-foreground ml-2 text-xs">
                                                +
                                                {role.users_count -
                                                    roleUsers.length}{' '}
                                                más
                                            </span>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="overflow-hidden">
                        <CardHeader className="flex flex-row items-start justify-between gap-3">
                            <div>
                                <CardTitle className="text-base">
                                    Permisos
                                </CardTitle>
                                <p className="text-muted-foreground text-xs">
                                    {role.locked
                                        ? 'Este rol omite las verificaciones de permisos y tiene acceso completo.'
                                        : `${current.size} permisos activos de ${totalPermissions}.`}
                                </p>
                            </div>
                            {!role.locked && (
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setExpandSignal(true)}
                                    >
                                        Expandir todo
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setExpandSignal(false)}
                                    >
                                        Contraer
                                    </Button>
                                </div>
                            )}
                        </CardHeader>
                        <CardContent className="p-0">
                            <PermissionMatrix
                                groups={permissionGroups}
                                assigned={current}
                                onChange={setCurrent}
                                locked={role.locked}
                                expandSignal={expandSignal}
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>

            <SaveBar
                visible={dirty && !role.locked}
                changeCount={added.length + removed.length}
                addedCount={added.length}
                removedCount={removed.length}
                onDiscard={discard}
                onSave={save}
                saving={saving}
            />
        </AppLayout>
    );
}
