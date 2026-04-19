import { Head, Link, useForm } from '@inertiajs/react';
import IncidentTypeController from '@/actions/App/Http/Controllers/IncidentTypeController';
import InputError from '@/components/input-error';
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
import {
    IncidentSeverity,
    IncidentSeverityLabel,
} from '@/enums/IncidentSeverity';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, IncidentType } from '@/types';

export default function IncidentTypesEdit({
    incidentType,
}: {
    incidentType: IncidentType;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Tipos de Novedad',
            href: IncidentTypeController.index.url(),
        },
        {
            title: incidentType.name,
            href: IncidentTypeController.edit.url(incidentType.id),
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        code: incidentType.code,
        name: incidentType.name,
        severity: incidentType.severity,
        affects_billing_default: incidentType.affects_billing_default,
        description: incidentType.description ?? '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(IncidentTypeController.update.url(incidentType.id));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${incidentType.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Editar Tipo de Novedad</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="code">
                                        Código
                                        <span className="text-destructive">
                                            {' *'}
                                        </span>
                                    </Label>
                                    <Input
                                        id="code"
                                        value={data.code}
                                        onChange={(e) =>
                                            setData('code', e.target.value)
                                        }
                                        maxLength={10}
                                    />
                                    <InputError message={errors.code} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">
                                        Nombre
                                        <span className="text-destructive">
                                            {' *'}
                                        </span>
                                    </Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) =>
                                            setData('name', e.target.value)
                                        }
                                        maxLength={100}
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="severity">
                                        Severidad
                                        <span className="text-destructive">
                                            {' *'}
                                        </span>
                                    </Label>
                                    <Select
                                        value={data.severity}
                                        onValueChange={(value) =>
                                            setData('severity', value)
                                        }
                                    >
                                        <SelectTrigger id="severity">
                                            <SelectValue placeholder="Seleccionar severidad..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {Object.values(
                                                IncidentSeverity,
                                            ).map((value) => (
                                                <SelectItem
                                                    key={value}
                                                    value={value}
                                                >
                                                    {
                                                        IncidentSeverityLabel[
                                                            value
                                                        ]
                                                    }
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.severity} />
                                </div>

                                <div className="flex items-center gap-3 self-end">
                                    <Switch
                                        id="affects_billing_default"
                                        checked={data.affects_billing_default}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'affects_billing_default',
                                                checked,
                                            )
                                        }
                                    />
                                    <Label htmlFor="affects_billing_default">
                                        Afecta facturación por defecto
                                    </Label>
                                    <InputError
                                        message={errors.affects_billing_default}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="description">
                                    Descripción (opcional)
                                </Label>
                                <textarea
                                    id="description"
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                                <Link href={IncidentTypeController.index.url()}>
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
