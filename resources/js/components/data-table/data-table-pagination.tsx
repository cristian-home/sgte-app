import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

import type { PaginatedData } from '@/types';

interface DataTablePaginationProps<TData> {
    pagination: PaginatedData<TData>;
    onNavigate: (url: string) => void;
    onPerPageChange: (perPage: string) => void;
}

export function DataTablePagination<TData>({
    pagination,
    onNavigate,
    onPerPageChange,
}: DataTablePaginationProps<TData>) {
    const pageLinks = pagination.links.slice(1, -1);

    return (
        <div className="flex items-center justify-between gap-4">
            <p className="text-sm text-muted-foreground">
                {pagination.from && pagination.to
                    ? `Mostrando ${pagination.from} a ${pagination.to} de ${pagination.total} resultados`
                    : `${pagination.total} resultados`}
            </p>

            <div className="flex items-center gap-4">
                <div className="flex items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        Por página
                    </span>
                    <Select
                        value={String(pagination.per_page)}
                        onValueChange={onPerPageChange}
                    >
                        <SelectTrigger className="h-8 w-18">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="10">10</SelectItem>
                            <SelectItem value="15">15</SelectItem>
                            <SelectItem value="25">25</SelectItem>
                            <SelectItem value="50">50</SelectItem>
                            <SelectItem value="100">100</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() =>
                            pagination.prev_page_url &&
                            onNavigate(pagination.prev_page_url)
                        }
                        disabled={!pagination.prev_page_url}
                    >
                        <ChevronLeft className="size-4" />
                        <span className="sr-only">Anterior</span>
                    </Button>

                    {pageLinks.map((link) => (
                        <Button
                            key={link.label}
                            variant={link.active ? 'default' : 'outline'}
                            size="icon"
                            className="size-8"
                            onClick={() => link.url && onNavigate(link.url)}
                            disabled={!link.url || link.active}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}

                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() =>
                            pagination.next_page_url &&
                            onNavigate(pagination.next_page_url)
                        }
                        disabled={!pagination.next_page_url}
                    >
                        <ChevronRight className="size-4" />
                        <span className="sr-only">Siguiente</span>
                    </Button>
                </div>
            </div>
        </div>
    );
}
