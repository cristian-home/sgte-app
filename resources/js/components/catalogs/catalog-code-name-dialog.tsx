import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
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

/** Shared shape of the simple `code` + `name` catalogs. */
export interface CodeNameRecord {
    id: number;
    code: string;
    name: string;
}

interface CatalogCodeNameDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    mode: 'create' | 'edit';
    record?: CodeNameRecord | null;
    /** Singular noun shown in the dialog title — e.g. "EPS". */
    entityLabel: string;
    /** Store endpoint URL for the resource. */
    storeUrl: string;
    /** Builds the update endpoint URL for a given record id. */
    updateUrl: (id: number) => string;
}

const emptyData = { code: '', name: '' };

/**
 * Mode-aware create/edit dialog reused by the three identical `code` +
 * `name` catalogs (EPS, pension funds, severance funds). Each index page
 * supplies its own resource label and Wayfinder endpoint URLs.
 */
export default function CatalogCodeNameDialog({
    open,
    onOpenChange,
    mode,
    record,
    entityLabel,
    storeUrl,
    updateUrl,
}: CatalogCodeNameDialogProps) {
    const { data, setData, post, put, processing, errors, clearErrors } =
        useForm({ ...emptyData });

    // Re-seed the form whenever the dialog identity changes. Inertia's
    // `setData`/`clearErrors` aren't React state setters, so this effect
    // is not flagged by the React Compiler (same pattern as UserDialog).
    useEffect(() => {
        if (!open) {
            return;
        }
        if (mode === 'edit' && record) {
            setData({ code: record.code, name: record.name });
        } else {
            setData({ ...emptyData });
        }
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, mode, record?.id]);

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        // This dialog owns its <form>; stop the submit event from bubbling
        // through the React tree to an ancestor <form>. See BUG-002.
        e.stopPropagation();
        if (mode === 'create') {
            post(storeUrl, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else if (record) {
            put(updateUrl(record.id), {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <DialogHeader>
                        <DialogTitle>
                            {mode === 'create'
                                ? `Crear ${entityLabel}`
                                : `Editar ${entityLabel}`}
                        </DialogTitle>
                        <DialogDescription>
                            Complete el código y el nombre del registro.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="catalog-code">
                                Código
                                <span className="text-destructive">{' *'}</span>
                            </Label>
                            <Input
                                id="catalog-code"
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
                            <Label htmlFor="catalog-name">
                                Nombre
                                <span className="text-destructive">{' *'}</span>
                            </Label>
                            <Input
                                id="catalog-name"
                                value={data.name}
                                maxLength={100}
                                aria-invalid={!!errors.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
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
