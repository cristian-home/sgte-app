import { Head, Link } from '@inertiajs/react';
import SeveranceFundController from '@/actions/App/Http/Controllers/SeveranceFundController';
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
                            <Link
                                href={SeveranceFundController.edit.url(
                                    severanceFund.id,
                                )}
                            >
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
