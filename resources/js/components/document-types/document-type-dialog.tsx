import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import DocumentTypeController from '@/actions/App/Http/Controllers/DocumentTypeController';
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
import { Switch } from '@/components/ui/switch';
import type { DocumentType } from '@/types';

interface DocumentTypeDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    documentType?: DocumentType | null;
}

const emptyData = {
    code: '',
    name: '',
    is_natural_person: true,
    is_legal_person: false,
};

export default function DocumentTypeDialog({
    open,
    onOpenChange,
    mode,
    documentType,
}: DocumentTypeDialogProps) {
    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm({ ...emptyData });

    // Re-seed the form whenever the dialog identity changes. Inertia's
    // `setData`/`clearErrors` aren't React state setters, so this effect
    // is not flagged by the React Compiler (same pattern as UserDialog).
    useEffect(() => {
        if (!open) {
            return;
        }
        if (mode === 'edit' && documentType) {
            setData({
                code: documentType.code,
                name: documentType.name,
                is_natural_person: documentType.is_natural_person,
                is_legal_person: documentType.is_legal_person,
            });
        } else {
            setData({ ...emptyData });
        }
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, documentType?.id]);

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        // This dialog owns its <form>; stop the submit event from bubbling
        // through the React tree to an ancestor <form>. See BUG-002.
        e.stopPropagation();
        if (mode === 'create') {
            post(DocumentTypeController.store().url, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else if (documentType) {
            put(DocumentTypeController.update(documentType.id).url, {
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
                                ? 'Crear Tipo de Documento'
                                : 'Editar Tipo de Documento'}
                        </DialogTitle>
                        <DialogDescription>
                            Complete los campos del tipo de documento.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="document-type-code">
                                Código
                                <span className="text-destructive">{' *'}</span>
                            </Label>
                            <Input
                                id="document-type-code"
                                value={data.code}
                                maxLength={10}
                                aria-invalid={!!errors.code}
                                onChange={(e) =>
                                    setData('code', e.target.value.toUpperCase())
                                }
                                className="uppercase"
                                autoCapitalize="characters"
                            />
                            <InputError message={errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="document-type-name">
                                Nombre
                                <span className="text-destructive">{' *'}</span>
                            </Label>
                            <Input
                                id="document-type-name"
                                value={data.name}
                                maxLength={100}
                                aria-invalid={!!errors.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="flex items-center gap-3">
                            <Switch
                                id="document-type-natural"
                                checked={data.is_natural_person}
                                onCheckedChange={(checked) =>
                                    setData('is_natural_person', checked)
                                }
                            />
                            <Label htmlFor="document-type-natural">
                                Persona natural
                            </Label>
                        </div>
                        <div className="flex items-center gap-3">
                            <Switch
                                id="document-type-legal"
                                checked={data.is_legal_person}
                                onCheckedChange={(checked) =>
                                    setData('is_legal_person', checked)
                                }
                            />
                            <Label htmlFor="document-type-legal">
                                Persona jurídica
                            </Label>
                        </div>
                    </div>
                    <InputError message={errors.is_natural_person} />
                    <InputError message={errors.is_legal_person} />

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
