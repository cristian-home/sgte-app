import { Head, Link, useForm } from '@inertiajs/react';
import SeveranceFundController from '@/actions/App/Http/Controllers/SeveranceFundController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, SeveranceFund } from '@/types';

export default function SeveranceFundsEdit({
    severanceFund,
}: {
    severanceFund: SeveranceFund;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Fondos de Cesantías',
            href: SeveranceFundController.index.url(),
        },
        {
            title: severanceFund.name,
            href: SeveranceFundController.edit.url(severanceFund.id),
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        code: severanceFund.code,
        name: severanceFund.name,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(SeveranceFundController.update.url(severanceFund.id));
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${severanceFund.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Editar Fondo de Cesantías</CardTitle>
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
                            </div>

                            <div className="flex items-center gap-4">
                                <Button type="submit" disabled={processing}>
                                    Actualizar
                                </Button>
                                <Link
                                    href={SeveranceFundController.index.url()}
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
