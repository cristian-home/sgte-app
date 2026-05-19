import { Head, Link } from '@inertiajs/react';
import EpsController from '@/actions/App/Http/Controllers/EpsController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Eps } from '@/types';

export default function EpsShow({ eps }: { eps: Eps }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'EPS', href: EpsController.index.url() },
        { title: eps.name, href: EpsController.show.url(eps.id) },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={eps.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{eps.name}</CardTitle>
                                <CardDescription>
                                    Código: {eps.code}
                                </CardDescription>
                            </div>
                            <Link href={EpsController.edit.url(eps.id)}>
                                <Button variant="outline">Editar</Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent />
                </Card>
            </div>
        </AppLayout>
    );
}
