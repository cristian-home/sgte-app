import { Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    Ban,
    CheckCircle2,
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
                        <div className="flex items-start justify-between gap-3">
                            <div className="flex items-start gap-3">
                                <div className="flex size-12 shrink-0 items-center justify-center rounded-lg border bg-muted/40">
                                    <Truck className="size-6 text-muted-foreground" />
                                </div>
                                <div className="min-w-0">
                                    <DialogTitle className="text-xl leading-tight">
                                        {service.vehicle?.plate ?? '—'}
                                    </DialogTitle>
                                    <DialogDescription className="mt-0.5">
                                        {clientName(service)}
                                    </DialogDescription>
                                </div>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                {incidentCount > 0 && (
                                    <Badge variant="destructive">
                                        {incidentCount} novedad
                                        {incidentCount > 1 ? 'es' : ''}
                                    </Badge>
                                )}
                                <Badge
                                    variant={isClosed ? 'default' : 'secondary'}
                                    className="gap-1.5"
                                >
                                    <span
                                        className={
                                            isClosed
                                                ? 'size-1.5 rounded-full bg-current'
                                                : 'size-1.5 rounded-full bg-foreground'
                                        }
                                        aria-hidden="true"
                                    />
                                    {ServiceStatusLabel[
                                        service.service_status as keyof typeof ServiceStatusLabel
                                    ] ?? service.service_status}
                                </Badge>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="space-y-4">
                        {/* Route row — A pin · origin ─────► B pin · destination */}
                        <div className="flex items-center gap-3 text-sm">
                            <div className="flex shrink-0 items-center gap-2">
                                <MapPin className="size-4 text-muted-foreground" />
                                <span className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    A
                                </span>
                                <span className="text-sm font-medium">
                                    {municipalityName(
                                        service.origin_municipality,
                                    )}
                                </span>
                            </div>
                            <div className="relative flex-1">
                                <div className="h-px w-full bg-border" />
                                <div className="absolute top-1/2 right-0 size-0 -translate-y-1/2 border-y-[4px] border-l-[6px] border-y-transparent border-l-border" />
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <MapPin className="size-4 text-muted-foreground" />
                                <span className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    B
                                </span>
                                <span className="text-sm font-medium">
                                    {municipalityName(
                                        service.destination_municipality,
                                    )}
                                </span>
                            </div>
                        </div>

                        <RouteStaticMap
                            origin={service.origin_coordinates ?? null}
                            destination={service.destination_coordinates ?? null}
                            geometry={service.route_geometry ?? null}
                            width={560}
                            height={260}
                        />

                        {/* Métricas de Tiempo — bordered card with the planned
                            and actual horizons side by side. */}
                        <div className="rounded-lg border bg-muted/20 p-4">
                            <p className="mb-3 text-sm font-medium">
                                M&eacute;tricas de Tiempo
                            </p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="flex items-start gap-2.5">
                                    <Clock className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                    <div>
                                        <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                            Horario planificado
                                        </p>
                                        <p className="text-sm">
                                            {formatServiceTime(
                                                service.planned_start_at,
                                                service.timezone,
                                            )}{' '}
                                            ({service.planned_duration} min)
                                        </p>
                                    </div>
                                </div>
                                {hasStarted && (
                                    <div className="flex items-start gap-2.5">
                                        <CheckCircle2 className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                        <div>
                                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                                {hasEnded
                                                    ? 'Horario real'
                                                    : 'Horario de inicio real'}
                                            </p>
                                            <p className="text-sm">
                                                {formatServiceTime(
                                                    service.actual_start_at,
                                                    service.timezone,
                                                )}
                                                {hasEnded && (
                                                    <>
                                                        {' '}
                                                        &mdash;{' '}
                                                        {formatServiceTime(
                                                            service.actual_end_at,
                                                            service.timezone,
                                                        )}
                                                    </>
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

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

                    <DialogFooter className="flex-wrap gap-2 sm:justify-between">
                        <div className="flex flex-wrap gap-2">
                            <OpenInMapsButton
                                variant="outline"
                                origin={service.origin_coordinates ?? null}
                                destination={
                                    service.destination_coordinates ?? null
                                }
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

                            {isToday && !hasStarted && !isDeclined && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDeclineOpen(true)}
                                    className="text-destructive hover:text-destructive"
                                >
                                    <Ban className="mr-1 size-4" />
                                    Declinar servicio
                                </Button>
                            )}
                        </div>

                        <div className="flex flex-wrap gap-2">
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
                        </div>
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
