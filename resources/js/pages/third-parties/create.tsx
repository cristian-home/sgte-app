import { Head, Link, useForm } from '@inertiajs/react';
import ThirdPartyController from '@/actions/App/Http/Controllers/ThirdPartyController';
import InputError from '@/components/input-error';
import MunicipalityCombobox, {
    type MunicipalityOption,
} from '@/components/municipality-combobox';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import thirdParties from '@/routes/third-parties';
import { type BreadcrumbItem } from '@/types';

interface DocumentType {
    id: number;
    code: string;
    name: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Terceros', href: thirdParties.index().url },
    { title: 'Crear', href: thirdParties.create().url },
];

export default function ThirdPartiesCreate({
    documentTypes,
    municipalities,
}: {
    documentTypes: DocumentType[];
    municipalities: MunicipalityOption[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        document_type_id: '',
        identification_number: '',
        is_natural_person: true,
        first_name: '',
        second_name: '',
        first_lastname: '',
        second_lastname: '',
        company_name: '',
        trade_name: '',
        municipality_id: '',
        address: '',
        phone: '',
        email: '',
        is_customer: true,
        is_provider: false,
        active: true,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(ThirdPartyController.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Crear Tercero" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Crear Tercero</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="document_type_id">
                                        Tipo de Documento
                                    </Label>
                                    <Select
                                        value={data.document_type_id}
                                        onValueChange={(value) =>
                                            setData('document_type_id', value)
                                        }
                                    >
                                        <SelectTrigger id="document_type_id">
                                            <SelectValue placeholder="Seleccionar..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {documentTypes.map((dt) => (
                                                <SelectItem
                                                    key={dt.id}
                                                    value={String(dt.id)}
                                                >
                                                    {dt.code} - {dt.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        message={errors.document_type_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="identification_number">
                                        Numero de Identificacion
                                    </Label>
                                    <Input
                                        id="identification_number"
                                        value={data.identification_number}
                                        onChange={(e) =>
                                            setData(
                                                'identification_number',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={errors.identification_number}
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <Switch
                                    id="is_natural_person"
                                    checked={data.is_natural_person}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'is_natural_person',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_natural_person">
                                    {data.is_natural_person
                                        ? 'Persona Natural'
                                        : 'Persona Juridica'}
                                </Label>
                                <InputError
                                    message={errors.is_natural_person}
                                />
                            </div>

                            {data.is_natural_person ? (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="first_name">
                                            Primer Nombre
                                        </Label>
                                        <Input
                                            id="first_name"
                                            value={data.first_name}
                                            onChange={(e) =>
                                                setData(
                                                    'first_name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.first_name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="second_name">
                                            Segundo Nombre
                                        </Label>
                                        <Input
                                            id="second_name"
                                            value={data.second_name}
                                            onChange={(e) =>
                                                setData(
                                                    'second_name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.second_name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="first_lastname">
                                            Primer Apellido
                                        </Label>
                                        <Input
                                            id="first_lastname"
                                            value={data.first_lastname}
                                            onChange={(e) =>
                                                setData(
                                                    'first_lastname',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.first_lastname}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="second_lastname">
                                            Segundo Apellido
                                        </Label>
                                        <Input
                                            id="second_lastname"
                                            value={data.second_lastname}
                                            onChange={(e) =>
                                                setData(
                                                    'second_lastname',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.second_lastname}
                                        />
                                    </div>
                                </div>
                            ) : (
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="company_name">
                                            Razon Social
                                        </Label>
                                        <Input
                                            id="company_name"
                                            value={data.company_name}
                                            onChange={(e) =>
                                                setData(
                                                    'company_name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.company_name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="trade_name">
                                            Nombre Comercial
                                        </Label>
                                        <Input
                                            id="trade_name"
                                            value={data.trade_name}
                                            onChange={(e) =>
                                                setData(
                                                    'trade_name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={errors.trade_name}
                                        />
                                    </div>
                                </div>
                            )}

                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="municipality_id">
                                        Municipio
                                    </Label>
                                    <MunicipalityCombobox
                                        id="municipality_id"
                                        municipalities={municipalities}
                                        value={data.municipality_id}
                                        onChange={(val) =>
                                            setData('municipality_id', val)
                                        }
                                        invalid={!!errors.municipality_id}
                                    />
                                    <InputError
                                        message={errors.municipality_id}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="address">Direccion</Label>
                                    <Input
                                        id="address"
                                        value={data.address}
                                        onChange={(e) =>
                                            setData('address', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.address} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Telefono</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) =>
                                            setData('phone', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">
                                        Correo Electronico
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>

                            <div className="flex flex-wrap gap-6">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="is_customer"
                                        checked={data.is_customer}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'is_customer',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="is_customer">Cliente</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="is_provider"
                                        checked={data.is_provider}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'is_provider',
                                                checked === true,
                                            )
                                        }
                                    />
                                    <Label htmlFor="is_provider">
                                        Proveedor
                                    </Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="active"
                                        checked={data.active}
                                        onCheckedChange={(checked) =>
                                            setData('active', checked === true)
                                        }
                                    />
                                    <Label htmlFor="active">Activo</Label>
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                                <Link href={thirdParties.index().url}>
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
