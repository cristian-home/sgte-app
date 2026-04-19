import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    VehicleLocationForm,
    type VehicleLocationFormData,
} from '@/components/vehicle-locations/vehicle-location-form';
import { type VehicleOption } from '@/components/vehicles/vehicle-combobox';
import AppLayout from '@/layouts/app-layout';

import type { BreadcrumbItem } from '@/types';

interface Location {
    id: number;
    vehicle_id: number;
    service_id: number | null;
    recorded_at: string;
    latitude: string;
    longitude: string;
    accuracy: string | null;
    is_manual: boolean;
}

export default function VehicleLocationsEdit({
    vehicleLocation,
    vehicles,
}: {
    vehicleLocation: Location;
    vehicles: VehicleOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'GPS', href: '#' },
        { title: 'Ubicaciones', href: '/vehicle-locations' },
        {
            title: `#${vehicleLocation.id}`,
            href: `/vehicle-locations/${vehicleLocation.id}`,
        },
        {
            title: 'Editar',
            href: `/vehicle-locations/${vehicleLocation.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } =
        useForm<VehicleLocationFormData>({
            vehicle_id: vehicleLocation.vehicle_id,
            service_id: vehicleLocation.service_id,
            recorded_at: vehicleLocation.recorded_at
                .replace('Z', '')
                .slice(0, 16),
            latitude: vehicleLocation.latitude,
            longitude: vehicleLocation.longitude,
            accuracy: vehicleLocation.accuracy ?? '',
            is_manual: vehicleLocation.is_manual,
        });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(`/vehicle-locations/${vehicleLocation.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ubicación #${vehicleLocation.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Editar ubicación</CardTitle>
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
                                    Guardar cambios
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
