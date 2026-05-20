import { Head } from '@inertiajs/react';
import { useState } from 'react';
import PensionFundController from '@/actions/App/Http/Controllers/PensionFundController';
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
import type { BreadcrumbItem, PensionFund } from '@/types';

export default function PensionFundsShow({
    pensionFund,
}: {
    pensionFund: PensionFund;
}) {
    const [editOpen, setEditOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Fondos de Pensiones',
            href: PensionFundController.index.url(),
        },
        {
            title: pensionFund.name,
            href: PensionFundController.show.url(pensionFund.id),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={pensionFund.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{pensionFund.name}</CardTitle>
                                <CardDescription>
                                    Código: {pensionFund.code}
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
                record={pensionFund}
                entityLabel="Fondo de Pensiones"
                storeUrl={PensionFundController.store.url()}
                updateUrl={(id) => PensionFundController.update.url(id)}
            />
        </AppLayout>
    );
}
