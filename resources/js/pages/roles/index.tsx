import { Head, Link } from '@inertiajs/react';
import { Lock, Shield, ShieldCheck } from 'lucide-react';
import { show as rolesShow } from '@/actions/App/Http/Controllers/RoleController';
import { AdminTabs } from '@/components/admin/admin-tabs';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import roles from '@/routes/roles';

import type { BreadcrumbItem } from '@/types';

interface RoleCard {
    id: number;
    name: string;
    label: string;
    description: string | null;
    users_count: number;
    permissions_count: number;
    locked: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Roles', href: roles.index().url },
];

export default function RolesIndex({ roles: items }: { roles: RoleCard[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Roles" />
            <div className="flex h-full flex-1 flex-col gap-4 p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-[26px] font-semibold tracking-tight">
                        Roles
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        Define qué puede hacer cada conjunto de usuarios dentro
                        del sistema.
                    </p>
                </div>

                <AdminTabs current="roles" />

                <div className="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(320px,1fr))]">
                    {items.map((role) => (
                        <Card key={role.id} className="flex flex-col gap-3 p-5">
                            <div className="flex items-start gap-3">
                                <span
                                    className={cn(
                                        'inline-flex size-10 shrink-0 items-center justify-center rounded-md',
                                        role.locked
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {role.locked ? (
                                        <ShieldCheck className="size-5" />
                                    ) : (
                                        <Shield className="size-5" />
                                    )}
                                </span>
                                <div className="flex flex-1 flex-col gap-0.5">
                                    <div className="flex items-center gap-1.5">
                                        <h3 className="text-[17px] font-semibold tracking-tight">
                                            {role.label}
                                        </h3>
                                        {role.locked && (
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Lock className="text-muted-foreground size-3.5" />
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        Este rol omite todas las
                                                        verificaciones de
                                                        permisos.
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        )}
                                    </div>
                                    <code className="text-muted-foreground text-[11.5px]">
                                        {role.name}
                                    </code>
                                </div>
                            </div>
                            <p className="text-muted-foreground min-h-10 text-[13px] leading-relaxed">
                                {role.description ?? 'Sin descripción.'}
                            </p>
                            <Separator />
                            <div className="flex items-baseline gap-6">
                                <div className="flex flex-col">
                                    <span className="text-[20px] font-semibold tabular-nums">
                                        {role.users_count}
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        usuarios
                                    </span>
                                </div>
                                <div className="flex flex-col">
                                    <span className="text-[20px] font-semibold tabular-nums">
                                        {role.permissions_count}
                                    </span>
                                    <span className="text-muted-foreground text-xs">
                                        permisos
                                    </span>
                                </div>
                            </div>
                            <div className="flex items-center justify-between gap-2">
                                {role.locked ? (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled
                                    >
                                        Editar
                                    </Button>
                                ) : (
                                    <Button asChild variant="outline" size="sm">
                                        <Link
                                            href={rolesShow(role.name).url}
                                        >
                                            Editar
                                        </Link>
                                    </Button>
                                )}
                                <Button asChild variant="ghost" size="sm">
                                    <Link href={rolesShow(role.name).url}>
                                        Ver detalles
                                    </Link>
                                </Button>
                            </div>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
