import { Head, useForm } from '@inertiajs/react';
import {
    FuecNumberRangeForm,
    type FuecNumberRangeFormData,
} from '@/components/fuec-number-ranges/fuec-number-range-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Rangos FUEC', href: '/fuec-number-ranges' },
    { title: 'Nuevo', href: '/fuec-number-ranges/create' },
];

export default function FuecNumberRangeCreate() {
    const { data, setData, post, processing, errors } =
        useForm<FuecNumberRangeFormData>({
            resolution_number: '',
            resolution_year: new Date().getFullYear(),
            range_from: '',
            range_to: '',
            active: false,
            notes: '',
        });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/fuec-number-ranges');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo rango FUEC" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Nuevo rango MinTransporte</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <FuecNumberRangeForm
                                data={data}
                                setData={setData}
                                errors={
                                    errors as Partial<
                                        Record<
                                            keyof FuecNumberRangeFormData,
                                            string
                                        >
                                    >
                                }
                            />
                            <div className="flex justify-end gap-2">
                                <Button type="submit" disabled={processing}>
                                    Guardar
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
