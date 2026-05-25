import { Head, Link, useForm } from '@inertiajs/react';
import { index as driverDashboard } from '@/actions/App/Http/Controllers/DriverDashboardController';
import ServiceIncidentController from '@/actions/App/Http/Controllers/ServiceIncidentController';
import ServiceIncidentForm, {
    type IncidentTypeOption,
    type PreselectedService,
    type ServiceIncidentFormData,
} from '@/components/incidents/service-incident-form';
import { type ServiceOption } from '@/components/services/service-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Permission } from '@/enums/Permission';
import { usePermissions } from '@/hooks/use-permissions';
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
    const { can } = usePermissions();
    // Drivers have CREATE_INCIDENTS but not VIEW_INCIDENTS — sending
    // them back to the global Novedades index would 403. Their natural
    // "back" target is the driver dashboard.
    const cancelHref = can(Permission.VIEW_INCIDENTS)
        ? serviceIncidents.index().url
        : driverDashboard().url;
    const breadcrumbs: BreadcrumbItem[] = can(Permission.VIEW_INCIDENTS)
        ? [
              { title: 'Novedades', href: serviceIncidents.index().url },
              { title: 'Registrar', href: '#' },
          ]
        : [
              { title: 'Mis Servicios', href: driverDashboard().url },
              { title: 'Registrar Novedad', href: '#' },
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
                                <Link href={cancelHref}>
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
