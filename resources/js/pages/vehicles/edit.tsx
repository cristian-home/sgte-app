import { Head, Link, useForm } from '@inertiajs/react';
import VehicleController from '@/actions/App/Http/Controllers/VehicleController';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import VehicleForm, {
    type ThirdPartyOption,
} from '@/components/vehicles/vehicle-form';
import AppLayout from '@/layouts/app-layout';
import vehicles from '@/routes/vehicles';
import { type BreadcrumbItem } from '@/types';

interface Vehicle {
    id: number;
    internal_code: string;
    plate: string;
    mobile_number: string;
    brand: string;
    line: string;
    model_year: number;
    type: string;
    engine_number: string;
    chassis_number: string;
    capacity: number;
    municipality_id: number | null;
    is_third_party: boolean;
    third_party_id: number | null;
    soat_due_date: string;
    rtm_due_date: string;
    operation_card_due_date: string;
    status: string;
}

export default function VehiclesEdit({
    vehicle,
    municipalities,
    thirdParties,
}: {
    vehicle: Vehicle;
    municipalities: MunicipalityOption[];
    thirdParties: ThirdPartyOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Vehículos', href: vehicles.index().url },
        { title: 'Editar', href: VehicleController.edit(vehicle.id).url },
    ];

    const { data, setData, put, processing, errors } = useForm({
        internal_code: vehicle.internal_code,
        plate: vehicle.plate,
        mobile_number: vehicle.mobile_number,
        brand: vehicle.brand,
        line: vehicle.line,
        model_year: String(vehicle.model_year),
        type: vehicle.type,
        engine_number: vehicle.engine_number,
        chassis_number: vehicle.chassis_number,
        capacity: String(vehicle.capacity),
        municipality_id: vehicle.municipality_id
            ? String(vehicle.municipality_id)
            : '',
        is_third_party: vehicle.is_third_party,
        third_party_id: vehicle.third_party_id
            ? String(vehicle.third_party_id)
            : '',
        soat_due_date: vehicle.soat_due_date,
        rtm_due_date: vehicle.rtm_due_date,
        operation_card_due_date: vehicle.operation_card_due_date,
        status: vehicle.status,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(VehicleController.update(vehicle.id).url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Vehículo" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Editar Vehículo</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <VehicleForm
                                data={data}
                                setData={setData}
                                errors={errors}
                                municipalities={municipalities}
                                thirdParties={thirdParties}
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Actualizar
                                </Button>
                                <Link href={vehicles.index().url}>
                                    <Button type="button" variant="outline">
                                        Cancelar
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
