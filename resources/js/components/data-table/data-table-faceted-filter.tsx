'use no memo';

import { Check, PlusCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

import type { FilterOption } from '@/types';

interface DataTableFacetedFilterProps {
    name: string;
    label: string;
    options: FilterOption[];
    selected: string[];
    onSelectionChange: (values: string[]) => void;
}

export function DataTableFacetedFilter({
    label,
    options,
    selected,
    onSelectionChange,
}: DataTableFacetedFilterProps) {
    const selectedSet = new Set(selected);

    function toggleValue(value: string) {
        const next = new Set(selectedSet);
        if (next.has(value)) {
            next.delete(value);
        } else {
            next.add(value);
        }
        onSelectionChange(Array.from(next));
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-8 border-dashed"
                >
                    <PlusCircle className="mr-2 size-4" />
                    {label}
                    {selectedSet.size > 0 && (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-2 h-4"
                            />
                            <Badge
                                variant="secondary"
                                className="rounded-sm px-1 font-normal"
                            >
                                {selectedSet.size}
                            </Badge>
                        </>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-50 p-0" align="start">
                <Command>
                    <CommandInput placeholder={label} />
                    <CommandList>
                        <CommandEmpty>Sin resultados.</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => {
                                const isSelected = selectedSet.has(
                                    option.value,
                                );
                                return (
                                    <CommandItem
                                        key={option.value}
                                        onSelect={() =>
                                            toggleValue(option.value)
                                        }
                                    >
                                        <div
                                            className={cn(
                                                'mr-2 flex size-4 items-center justify-center rounded-sm border border-primary',
                                                isSelected
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'opacity-50 [&_svg]:invisible',
                                            )}
                                        >
                                            <Check className="size-4" />
                                        </div>
                                        {option.icon && (
                                            <option.icon className="mr-2 size-4 text-muted-foreground" />
                                        )}
                                        <span>{option.label}</span>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                        {selectedSet.size > 0 && (
                            <>
                                <CommandSeparator />
                                <CommandGroup>
                                    <CommandItem
                                        onSelect={() => onSelectionChange([])}
                                        className="justify-center text-center"
                                    >
                                        Limpiar filtros
                                    </CommandItem>
                                </CommandGroup>
                            </>
                        )}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
