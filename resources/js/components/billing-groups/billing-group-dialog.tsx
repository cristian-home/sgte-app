import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import BillingGroupController from '@/actions/App/Http/Controllers/BillingGroupController';
import FieldFooter from '@/components/field-footer';
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
import { Switch } from '@/components/ui/switch';
import type { BillingGroup } from '@/types';

interface BillingGroupDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    billingGroup?: BillingGroup | null;
}

const emptyData = {
    code: '',
    name: '',
    active: true,
    description: '',
};

export default function BillingGroupDialog({
    open,
    onOpenChange,
    mode,
    billingGroup,
}: BillingGroupDialogProps) {
    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm({ ...emptyData });

    useEffect(() => {
        if (!open) {
            return;
        }
        if (mode === 'edit' && billingGroup) {
            setData({
                code: billingGroup.code,
                name: billingGroup.name,
                active: billingGroup.active,
                description: billingGroup.description ?? '',
            });
        } else {
            setData({ ...emptyData });
        }
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, billingGroup?.id]);

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        e.stopPropagation();
        if (mode === 'create') {
            post(BillingGroupController.store().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else if (billingGroup) {
            put(BillingGroupController.update(billingGroup.id).url, {
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
                                ? 'Crear Grupo de Facturación'
                                : 'Editar Grupo de Facturación'}
                        </DialogTitle>
                        <DialogDescription>
                            Los grupos se asignan como tags a los servicios y
                            sirven para clasificar la facturación.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-x-4 gap-y-2 sm:grid-cols-2 sm:grid-rows-[auto_auto_auto]">
                        <div className="grid gap-2 sm:row-span-3 sm:grid-rows-subgrid">
                            <Label htmlFor="billing-group-code">
                                Código
                                <span className="text-destructive">{' *'}</span>
                            </Label>
                            <Input
                                id="billing-group-code"
                                value={data.code}
                                maxLength={50}
                                aria-invalid={!!errors.code}
                                placeholder="empresarial"
                                onChange={(e) =>
                                    setData(
                                        'code',
                                        e.target.value.toLowerCase(),
                                    )
                                }
                                className="font-mono lowercase"
                                autoCapitalize="none"
                            />
                            <FieldFooter error={errors.code}>
                                Solo minúsculas, números y guiones.
                            </FieldFooter>
                        </div>
                        <div className="grid gap-2 sm:row-span-3 sm:grid-rows-subgrid">
                            <Label htmlFor="billing-group-name">
                                Nombre
                                <span className="text-destructive">{' *'}</span>
                            </Label>
                            <Input
                                id="billing-group-name"
                                value={data.name}
                                maxLength={100}
                                aria-invalid={!!errors.name}
                                placeholder="Empresarial"
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            <FieldFooter error={errors.name} />
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Switch
                            id="billing-group-active"
                            checked={data.active}
                            onCheckedChange={(checked) =>
                                setData('active', checked)
                            }
                        />
                        <Label htmlFor="billing-group-active">
                            Activo (disponible para asignar a nuevos servicios)
                        </Label>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="billing-group-description">
                            Descripción (opcional)
                        </Label>
                        <textarea
                            id="billing-group-description"
                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.description} />
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
