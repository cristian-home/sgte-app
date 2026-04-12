import { Head, Link, useForm } from '@inertiajs/react';
import ServiceIncidentController from '@/actions/App/Http/Controllers/ServiceIncidentController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import services from '@/routes/services';
import type { BreadcrumbItem, IncidentType, Service } from '@/types';

export default function ServiceIncidentsCreate({
    incidentTypes,
    service,
}: {
    incidentTypes: IncidentType[];
    service?: Service | null;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Novedades',
            href: ServiceIncidentController.index.url(),
        },
        { title: 'Registrar', href: '#' },
    ];

    const { data, setData, post, processing, errors } = useForm({
        service_id: service?.id ? String(service.id) : '',
        incident_type_id: '',
        description: '',
        affects_billing: false,
        additional_value: '',
    });

    function handleIncidentTypeChange(value: string) {
        setData((prev) => {
            const type = incidentTypes.find((t) => String(t.id) === value);
            return {
                ...prev,
                incident_type_id: value,
                affects_billing: type?.affects_billing_default ?? false,
            };
        });
    }

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(ServiceIncidentController.store().url);
    }

    const serviceLabel = service
        ? `${service.vehicle?.plate ?? ''} - ${service.service_date}`
        : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Registrar Novedad" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-3">
                            <CardTitle>Registrar Novedad</CardTitle>
                            {serviceLabel && (
                                <Badge variant="outline">{serviceLabel}</Badge>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            {service ? (
                                <input
                                    type="hidden"
                                    name="service_id"
                                    value={data.service_id}
                                />
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="service_id">
                                        ID del Servicio
                                    </Label>
                                    <Input
                                        id="service_id"
                                        type="number"
                                        value={data.service_id}
                                        onChange={(e) =>
                                            setData(
                                                'service_id',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError message={errors.service_id} />
                                </div>
                            )}

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="incident_type_id">
                                        Tipo de Novedad
                                    </Label>
                                    <Select
                                        value={data.incident_type_id}
                                        onValueChange={handleIncidentTypeChange}
                                    >
                                        <SelectTrigger id="incident_type_id">
                                            <SelectValue placeholder="Seleccionar tipo..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {incidentTypes.map((type) => (
                                                <SelectItem
                                                    key={type.id}
                                                    value={String(type.id)}
                                                >
                                                    {type.code} - {type.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.incident_type_id}
                                    />
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center gap-3">
                                        <Switch
                                            id="affects_billing"
                                            checked={data.affects_billing}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'affects_billing',
                                                    checked,
                                                )
                                            }
                                        />
                                        <Label htmlFor="affects_billing">
                                            Afecta facturación
                                        </Label>
                                    </div>
                                    <InputError
                                        message={errors.affects_billing}
                                    />

                                    {data.affects_billing && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="additional_value">
                                                Valor adicional / descuento
                                            </Label>
                                            <Input
                                                id="additional_value"
                                                type="number"
                                                step="0.01"
                                                value={data.additional_value}
                                                onChange={(e) =>
                                                    setData(
                                                        'additional_value',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Valor positivo o negativo"
                                            />
                                            <InputError
                                                message={
                                                    errors.additional_value
                                                }
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">Descripción</Label>
                                <textarea
                                    id="description"
                                    className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                    placeholder="Describa la novedad en detalle..."
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Registrar Novedad
                                </Button>
                                <Link
                                    href={
                                        service
                                            ? services.show(service.id).url
                                            : ServiceIncidentController.index.url()
                                    }
                                >
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
