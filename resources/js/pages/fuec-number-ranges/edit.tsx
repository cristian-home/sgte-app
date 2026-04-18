import { Head, useForm } from '@inertiajs/react';
import {
    FuecNumberRangeForm,
    type FuecNumberRangeFormData,
} from '@/components/fuec-number-ranges/fuec-number-range-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

import type { BreadcrumbItem } from '@/types';

interface RangeProp {
    id: number;
    resolution_number: string;
    resolution_year: number;
    range_from: number;
    range_to: number;
    active: boolean;
    notes: string | null;
}

export default function FuecNumberRangeEdit({ range }: { range: RangeProp }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Administración', href: '#' },
        { title: 'Rangos FUEC', href: '/fuec-number-ranges' },
        {
            title: range.resolution_number,
            href: `/fuec-number-ranges/${range.id}`,
        },
        { title: 'Editar', href: `/fuec-number-ranges/${range.id}/edit` },
    ];

    const { data, setData, put, processing, errors } =
        useForm<FuecNumberRangeFormData>({
            resolution_number: range.resolution_number,
            resolution_year: range.resolution_year,
            range_from: range.range_from,
            range_to: range.range_to,
            active: range.active,
            notes: range.notes ?? '',
        });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        put(`/fuec-number-ranges/${range.id}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Rango ${range.resolution_number}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>
                            Editar rango {range.resolution_number}
                        </CardTitle>
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
                                    Guardar cambios
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
