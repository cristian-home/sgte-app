import { Head, useForm } from '@inertiajs/react';
import {
    VehicleLocationForm,
    type VehicleLocationFormData,
} from '@/components/vehicle-locations/vehicle-location-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type VehicleOption } from '@/components/vehicles/vehicle-combobox';
import AppLayout from '@/layouts/app-layout';

import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'GPS', href: '#' },
    { title: 'Ubicaciones', href: '/vehicle-locations' },
    { title: 'Registrar', href: '/vehicle-locations/create' },
];

export default function VehicleLocationsCreate({
    vehicles,
}: {
    vehicles: VehicleOption[];
}) {
    const now = new Date();
    const localIso = new Date(now.getTime() - now.getTimezoneOffset() * 60_000)
        .toISOString()
        .slice(0, 16);

    const { data, setData, post, processing, errors } =
        useForm<VehicleLocationFormData>({
            vehicle_id: '',
            service_id: null,
            recorded_at: localIso,
            latitude: '',
            longitude: '',
            accuracy: '',
            is_manual: true,
        });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/vehicle-locations');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar Ubicación" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Registrar ubicación</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <VehicleLocationForm
                                data={data}
                                setData={setData}
                                errors={
                                    errors as Partial<
                                        Record<
                                            keyof VehicleLocationFormData,
                                            string
                                        >
                                    >
                                }
                                vehicles={vehicles}
                            />
                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
