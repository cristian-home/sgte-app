import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';

import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'FUEC', href: '/fuecs' },
    { title: 'Generar', href: '/fuecs/create' },
];

interface Candidate {
    id: number;
    service_date: string | null;
    vehicle?: { id: number; plate: string } | null;
    driver?: {
        id: number;
        first_name: string | null;
        first_lastname: string | null;
    } | null;
    contract?: { id: number; contract_number: string } | null;
}

function driverName(d: Candidate['driver']): string {
    if (!d) return '—';
    const name = [d.first_name, d.first_lastname]
        .filter(Boolean)
        .join(' ')
        .trim();
    return name !== '' ? name : '—';
}

export default function FuecsCreate() {
    const [candidates, setCandidates] = useState<Candidate[]>([]);
    const [search, setSearch] = useState('');
    const [loading, setLoading] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{
        service_id: number | '';
    }>({
        service_id: '',
    });

    useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoading(true);
            fetch(
                `/fuecs/candidate-services${search ? `?search=${encodeURIComponent(search)}` : ''}`,
                {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                },
            )
                .then((res) => (res.ok ? res.json() : []))
                .then((payload: Candidate[]) => setCandidates(payload))
                .catch(() => {})
                .finally(() => setLoading(false));
        }, 250);
        return () => {
            controller.abort();
            clearTimeout(timer);
        };
    }, [search]);

    const preGenErrors = Object.entries(errors).filter(([key]) =>
        key.startsWith('fuec_pre_generation.'),
    );

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post('/fuecs');
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Generar FUEC" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Generar FUEC</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Alert>
                            <AlertTitle>Validaciones previas</AlertTitle>
                            <AlertDescription>
                                Se verificará que el contrato esté vigente, los
                                documentos del vehículo (SOAT, RTM, Tarjeta de
                                Operación) no estén vencidos, la licencia del
                                conductor sea válida y compatible con el
                                vehículo, y exista un rango MinTransporte activo
                                con consecutivos disponibles.
                            </AlertDescription>
                        </Alert>

                        {preGenErrors.length > 0 && (
                            <Alert variant="destructive">
                                <AlertTitle>
                                    No se puede generar el FUEC
                                </AlertTitle>
                                <AlertDescription>
                                    <ul className="mt-2 list-disc pl-5 text-sm">
                                        {preGenErrors.map(([key, msg]) => (
                                            <li key={key}>
                                                {Array.isArray(msg)
                                                    ? msg.join(' ')
                                                    : String(msg)}
                                            </li>
                                        ))}
                                    </ul>
                                </AlertDescription>
                            </Alert>
                        )}

                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="fuec-candidate-search">
                                    Buscar servicio (placa, contrato, conductor)
                                </Label>
                                <Input
                                    id="fuec-candidate-search"
                                    value={search}
                                    placeholder="Buscar..."
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>

                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-10"></TableHead>
                                            <TableHead>Fecha</TableHead>
                                            <TableHead>Vehículo</TableHead>
                                            <TableHead>Conductor</TableHead>
                                            <TableHead>Contrato</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {loading && (
                                            <TableRow>
                                                <TableCell
                                                    colSpan={5}
                                                    className="text-center text-muted-foreground"
                                                >
                                                    Buscando…
                                                </TableCell>
                                            </TableRow>
                                        )}
                                        {!loading &&
                                            candidates.length === 0 && (
                                                <TableRow>
                                                    <TableCell
                                                        colSpan={5}
                                                        className="text-center text-muted-foreground"
                                                    >
                                                        Sin servicios
                                                        candidatos.
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        {candidates.map((candidate) => (
                                            <TableRow key={candidate.id}>
                                                <TableCell>
                                                    <input
                                                        type="radio"
                                                        name="service_id"
                                                        value={candidate.id}
                                                        checked={
                                                            data.service_id ===
                                                            candidate.id
                                                        }
                                                        onChange={() =>
                                                            setData(
                                                                'service_id',
                                                                candidate.id,
                                                            )
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    {candidate.service_date ??
                                                        '—'}
                                                </TableCell>
                                                <TableCell className="font-mono">
                                                    {candidate.vehicle?.plate ??
                                                        '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {driverName(
                                                        candidate.driver,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {candidate.contract
                                                        ?.contract_number ??
                                                        '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            <Button
                                type="submit"
                                disabled={processing || data.service_id === ''}
                            >
                                Generar FUEC
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
