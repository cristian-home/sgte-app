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

export type VehicleOption = {
    id: number;
    plate: string;
    brand: string | null;
    line: string | null;
};

interface VehicleComboboxProps {
    vehicles: VehicleOption[];
    value: number | string | null;
    onChange: (value: number | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    id?: string;
    className?: string;
}

export default function VehicleCombobox({
    vehicles,
    value,
    onChange,
    placeholder = 'Todos los vehículos',
    searchPlaceholder = 'Buscar por placa, marca o línea...',
    disabled = false,
    invalid,
    id,
    className,
}: VehicleComboboxProps) {
    'use no memo';
    const [open, setOpen] = useState(false);

    const selected = useMemo(() => {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        const numValue = Number(value);
        return vehicles.find((v) => v.id === numValue) ?? null;
    }, [vehicles, value]);

    const displayLabel = selected ? selected.plate : placeholder;

    return (
        <Popover open={open} onOpenChange={setOpen} modal>
            <div className={cn('relative min-w-0', className)}>
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
                            onChange(null);
                        }}
                        aria-label="Limpiar selección"
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
                        <CommandEmpty>Sin vehículos.</CommandEmpty>
                        <CommandGroup>
                            {vehicles.map((vehicle) => (
                                <CommandItem
                                    key={vehicle.id}
                                    value={String(vehicle.id)}
                                    keywords={[
                                        vehicle.plate,
                                        vehicle.brand ?? '',
                                        vehicle.line ?? '',
                                    ]}
                                    onSelect={() => {
                                        onChange(vehicle.id);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            selected?.id === vehicle.id
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate font-mono font-medium">
                                            {vehicle.plate}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {[vehicle.brand, vehicle.line]
                                                .filter(Boolean)
                                                .join(' ') || '—'}
                                        </span>
                                    </div>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
