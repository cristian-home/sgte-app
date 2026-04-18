import { Head, Link, useForm } from '@inertiajs/react';
import ServiceIncidentController from '@/actions/App/Http/Controllers/ServiceIncidentController';
import ServiceIncidentForm, {
    type IncidentTypeOption,
    type PreselectedService,
    type ServiceIncidentFormData,
} from '@/components/incidents/service-incident-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import serviceIncidents from '@/routes/service-incidents';

import type { BreadcrumbItem } from '@/types';
import type { ServiceIncident } from '@/types/models';

type EditServiceIncident = Pick<
    ServiceIncident,
    | 'id'
    | 'service_id'
    | 'incident_type_id'
    | 'description'
    | 'affects_billing'
    | 'additional_value'
> & {
    service?: PreselectedService | null;
    incident_type?: { id: number; name: string; severity: string } | null;
};

export default function ServiceIncidentsEdit({
    serviceIncident,
    incidentTypes,
}: {
    serviceIncident: EditServiceIncident;
    incidentTypes: IncidentTypeOption[];
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Novedades', href: serviceIncidents.index().url },
        {
            title: serviceIncident.incident_type?.name ?? 'Novedad',
            href: serviceIncidents.show(serviceIncident.id).url,
        },
        { title: 'Editar', href: '#' },
    ];

    const { data, setData, put, processing, errors } =
        useForm<ServiceIncidentFormData>({
            service_id: String(serviceIncident.service_id),
            incident_type_id: String(serviceIncident.incident_type_id),
            description: serviceIncident.description ?? '',
            affects_billing: serviceIncident.affects_billing,
            additional_value:
                serviceIncident.additional_value !== null &&
                serviceIncident.additional_value !== undefined
                    ? String(serviceIncident.additional_value)
                    : '',
        });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(ServiceIncidentController.update(serviceIncident.id).url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar Novedad" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Editar Novedad</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            {/* Service is read-only on edit — the
                                service-transfer flow is out of scope.
                                The preselected summary block renders
                                instead of the ServiceCombobox. */}
                            <ServiceIncidentForm
                                data={data}
                                setData={setData}
                                errors={errors}
                                incidentTypes={incidentTypes}
                                preselectedService={
                                    serviceIncident.service ?? null
                                }
                            />

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Actualizar
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
