import { Head } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import VehicleCreateDialog from '@/components/vehicles/vehicle-create-dialog';
import AppLayout from '@/layouts/app-layout';
import vehicles from '@/routes/vehicles';
import { type BreadcrumbItem } from '@/types';

interface ThirdPartyOption {
    id: number;
    identification_number: string;
    first_name: string | null;
    first_lastname: string | null;
    company_name: string | null;
    is_natural_person: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Vehiculos',
        href: vehicles.index().url,
    },
];

export default function VehiclesIndex({
    vehicles: vehicleList,
    thirdParties,
}: {
    vehicles: unknown;
    thirdParties: ThirdPartyOption[];
}) {
    const [createOpen, setCreateOpen] = useState(false);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vehiculos" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Vehiculos</h1>
                    <Button onClick={() => setCreateOpen(true)}>
                        <PlusIcon className="mr-2 size-4" />
                        Crear Vehiculo
                    </Button>
                </div>
                <div className="relative min-h-screen flex-1 overflow-hidden rounded-xl border border-sidebar-border bg-background p-6 md:min-h-min dark:border-sidebar-border">
                    <pre className="mt-4 overflow-auto rounded-lg bg-muted p-4 text-sm">
                        {JSON.stringify({ vehicles: vehicleList }, null, 2)}
                    </pre>
                </div>
            </div>

            <VehicleCreateDialog
                open={createOpen}
                onOpenChange={setCreateOpen}
                thirdParties={thirdParties}
            />
        </AppLayout>
    );
}
