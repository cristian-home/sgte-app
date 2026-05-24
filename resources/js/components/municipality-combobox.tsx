'use no memo';

import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { Department, Municipality } from '@/types/models';

export type MunicipalityOption = Pick<
    Municipality,
    'id' | 'name' | 'code' | 'department_id' | 'latitude' | 'longitude'
> & {
    department?: Pick<Department, 'id' | 'name'>;
};

interface MunicipalityComboboxProps {
    municipalities: MunicipalityOption[];
    value: string | number | null;
    onChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    id?: string;
    className?: string;
}

export default function MunicipalityCombobox({
    municipalities,
    value,
    onChange,
    placeholder = 'Seleccionar municipio...',
    searchPlaceholder = 'Buscar municipio...',
    disabled = false,
    invalid,
    id,
    className,
}: MunicipalityComboboxProps) {
    const [open, setOpen] = useState(false);

    const grouped = useMemo(() => {
        const map = new Map<
            string,
            { departmentName: string; items: MunicipalityOption[] }
        >();
        for (const m of municipalities) {
            const deptName = m.department?.name ?? 'Sin departamento';
            let group = map.get(deptName);
            if (!group) {
                group = { departmentName: deptName, items: [] };
                map.set(deptName, group);
            }
            group.items.push(m);
        }
        return Array.from(map.values());
    }, [municipalities]);

    const selected = useMemo(() => {
        if (!value) return null;
        const numValue = Number(value);
        return municipalities.find((m) => m.id === numValue) ?? null;
    }, [municipalities, value]);

    const displayLabel = selected
        ? `${selected.name} (${selected.department?.name ?? ''})`
        : placeholder;

    return (
        <Popover open={open} onOpenChange={setOpen} modal>
            {/* `self-start` keeps this wrapper at its natural button height
                even when the parent grid cell stretches (e.g. service form
                aligns the municipality column with the address column, and
                the address column grows when the coords badge is rendered).
                Without it, the absolutely-positioned X drifts vertically
                with the wrapper. */}
            <div className={cn('relative min-w-0 self-start', className)}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-invalid={invalid}
                        disabled={disabled}
                        className={cn(
                            'w-full min-w-0 justify-between font-normal',
                            !selected && 'text-muted-foreground',
                        )}
                    >
                        <span className="min-w-0 truncate text-left">
                            {displayLabel}
                        </span>
                        <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                {selected && !disabled && (
                    <button
                        type="button"
                        className="absolute top-1/2 right-8 -translate-y-1/2 rounded-sm p-0.5 opacity-50 hover:opacity-100"
                        onClick={(e) => {
                            e.stopPropagation();
                            onChange('');
                        }}
                    >
                        <X className="size-3.5" />
                    </button>
                )}
            </div>
            <PopoverContent
                className="w-[--radix-popover-trigger-width] p-0"
                align="start"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>No se encontro municipio.</CommandEmpty>
                        {grouped.map((group) => (
                            <CommandGroup
                                key={group.departmentName}
                                heading={group.departmentName}
                            >
                                {group.items.map((m) => (
                                    <CommandItem
                                        key={m.id}
                                        value={String(m.id)}
                                        keywords={[m.name, m.code]}
                                        onSelect={() => {
                                            onChange(String(m.id));
                                            setOpen(false);
                                        }}
                                    >
                                        <Check
                                            className={cn(
                                                'mr-2 size-4',
                                                selected?.id === m.id
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        <span>{m.name}</span>
                                        <span className="ml-auto text-xs text-muted-foreground">
                                            {m.code}
                                        </span>
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        ))}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
