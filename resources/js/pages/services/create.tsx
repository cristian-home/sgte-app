import { Head, Link, useForm } from '@inertiajs/react';
import ServiceController from '@/actions/App/Http/Controllers/ServiceController';
import { type MunicipalityOption } from '@/components/municipality-combobox';
import ServiceForm, {
    type ContractOption,
    type DriverOption,
    type VehicleOption,
} from '@/components/services/service-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import services from '@/routes/services';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Servicios', href: services.index().url },
    { title: 'Crear', href: services.create().url },
];

export default function ServicesCreate({
    vehicles,
    drivers,
    contracts,
    municipalities,
}: {
    vehicles: VehicleOption[];
    drivers: DriverOption[];
    contracts: ContractOption[];
    municipalities: MunicipalityOption[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        contract_id: '',
        vehicle_id: '',
        driver_id: '',
        service_date: '',
        origin_municipality_id: '',
        origin_address: '',
        destination_municipality_id: '',
        destination_address: '',
        planned_start_time: '',
        planned_duration: '',
        actual_start_time: '',
        actual_end_time: '',
        unit_value: '',
        quantity: '1',
        billing_group: '',
        payment_method: 'credit',
        service_status: 'open',
        justification: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(ServiceController.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Crear Servicio" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Crear Servicio</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <ServiceForm
                                data={data}
                                setData={setData}
                                errors={errors}
                                vehicles={vehicles}
                                drivers={drivers}
                                contracts={contracts}
                                municipalities={municipalities}
                                mode="create"
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                                <Link href={services.index().url}>
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
