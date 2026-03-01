'use no memo';

import { X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTableFacetedFilter } from './data-table-faceted-filter';
import { DataTableViewOptions } from './data-table-view-options';

import type { Table } from '@tanstack/react-table';
import type { FilterDefinition } from '@/types';

interface DataTableToolbarProps<TData> {
    table: Table<TData>;
    search: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    filters?: FilterDefinition[];
    activeFilters?: Record<string, string[]>;
    onFilterChange?: (name: string, values: string[]) => void;
    onClearFilters?: () => void;
    actions?: React.ReactNode;
}

export function DataTableToolbar<TData>({
    table,
    search,
    onSearchChange,
    searchPlaceholder = 'Buscar...',
    filters,
    activeFilters,
    onFilterChange,
    onClearFilters,
    actions,
}: DataTableToolbarProps<TData>) {
    const hasActiveFilters =
        activeFilters && Object.values(activeFilters).some((v) => v.length > 0);

    return (
        <div className="flex items-center justify-between gap-4">
            <div className="flex flex-1 items-center gap-2">
                <Input
                    placeholder={searchPlaceholder}
                    value={search}
                    onChange={(e) => onSearchChange(e.target.value)}
                    className="h-8 w-37.5 lg:w-62.5"
                />
                {filters?.map((filter) => (
                    <DataTableFacetedFilter
                        key={filter.name}
                        name={filter.name}
                        label={filter.label}
                        options={filter.options}
                        selected={activeFilters?.[filter.name] ?? []}
                        onSelectionChange={(values) =>
                            onFilterChange?.(filter.name, values)
                        }
                    />
                ))}
                {hasActiveFilters && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="h-8 px-2 lg:px-3"
                        onClick={onClearFilters}
                    >
                        Limpiar
                        <X className="ml-2 size-4" />
                    </Button>
                )}
            </div>
            <div className="flex items-center gap-2">
                <DataTableViewOptions table={table} />
                {actions}
            </div>
        </div>
    );
}
