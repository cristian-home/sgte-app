import { useForm } from '@inertiajs/react';
import { useEffect, useId } from 'react';
import DriverController from '@/actions/App/Http/Controllers/DriverController';
import FieldFooter from '@/components/field-footer';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface DriverInviteDialogProps {
    driverId: number;
    defaultEmail?: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function DriverInviteDialog({
    driverId,
    defaultEmail = '',
    open,
    onOpenChange,
}: DriverInviteDialogProps) {
    const labelId = useId();
    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm<{ account_email: string }>({
            account_email: defaultEmail,
        });

    useEffect(() => {
        if (open) {
            setData('account_email', defaultEmail);
            clearErrors();
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, defaultEmail]);

    function submit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        // This dialog owns its <form>; stop the submit event from bubbling
        // through the React tree to an ancestor <form>. See BUG-002.
        e.stopPropagation();
        post(DriverController.inviteAccount(driverId).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-[480px]">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Crear cuenta de acceso</DialogTitle>
                        <DialogDescription>
                            Se enviará un enlace al correo para que el conductor
                            configure su contraseña. El enlace expira en 60
                            minutos.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2 py-4">
                        <Label htmlFor={labelId}>Correo de acceso</Label>
                        <Input
                            id={labelId}
                            type="email"
                            value={data.account_email}
                            onChange={(e) =>
                                setData('account_email', e.target.value)
                            }
                            aria-invalid={
                                errors.account_email ? true : undefined
                            }
                            autoFocus
                            required
                        />
                        <FieldFooter error={errors.account_email} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Enviar invitación
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
