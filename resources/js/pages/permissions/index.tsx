import { Head, Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { AdminTabs } from '@/components/admin/admin-tabs';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import permissions from '@/routes/permissions';
import roles from '@/routes/roles';

import type { BreadcrumbItem } from '@/types';

interface PermissionItem {
    key: string;
    label: string;
    description: string;
}

interface PermissionGroupBlock {
    id: string;
    label: string;
    permissions: PermissionItem[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Permisos', href: permissions.index().url },
];

export default function PermissionsIndex({
    groups,
}: {
    groups: PermissionGroupBlock[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Permisos" />
            <div className="flex h-full flex-1 flex-col gap-4 p-6">
                <div className="flex flex-col gap-1">
                    <h1 className="text-[26px] font-semibold tracking-tight">
                        Permisos
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Referencia de todos los permisos disponibles en el
                        sistema, agrupados por módulo.
                    </p>
                </div>

                <AdminTabs current="permissions" />

                <Alert>
                    <AlertTriangle className="size-4" />
                    <AlertTitle>Solo lectura</AlertTitle>
                    <AlertDescription>
                        Los permisos son definidos por la plataforma. Para
                        otorgarlos o revocarlos, edita un rol desde la pestaña{' '}
                        <Link
                            href={roles.index().url}
                            className="text-primary underline-offset-2 hover:underline"
                        >
                            Roles
                        </Link>
                        .
                    </AlertDescription>
                </Alert>

                <div className="grid gap-4 md:grid-cols-2">
                    {groups.map((group) => (
                        <Card key={group.id}>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle className="text-base">
                                    {group.label}
                                </CardTitle>
                                <Badge variant="secondary">
                                    {group.permissions.length}
                                </Badge>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-2">
                                {group.permissions.map((perm) => (
                                    <div
                                        key={perm.key}
                                        className="flex flex-col"
                                    >
                                        <p className="text-sm font-medium">
                                            {perm.label}
                                        </p>
                                        <code className="text-xs text-muted-foreground/80">
                                            {perm.key}
                                        </code>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
