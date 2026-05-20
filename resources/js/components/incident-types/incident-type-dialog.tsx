import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import IncidentTypeController from '@/actions/App/Http/Controllers/IncidentTypeController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    IncidentSeverity,
    IncidentSeverityLabel,
} from '@/enums/IncidentSeverity';
import type { IncidentType } from '@/types';

interface IncidentTypeDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    incidentType?: IncidentType | null;
}

const emptyData = {
    code: '',
    name: '',
    severity: '',
    affects_billing_default: false,
    description: '',
};

export default function IncidentTypeDialog({
    open,
    onOpenChange,
    mode,
    incidentType,
}: IncidentTypeDialogProps) {
    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm({ ...emptyData });

    // Re-seed the form whenever the dialog identity changes. Inertia's
    // `setData`/`clearErrors` aren't React state setters, so this effect
    // is not flagged by the React Compiler (same pattern as UserDialog).
    useEffect(() => {
        if (!open) {
            return;
        }
        if (mode === 'edit' && incidentType) {
            setData({
                code: incidentType.code,
                name: incidentType.name,
                severity: incidentType.severity,
                affects_billing_default: incidentType.affects_billing_default,
                description: incidentType.description ?? '',
            });
        } else {
            setData({ ...emptyData });
        }
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, incidentType?.id]);

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        if (mode === 'create') {
            post(IncidentTypeController.store().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else if (incidentType) {
            put(IncidentTypeController.update(incidentType.id).url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {mode === 'create'
                                ? 'Crear Tipo de Novedad'
                                : 'Editar Tipo de Novedad'}
                        </DialogTitle>
                        <DialogDescription>
                            Complete los campos del tipo de novedad.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="incident-type-code">
                                Código
                                <span className="text-destructive">{' *'}</span>
                            </Label>
                            <Input
                                id="incident-type-code"
                                value={data.code}
                                maxLength={10}
                                aria-invalid={!!errors.code}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                            />
                            <InputError message={errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="incident-type-name">
                                Nombre
                                <span className="text-destructive">{' *'}</span>
                            </Label>
                            <Input
                                id="incident-type-name"
                                value={data.name}
                                maxLength={100}
                                aria-invalid={!!errors.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="incident-type-severity">
                                Severidad
                                <span className="text-destructive">{' *'}</span>
                            </Label>
                            <Select
                                value={data.severity}
                                onValueChange={(value) =>
                                    setData('severity', value)
                                }
                            >
                                <SelectTrigger
                                    id="incident-type-severity"
                                    aria-invalid={!!errors.severity}
                                >
                                    <SelectValue placeholder="Seleccionar severidad..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {Object.values(IncidentSeverity).map(
                                        (value) => (
                                            <SelectItem
                                                key={value}
                                                value={value}
                                            >
                                                {IncidentSeverityLabel[value]}
                                            </SelectItem>
                                        ),
                                    )}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.severity} />
                        </div>
                        <div className="flex items-center gap-3 self-end">
                            <Switch
                                id="incident-type-billing"
                                checked={data.affects_billing_default}
                                onCheckedChange={(checked) =>
                                    setData('affects_billing_default', checked)
                                }
                            />
                            <Label htmlFor="incident-type-billing">
                                Afecta facturación por defecto
                            </Label>
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="incident-type-description">
                            Descripción (opcional)
                        </Label>
                        <textarea
                            id="incident-type-description"
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                        <InputError message={errors.description} />
                    </div>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </DialogClose>
                        <Button type="submit" disabled={processing}>
                            {mode === 'create' ? 'Guardar' : 'Actualizar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
