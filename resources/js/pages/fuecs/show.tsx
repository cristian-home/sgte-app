import { Head, router, useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { FuecStatusPill } from '@/components/fuecs/fuec-status-pill';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';

import type { BreadcrumbItem } from '@/types';

interface FuecDetail {
    id: number;
    uuid: string;
    consecutive_number: number;
    generated_at: string | null;
    status: string;
    pdf_path: string | null;
    cancellation_reason: string | null;
    service?: {
        id: number;
        service_date: string | null;
        planned_start_local: string | null;
        planned_duration: number | null;
        vehicle?: { id: number; plate: string } | null;
        driver?: {
            id: number;
            first_name: string | null;
            first_lastname: string | null;
        } | null;
        contract?: {
            id: number;
            contract_number: string;
            third_party?: {
                id: number;
                company_name: string | null;
                first_name: string | null;
                first_lastname: string | null;
                is_natural_person: boolean;
            } | null;
        } | null;
        origin_municipality?: { id: number; name: string } | null;
        destination_municipality?: { id: number; name: string } | null;
    } | null;
    fuec_number_range?: {
        id: number;
        resolution_number: string;
        resolution_year: number;
    } | null;
}

function customerName(
    tp: NonNullable<
        NonNullable<FuecDetail['service']>['contract']
    >['third_party'],
): string {
    if (!tp) return '—';
    if (tp.is_natural_person) {
        const name = [tp.first_name, tp.first_lastname]
            .filter(Boolean)
            .join(' ')
            .trim();
        return name !== '' ? name : '—';
    }
    return tp.company_name ?? '—';
}

function driverName(d: NonNullable<FuecDetail['service']>['driver']): string {
    if (!d) return '—';
    const name = [d.first_name, d.first_lastname]
        .filter(Boolean)
        .join(' ')
        .trim();
    return name !== '' ? name : '—';
}

export default function FuecShow({
    fuec,
    verifyUrl,
}: {
    fuec: FuecDetail;
    verifyUrl: string;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'FUEC', href: '/fuecs' },
        { title: `Nº ${fuec.consecutive_number}`, href: `/fuecs/${fuec.id}` },
    ];

    const [cancelOpen, setCancelOpen] = useState(false);
    const { data, setData, processing, errors, reset } = useForm({
        reason: '',
    });

    function confirmCancel() {
        router.post(
            `/fuecs/${fuec.id}/cancel`,
            { reason: data.reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    reset('reason');
                    setCancelOpen(false);
                },
            },
        );
    }

    const isActive = fuec.status === 'active';
    const service = fuec.service;
    const contract = service?.contract;
    const range = fuec.fuec_number_range;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`FUEC Nº ${fuec.consecutive_number}`} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between gap-3">
                        <div className="space-y-1">
                            <CardTitle className="font-mono text-xl">
                                FUEC Nº {fuec.consecutive_number}
                            </CardTitle>
                            <div className="text-sm text-muted-foreground">
                                Resolución {range?.resolution_number ?? '—'} de{' '}
                                {range?.resolution_year ?? '—'}
                            </div>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <FuecStatusPill status={fuec.status} />
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={`/fuecs/${fuec.id}/pdf`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <Download className="mr-2 size-4" />
                                    Descargar PDF
                                </a>
                            </Button>
                            {isActive && (
                                <AlertDialog
                                    open={cancelOpen}
                                    onOpenChange={setCancelOpen}
                                >
                                    <AlertDialogTrigger asChild>
                                        <Button variant="destructive" size="sm">
                                            Anular
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Anular FUEC
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                La anulación quedará registrada
                                                en la auditoría. Esta acción no
                                                se puede deshacer.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <div className="space-y-2">
                                            <Label htmlFor="cancel-reason">
                                                Motivo de anulación
                                            </Label>
                                            <textarea
                                                id="cancel-reason"
                                                className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                value={data.reason}
                                                minLength={10}
                                                maxLength={500}
                                                onChange={(e) =>
                                                    setData(
                                                        'reason',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Entre 10 y 500 caracteres..."
                                            />
                                            {errors.reason && (
                                                <p className="text-sm text-destructive">
                                                    {errors.reason}
                                                </p>
                                            )}
                                        </div>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel
                                                disabled={processing}
                                            >
                                                Cancelar
                                            </AlertDialogCancel>
                                            <AlertDialogAction
                                                disabled={
                                                    processing ||
                                                    data.reason.length < 10
                                                }
                                                onClick={confirmCancel}
                                            >
                                                Confirmar anulación
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            )}
                        </div>
                    </CardHeader>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Servicio</CardTitle>
                    </CardHeader>
                    <CardContent className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div className="text-muted-foreground">
                                Contrato
                            </div>
                            <div>{contract?.contract_number ?? '—'}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Cliente</div>
                            <div>
                                {customerName(contract?.third_party ?? null)}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">
                                Vehículo
                            </div>
                            <div className="font-mono">
                                {service?.vehicle?.plate ?? '—'}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">
                                Conductor
                            </div>
                            <div>{driverName(service?.driver ?? null)}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">
                                Fecha del servicio
                            </div>
                            <div>{service?.service_date ?? '—'}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">
                                Hora planificada
                            </div>
                            <div>{service?.planned_start_local ?? '—'}</div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Origen</div>
                            <div>
                                {service?.origin_municipality?.name ?? '—'}
                            </div>
                        </div>
                        <div>
                            <div className="text-muted-foreground">Destino</div>
                            <div>
                                {service?.destination_municipality?.name ?? '—'}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {fuec.status === 'cancelled' && fuec.cancellation_reason && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Motivo de anulación
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-sm">
                                {fuec.cancellation_reason}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {fuec.pdf_path && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-sm">
                                Vista previa del PDF
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <iframe
                                src={`/fuecs/${fuec.id}/pdf`}
                                title={`FUEC ${fuec.consecutive_number}`}
                                className="h-[600px] w-full rounded-md border"
                            />
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">
                            QR de verificación
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2">
                        <div className="text-sm text-muted-foreground">
                            El QR embebido en el PDF apunta a la siguiente URL
                            pública:
                        </div>
                        <code className="block rounded-md border bg-muted/40 px-3 py-2 font-mono text-xs break-all">
                            {verifyUrl}
                        </code>
                        <Button asChild variant="outline" size="sm">
                            <a
                                href={verifyUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Abrir página de verificación
                            </a>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
