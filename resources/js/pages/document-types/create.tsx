import { Head, Link, useForm } from '@inertiajs/react';
import DocumentTypeController from '@/actions/App/Http/Controllers/DocumentTypeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/app-layout';
import documentTypesRoutes from '@/routes/document-types';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tipos de Documento',
        href: DocumentTypeController.index.url(),
    },
    { title: 'Crear', href: documentTypesRoutes.create().url },
];

export default function DocumentTypesCreate() {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        is_natural_person: true,
        is_legal_person: false,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(DocumentTypeController.store().url);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Crear Tipo de Documento" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Crear Tipo de Documento</CardTitle>
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
                                <div className="flex items-center gap-3">
                                    <Switch
                                        id="is_natural_person"
                                        checked={data.is_natural_person}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'is_natural_person',
                                                checked,
                                            )
                                        }
                                    />
                                    <Label htmlFor="is_natural_person">
                                        Persona natural
                                    </Label>
                                    <InputError
                                        message={errors.is_natural_person}
                                    />
                                </div>
                                <div className="flex items-center gap-3">
                                    <Switch
                                        id="is_legal_person"
                                        checked={data.is_legal_person}
                                        onCheckedChange={(checked) =>
                                            setData('is_legal_person', checked)
                                        }
                                    />
                                    <Label htmlFor="is_legal_person">
                                        Persona jurídica
                                    </Label>
                                    <InputError
                                        message={errors.is_legal_person}
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                                <Link href={DocumentTypeController.index.url()}>
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
