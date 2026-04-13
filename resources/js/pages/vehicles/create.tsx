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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Vehículos', href: vehicles.index().url },
    { title: 'Crear', href: vehicles.create().url },
];

export default function VehiclesCreate({
    municipalities,
    thirdParties,
}: {
    municipalities: MunicipalityOption[];
    thirdParties: ThirdPartyOption[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        internal_code: '',
        plate: '',
        mobile_number: '',
        brand: '',
        line: '',
        model_year: '',
        type: '',
        engine_number: '',
        chassis_number: '',
        capacity: '',
        municipality_id: '',
        is_third_party: false,
        third_party_id: '',
        soat_due_date: '',
        rtm_due_date: '',
        operation_card_due_date: '',
        status: 'active',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(VehicleController.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Crear Vehículo" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Crear Vehículo</CardTitle>
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
                                    Guardar
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
