import { Head, Link } from '@inertiajs/react';
import PensionFundController from '@/actions/App/Http/Controllers/PensionFundController';
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
                            <Link
                                href={PensionFundController.edit.url(
                                    pensionFund.id,
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
