import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { destroy as usersDestroy } from '@/actions/App/Http/Controllers/UserController';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: { id: number; name: string; email: string } | null;
}

export function DeleteUserDialog({ open, onOpenChange, user }: Props) {
    const form = useForm({});

    function confirm() {
        if (!user) return;
        form.delete(usersDestroy(user.id).url, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent className="sm:max-w-[440px]">
                <AlertDialogHeader>
                    <div className="flex items-start gap-3">
                        <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                            <Trash2 className="size-5" />
                        </span>
                        <div className="flex flex-col gap-1">
                            <AlertDialogTitle>
                                ¿Eliminar usuario?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {user ? (
                                    <>
                                        Se eliminará permanentemente la cuenta
                                        de <strong>{user.name}</strong> (
                                        {user.email}
                                        ). Esta acción no se puede deshacer y el
                                        usuario perderá acceso inmediato al
                                        sistema.
                                    </>
                                ) : null}
                            </AlertDialogDescription>
                        </div>
                    </div>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={form.processing}>
                        Cancelar
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={confirm}
                        disabled={form.processing}
                        className="gap-1.5 bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                        <Trash2 className="size-4" />
                        Eliminar
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
