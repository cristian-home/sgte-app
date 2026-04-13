import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface User {
    id: number;
    name: string;
    email: string;
    roles: { id: number; name: string }[];
    created_at: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Usuarios', href: '/users' },
    { title: 'Detalle', href: '#' },
];

export default function UsersShow({ user }: { user: User }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={user.name} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card className="max-w-2xl">
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>{user.name}</CardTitle>
                        <Button asChild>
                            <Link href={`/users/${user.id}/edit`}>
                                <Pencil className="mr-1 size-4" />
                                Editar
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Correo
                            </p>
                            <p className="font-medium">{user.email}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Roles
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {user.roles.map((role) => (
                                    <Badge key={role.id} variant="outline">
                                        {role.name}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
