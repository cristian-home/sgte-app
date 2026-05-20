import { Head } from '@inertiajs/react';
import { useState } from 'react';
import EpsController from '@/actions/App/Http/Controllers/EpsController';
import CatalogCodeNameDialog from '@/components/catalogs/catalog-code-name-dialog';
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
    const [editOpen, setEditOpen] = useState(false);

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
                            <Button
                                variant="outline"
                                onClick={() => setEditOpen(true)}
                            >
                                Editar
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent />
                </Card>
            </div>
            <CatalogCodeNameDialog
                open={editOpen}
                onOpenChange={setEditOpen}
                mode="edit"
                record={eps}
                entityLabel="EPS"
                storeUrl={EpsController.store.url()}
                updateUrl={(id) => EpsController.update.url(id)}
            />
        </AppLayout>
    );
}
