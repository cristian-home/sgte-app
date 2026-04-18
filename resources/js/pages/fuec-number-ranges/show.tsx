import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

import type { BreadcrumbItem } from '@/types';

interface RangeDetail {
    id: number;
    resolution_number: string;
    resolution_year: number;
    range_from: number;
    range_to: number;
    active: boolean;
    notes: string | null;
    remaining: number;
    used: number;
}

export default function FuecNumberRangeShow({ range }: { range: RangeDetail }) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Administración', href: '#' },
        { title: 'Rangos FUEC', href: '/fuec-number-ranges' },
        {
            title: range.resolution_number,
            href: `/fuec-number-ranges/${range.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Rango ${range.resolution_number}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle>
                                Rango {range.resolution_number}
                            </CardTitle>
                            <div className="text-sm text-muted-foreground">
                                Año {range.resolution_year}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {range.active ? (
                                <Badge>Activo</Badge>
                            ) : (
                                <Badge variant="outline">Inactivo</Badge>
                            )}
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={`/fuec-number-ranges/${range.id}/edit`}
                                >
                                    Editar
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div className="text-muted-foreground">
                                Rango autorizado
                            </div>
                            <div className="font-mono">
                                {range.range_from}–{range.range_to}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">
                                Consecutivos usados
                            </div>
                            <div className="font-mono">{range.used}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">
                                Consecutivos disponibles
                            </div>
                            <div className="font-mono">{range.remaining}</div>
                        </div>
                        <div className="col-span-2">
                            <div className="text-muted-foreground">Notas</div>
                            <div>{range.notes ?? '—'}</div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
