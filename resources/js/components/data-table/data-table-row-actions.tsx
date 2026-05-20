import { Link } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface DataTableRowActionsProps {
    editUrl?: string;
    /**
     * Opens an in-page edit dialog instead of navigating. When both
     * `onEdit` and `editUrl` are provided, `onEdit` wins.
     */
    onEdit?: () => void;
    onDelete?: () => void;
    /**
     * Optional override for the delete confirmation dialog title.
     * Defaults to "¿Eliminar registro?".
     */
    deleteConfirmTitle?: string;
    /**
     * Optional override for the delete confirmation dialog body text.
     * Defaults to an irreversible-action warning in Spanish.
     */
    deleteConfirmDescription?: string;
}

export function DataTableRowActions({
    editUrl,
    onEdit,
    onDelete,
    deleteConfirmTitle = '¿Eliminar registro?',
    deleteConfirmDescription = 'Esta acción no se puede deshacer. El registro será eliminado permanentemente.',
}: DataTableRowActionsProps) {
    const [confirmOpen, setConfirmOpen] = useState(false);

    function handleConfirm() {
        if (onDelete) {
            onDelete();
        }
        setConfirmOpen(false);
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" className="size-8 p-0">
                        <span className="sr-only">Abrir menú</span>
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {onEdit ? (
                        <DropdownMenuItem
                            onSelect={(event) => {
                                // Prevent the dropdown from closing-then-
                                // opening the dialog in the same tick,
                                // which Radix can race on.
                                event.preventDefault();
                                onEdit();
                            }}
                        >
                            <Pencil className="mr-2 size-4" />
                            Editar
                        </DropdownMenuItem>
                    ) : (
                        editUrl && (
                            <DropdownMenuItem asChild>
                                <Link href={editUrl}>
                                    <Pencil className="mr-2 size-4" />
                                    Editar
                                </Link>
                            </DropdownMenuItem>
                        )
                    )}
                    {onDelete && (
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={(event) => {
                                // Prevent the dropdown from closing-then-
                                // opening the dialog in the same tick,
                                // which Radix can race on.
                                event.preventDefault();
                                setConfirmOpen(true);
                            }}
                        >
                            <Trash2 className="mr-2 size-4" />
                            Eliminar
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>

            {onDelete && (
                <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                {deleteConfirmTitle}
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {deleteConfirmDescription}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancelar</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={handleConfirm}
                                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            >
                                Eliminar
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            )}
        </>
    );
}
