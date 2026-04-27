import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Upload } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { DataImportType } from '@/enums/DataImportType';
import type { BreadcrumbItem } from '@/types';

interface TypeOption {
    value: DataImportType;
    label: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Administración', href: '#' },
    { title: 'Importaciones', href: '/admin/imports' },
    { title: 'Nueva carga', href: '/admin/imports/create' },
];

const MAX_BYTES = 20 * 1024 * 1024;

export default function ImportsCreate({ types }: { types: TypeOption[] }) {
    const [type, setType] = useState<string>('');
    const [file, setFile] = useState<File | null>(null);
    const [clientError, setClientError] = useState<string | null>(null);

    function handleFileChange(e: React.ChangeEvent<HTMLInputElement>) {
        const f = e.target.files?.[0] ?? null;
        if (!f) {
            setFile(null);
            setClientError(null);
            return;
        }

        if (f.size > MAX_BYTES) {
            setFile(null);
            setClientError('El archivo excede el límite de 20 MB.');
            return;
        }

        const ext = f.name.split('.').pop()?.toLowerCase() ?? '';
        if (!['csv', 'txt', 'xlsx'].includes(ext)) {
            setFile(null);
            setClientError('Solo se aceptan archivos CSV o XLSX.');
            return;
        }

        setFile(f);
        setClientError(null);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva carga" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Nueva carga</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action="/admin/imports"
                            method="post"
                            encType="multipart/form-data"
                            options={{ preserveScroll: true }}
                            className="flex flex-col gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="type">
                                            Tipo de carga *
                                        </Label>
                                        <Select
                                            value={type}
                                            onValueChange={setType}
                                            name="type"
                                        >
                                            <SelectTrigger id="type">
                                                <SelectValue placeholder="Selecciona…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {types.map((t) => (
                                                    <SelectItem
                                                        key={t.value}
                                                        value={t.value}
                                                    >
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <input
                                            type="hidden"
                                            name="type"
                                            value={type}
                                        />
                                        {errors.type && (
                                            <p className="text-sm text-destructive">
                                                {errors.type}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-2">
                                        <Label htmlFor="csv">Archivo *</Label>
                                        <Input
                                            id="csv"
                                            name="csv"
                                            type="file"
                                            accept=".csv,.txt,.xlsx"
                                            onChange={handleFileChange}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Acepta CSV o XLSX. Máximo 20 MB.
                                        </p>
                                        {clientError && (
                                            <p className="text-sm text-destructive">
                                                {clientError}
                                            </p>
                                        )}
                                        {errors.csv && (
                                            <p className="text-sm text-destructive">
                                                {errors.csv}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex items-start gap-2">
                                        <Checkbox
                                            id="dry_run"
                                            name="dry_run"
                                            value="1"
                                        />
                                        <div className="flex flex-col">
                                            <Label
                                                htmlFor="dry_run"
                                                className="cursor-pointer"
                                            >
                                                Solo validar (no escribir
                                                cambios)
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Recomendado en la primera prueba
                                                para validar el formato sin
                                                tocar la base de datos.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex items-start gap-2">
                                        <Checkbox
                                            id="update_existing"
                                            name="update_existing"
                                            value="1"
                                        />
                                        <div className="flex flex-col">
                                            <Label
                                                htmlFor="update_existing"
                                                className="cursor-pointer"
                                            >
                                                Actualizar registros existentes
                                            </Label>
                                            <p className="text-xs text-muted-foreground">
                                                Si está activo, las filas con
                                                clave existente se actualizan.
                                                Si no, se ignoran.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex justify-end gap-2 pt-2">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            asChild
                                        >
                                            <Link href="/admin/imports">
                                                <ArrowLeft className="mr-1 size-4" />
                                                Cancelar
                                            </Link>
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={
                                                processing ||
                                                !type ||
                                                !file ||
                                                !!clientError
                                            }
                                        >
                                            <Upload className="mr-1 size-4" />
                                            Subir y procesar
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
