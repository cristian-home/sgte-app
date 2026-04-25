import { Head, Link, router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { Can } from '@/components/can';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Permission } from '@/enums/Permission';
import AppLayout from '@/layouts/app-layout';
import { formatTimestampInViewerTz } from '@/lib/datetime';

import type { BreadcrumbItem } from '@/types';

interface Location {
    id: number;
    vehicle_id: number;
    service_id: number | null;
    recorded_at: string | null;
    latitude: string;
    longitude: string;
    accuracy: string | null;
    is_manual: boolean;
    captured_by: number | null;
    vehicle?: {
        id: number;
        plate: string;
        brand: string | null;
        line: string | null;
    } | null;
    service?: { id: number; service_date: string | null } | null;
    captured_by_user?: { id: number; name: string; email: string } | null;
}

export default function VehicleLocationShow({
    vehicleLocation,
}: {
    vehicleLocation: Location;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'GPS', href: '#' },
        { title: 'Ubicaciones', href: '/vehicle-locations' },
        {
            title: `#${vehicleLocation.id}`,
            href: `/vehicle-locations/${vehicleLocation.id}`,
        },
    ];

    const recordedAt =
        formatTimestampInViewerTz(vehicleLocation.recorded_at) || '—';

    function handleDelete() {
        if (confirm('¿Eliminar esta ubicación? Esta acción es auditable.')) {
            router.delete(`/vehicle-locations/${vehicleLocation.id}`);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ubicación #${vehicleLocation.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div className="space-y-1">
                            <CardTitle>
                                {vehicleLocation.vehicle?.plate ?? 'Vehículo —'}
                            </CardTitle>
                            <div className="text-sm text-muted-foreground">
                                {recordedAt}
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            {vehicleLocation.is_manual ? (
                                <Badge variant="outline">Manual</Badge>
                            ) : (
                                <Badge>GPS</Badge>
                            )}
                            <Can
                                permission={Permission.DELETE_VEHICLE_LOCATIONS}
                            >
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={handleDelete}
                                >
                                    <Trash2 className="mr-2 size-4" />
                                    Eliminar
                                </Button>
                            </Can>
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Coordenadas</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <div className="text-muted-foreground">Latitud</div>
                            <div className="font-mono">
                                {vehicleLocation.latitude}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">
                                Longitud
                            </div>
                            <div className="font-mono">
                                {vehicleLocation.longitude}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">
                                Precisión
                            </div>
                            <div className="font-mono">
                                {vehicleLocation.accuracy
                                    ? `${vehicleLocation.accuracy} m`
                                    : '—'}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Contexto</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div className="text-muted-foreground">
                                Servicio
                            </div>
                            <div>
                                {vehicleLocation.service ? (
                                    <Link
                                        href={`/services/${vehicleLocation.service.id}`}
                                        className="text-primary hover:underline"
                                    >
                                        #{vehicleLocation.service.id}
                                    </Link>
                                ) : (
                                    '—'
                                )}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">
                                Registrado por
                            </div>
                            <div>
                                {vehicleLocation.captured_by_user?.name ??
                                    'Sistema'}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
