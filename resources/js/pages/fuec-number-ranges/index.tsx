import { Head, Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';

import type { BreadcrumbItem, PaginatedData } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Rangos FUEC', href: '/fuec-number-ranges' },
];

interface RangeRow {
    id: number;
    resolution_number: string;
    resolution_year: number;
    range_from: number;
    range_to: number;
    active: boolean;
    notes: string | null;
    remaining: number;
}

export default function FuecNumberRangesIndex({
    ranges,
}: {
    ranges: PaginatedData<RangeRow>;
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rangos FUEC" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Rangos MinTransporte</CardTitle>
                        <Button asChild size="sm">
                            <Link href="/fuec-number-ranges/create">
                                <PlusIcon className="mr-2 size-4" />
                                Nuevo rango
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Resolución</TableHead>
                                    <TableHead>Año</TableHead>
                                    <TableHead>Rango</TableHead>
                                    <TableHead>Disponibles</TableHead>
                                    <TableHead>Activo</TableHead>
                                    <TableHead>Notas</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {ranges.data.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="text-center text-muted-foreground"
                                        >
                                            Sin rangos registrados.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {ranges.data.map((range) => (
                                    <TableRow key={range.id}>
                                        <TableCell>
                                            <Link
                                                href={`/fuec-number-ranges/${range.id}`}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {range.resolution_number}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {range.resolution_year}
                                        </TableCell>
                                        <TableCell className="font-mono">
                                            {range.range_from}–{range.range_to}
                                        </TableCell>
                                        <TableCell className="font-mono">
                                            {range.remaining}
                                        </TableCell>
                                        <TableCell>
                                            {range.active ? (
                                                <Badge>Sí</Badge>
                                            ) : (
                                                <Badge variant="outline">
                                                    No
                                                </Badge>
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-md truncate">
                                            {range.notes ?? '—'}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
