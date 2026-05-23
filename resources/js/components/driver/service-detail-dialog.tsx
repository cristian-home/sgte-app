import { Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Ban,
    Clock,
    Flag,
    MapPin,
    Play,
    Truck,
} from 'lucide-react';
import { useState } from 'react';
import OpenInMapsButton from '@/components/services/open-in-maps-button';
import RouteStaticMap from '@/components/services/route-static-map';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { ServiceStatusLabel } from '@/enums/ServiceStatus';
import { formatEventTime } from '@/lib/datetime';
import type { Service } from '@/types';

function formatServiceTime(at: string | null, timezone: string): string {
    return formatEventTime(at, timezone) || '—';
}

function municipalityName(municipality?: { name: string } | null): string {
    return municipality?.name ?? '—';
}

function clientName(service: Service): string {
    const tp = service.contract?.third_party;
    if (!tp) {
        return 'Sin contrato';
    }
    return (
        tp.company_name ||
        `${tp.first_name ?? ''} ${tp.first_lastname ?? ''}`.trim() ||
        'Sin contrato'
    );
}

interface ServiceDetailDialogProps {
    service: Service | null;
    isToday: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirmStart: (serviceId: number) => void;
    onConfirmEnd: (serviceId: number) => void;
}

export function ServiceDetailDialog({
    service,
    isToday,
    open,
    onOpenChange,
    onConfirmStart,
    onConfirmEnd,
}: ServiceDetailDialogProps) {
    const [declineOpen, setDeclineOpen] = useState(false);
    const declineForm = useForm({ reason_text: '' });

    if (!service) {
        return null;
    }

    const hasStarted = !!service.actual_start_at;
    const hasEnded = !!service.actual_end_at;
    const isDeclined = !!service.driver_declined_at;
    const isClosed = service.service_status === 'closed';
    const incidentCount = service.service_incidents_count ?? 0;

    function submitDecline(e: React.FormEvent) {
        e.preventDefault();
        if (!service) {
            return;
        }
        declineForm.post(`/driver/services/${service.id}/decline`, {
            onSuccess: () => {
                setDeclineOpen(false);
                declineForm.reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <div className="flex items-center justify-between gap-3">
                            <DialogTitle className="flex items-center gap-2 text-lg">
                                <Truck className="size-5" />
                                {service.vehicle?.plate ?? '—'}
                            </DialogTitle>
                            <div className="flex items-center gap-2">
                                {incidentCount > 0 && (
                                    <Badge variant="destructive">
                                        {incidentCount} novedad
                                        {incidentCount > 1 ? 'es' : ''}
                                    </Badge>
                                )}
                                <Badge
                                    variant={
                                        isClosed ? 'default' : 'secondary'
                                    }
                                >
                                    {ServiceStatusLabel[
                                        service.service_status as keyof typeof ServiceStatusLabel
                                    ] ?? service.service_status}
                                </Badge>
                            </div>
                        </div>
                        <DialogDescription>
                            {clientName(service)}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-3">
                        <div className="flex items-start gap-2 text-sm">
                            <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                            <p>
                                {municipalityName(service.origin_municipality)}{' '}
                                &rarr;{' '}
                                {municipalityName(
                                    service.destination_municipality,
                                )}
                            </p>
                        </div>

                        <RouteStaticMap
                            origin={service.origin_coordinates ?? null}
                            destination={service.destination_coordinates ?? null}
                            geometry={service.route_geometry ?? null}
                            width={560}
                            height={260}
                        />

                        <div className="flex items-center gap-2 text-sm">
                            <Clock className="size-4 shrink-0 text-muted-foreground" />
                            <span>
                                Planificado:{' '}
                                {formatServiceTime(
                                    service.planned_start_at,
                                    service.timezone,
                                )}{' '}
                                ({service.planned_duration} min)
                            </span>
                        </div>

                        {hasStarted && (
                            <div className="flex items-center gap-2 text-sm">
                                <Play className="size-4 shrink-0 text-green-600" />
                                <span>
                                    Inicio real:{' '}
                                    {formatServiceTime(
                                        service.actual_start_at,
                                        service.timezone,
                                    )}
                                </span>
                                {hasEnded && (
                                    <span className="ml-2">
                                        | Fin:{' '}
                                        {formatServiceTime(
                                            service.actual_end_at,
                                            service.timezone,
                                        )}
                                    </span>
                                )}
                            </div>
                        )}

                        {isDeclined && (
                            <div className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm text-destructive">
                                <p className="font-medium">
                                    Servicio declinado &mdash; pendiente de
                                    reasignaci&oacute;n
                                </p>
                                {service.driver_decline_reason && (
                                    <p className="mt-1 text-xs opacity-80">
                                        {service.driver_decline_reason}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    <DialogFooter className="flex-wrap gap-2 sm:justify-start">
                        {isToday && !hasStarted && !isDeclined && (
                            <Button
                                onClick={() => onConfirmStart(service.id)}
                            >
                                <Play className="mr-1 size-4" />
                                Confirmar Inicio
                            </Button>
                        )}
                        {isToday && hasStarted && !hasEnded && (
                            <Button
                                variant="secondary"
                                onClick={() => onConfirmEnd(service.id)}
                            >
                                <Flag className="mr-1 size-4" />
                                Confirmar Fin
                            </Button>
                        )}
                        {isToday && hasStarted && hasEnded && (
                            <span className="self-center text-sm text-muted-foreground">
                                Servicio completado
                            </span>
                        )}
                        {isToday && !hasStarted && !isDeclined && (
                            <Button
                                variant="destructive"
                                onClick={() => setDeclineOpen(true)}
                            >
                                <Ban className="mr-1 size-4" />
                                Declinar servicio
                            </Button>
                        )}

                        <OpenInMapsButton
                            origin={service.origin_coordinates ?? null}
                            destination={service.destination_coordinates ?? null}
                        />

                        {!isClosed && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={`/service-incidents/create?service_id=${service.id}`}
                                >
                                    <AlertCircle className="mr-1 size-4" />
                                    Registrar Novedad
                                </Link>
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={declineOpen}
                onOpenChange={(o) => {
                    setDeclineOpen(o);
                    if (!o) {
                        declineForm.reset();
                        declineForm.clearErrors();
                    }
                }}
            >
                <DialogContent>
                    <form onSubmit={submitDecline} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>Declinar servicio</DialogTitle>
                            <DialogDescription>
                                Explique por qu&eacute; no puede ejecutar el
                                servicio. Esta acci&oacute;n notifica a
                                operaciones y marca el servicio como pendiente
                                de reasignaci&oacute;n.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="space-y-2">
                            <Label htmlFor="reason_text">
                                Motivo del rechazo
                                <span className="text-destructive"> *</span>
                            </Label>
                            <textarea
                                id="reason_text"
                                className="flex min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                value={declineForm.data.reason_text}
                                placeholder="Ej: Incapacidad médica, vehículo con falla mecánica, etc."
                                minLength={10}
                                maxLength={1000}
                                onChange={(e) =>
                                    declineForm.setData(
                                        'reason_text',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                            {declineForm.errors.reason_text && (
                                <p className="text-sm text-destructive">
                                    {declineForm.errors.reason_text}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDeclineOpen(false)}
                            >
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={declineForm.processing}
                            >
                                Confirmar rechazo
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
