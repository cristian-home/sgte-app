import { Head, Link, useForm } from '@inertiajs/react';
import ServiceIncidentController from '@/actions/App/Http/Controllers/ServiceIncidentController';
import ServiceIncidentForm, {
    type IncidentTypeOption,
    type PreselectedService,
    type ServiceIncidentFormData,
} from '@/components/incidents/service-incident-form';
import { type ServiceOption } from '@/components/services/service-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import serviceIncidents from '@/routes/service-incidents';

import type { BreadcrumbItem } from '@/types';

export default function ServiceIncidentsCreate({
    incidentTypes,
    service,
    services,
}: {
    incidentTypes: IncidentTypeOption[];
    service?: PreselectedService | null;
    services?: ServiceOption[] | null;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Novedades', href: serviceIncidents.index().url },
        { title: 'Registrar', href: '#' },
    ];

    const { data, setData, post, processing, errors } =
        useForm<ServiceIncidentFormData>({
            service_id: service?.id ? String(service.id) : '',
            incident_type_id: '',
            description: '',
            affects_billing: false,
            additional_value: '',
        });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(ServiceIncidentController.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar Novedad" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Registrar Novedad</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <ServiceIncidentForm
                                data={data}
                                setData={setData}
                                errors={errors}
                                incidentTypes={incidentTypes}
                                services={services}
                                preselectedService={service ?? null}
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                                <Link href={serviceIncidents.index().url}>
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
