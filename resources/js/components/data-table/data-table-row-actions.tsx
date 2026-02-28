import { Link } from '@inertiajs/react';
import { MoreHorizontal, Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface DataTableRowActionsProps {
    editUrl?: string;
    onDelete?: () => void;
}

export function DataTableRowActions({
    editUrl,
    onDelete,
}: DataTableRowActionsProps) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className="size-8 p-0">
                    <span className="sr-only">Abrir menu</span>
                    <MoreHorizontal className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {editUrl && (
                    <DropdownMenuItem asChild>
                        <Link href={editUrl}>
                            <Pencil className="mr-2 size-4" />
                            Editar
                        </Link>
                    </DropdownMenuItem>
                )}
                {onDelete && (
                    <DropdownMenuItem variant="destructive" onClick={onDelete}>
                        <Trash2 className="mr-2 size-4" />
                        Eliminar
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
