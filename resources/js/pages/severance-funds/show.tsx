import { Head } from '@inertiajs/react';
import { useState } from 'react';
import SeveranceFundController from '@/actions/App/Http/Controllers/SeveranceFundController';
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
import type { BreadcrumbItem, SeveranceFund } from '@/types';

export default function SeveranceFundsShow({
    severanceFund,
}: {
    severanceFund: SeveranceFund;
}) {
    const [editOpen, setEditOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Fondos de Cesantías',
            href: SeveranceFundController.index.url(),
        },
        {
            title: severanceFund.name,
            href: SeveranceFundController.show.url(severanceFund.id),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={severanceFund.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{severanceFund.name}</CardTitle>
                                <CardDescription>
                                    Código: {severanceFund.code}
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
                record={severanceFund}
                entityLabel="Fondo de Cesantías"
                storeUrl={SeveranceFundController.store.url()}
                updateUrl={(id) => SeveranceFundController.update.url(id)}
            />
        </AppLayout>
    );
}
