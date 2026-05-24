import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Column } from '@tanstack/react-table';

interface DataTableColumnHeaderProps<
    TData,
    TValue,
> extends React.ComponentProps<'div'> {
    column: Column<TData, TValue>;
    title: string;
}

export function DataTableColumnHeader<TData, TValue>({
    column,
    title,
    className,
}: DataTableColumnHeaderProps<TData, TValue>) {
    'use no memo';

    if (!column.getCanSort()) {
        return <div className={cn(className)}>{title}</div>;
    }

    return (
        <Button
            variant="ghost"
            size="sm"
            className={cn('h-8', className)}
            onClick={() => column.toggleSorting(column.getIsSorted() === 'asc')}
        >
            {title}
            {column.getIsSorted() === 'desc' ? (
                <ArrowDown className="ml-2 size-4" />
            ) : column.getIsSorted() === 'asc' ? (
                <ArrowUp className="ml-2 size-4" />
            ) : (
                <ArrowUpDown className="ml-2 size-4" />
            )}
        </Button>
    );
}
