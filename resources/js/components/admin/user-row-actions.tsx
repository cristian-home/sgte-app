import {
    Check,
    KeyRound,
    LogOut,
    MoreHorizontal,
    Pencil,
    Trash2,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Props {
    isActive: boolean;
    isSelf?: boolean;
    isToggling?: boolean;
    onEdit: () => void;
    onResetPassword: () => void;
    onToggleActive: () => void;
    onDelete: () => void;
}

export function UserRowActions({
    isActive,
    isSelf = false,
    isToggling = false,
    onEdit,
    onResetPassword,
    onToggleActive,
    onDelete,
}: Props) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-8">
                    <MoreHorizontal className="size-4" />
                    <span className="sr-only">Acciones</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                <DropdownMenuItem onSelect={onEdit}>
                    <Pencil className="size-4" /> Editar
                </DropdownMenuItem>
                <DropdownMenuItem onSelect={onResetPassword}>
                    <KeyRound className="size-4" /> Restablecer contraseña
                </DropdownMenuItem>
                <DropdownMenuItem
                    onSelect={onToggleActive}
                    disabled={isSelf || isToggling}
                >
                    {isActive ? (
                        <>
                            <LogOut className="size-4" /> Desactivar
                        </>
                    ) : (
                        <>
                            <Check className="size-4" /> Activar
                        </>
                    )}
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    onSelect={onDelete}
                    disabled={isSelf}
                    className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                >
                    <Trash2 className="size-4" /> Eliminar
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
