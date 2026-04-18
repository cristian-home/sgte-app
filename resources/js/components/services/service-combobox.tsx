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
import { dateFormatter, parseDueDate } from '@/lib/document-status';
import { cn } from '@/lib/utils';

export type ServiceOption = {
    id: number;
    service_date: string | null;
    vehicle?: { id: number; plate: string } | null;
    contract?: { id: number; contract_number: string } | null;
    driver?: { id: number; first_name: string; first_lastname: string } | null;
};

interface ServiceComboboxProps {
    services: ServiceOption[];
    value: string | number | null;
    onChange: (value: string) => void;
    /**
     * Extra options that MUST appear even if not in the main list.
     * Used by edit forms where the linked service is older than the
     * 60-day window. Deduped by id.
     */
    forceInclude?: ServiceOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    id?: string;
    className?: string;
}

function formatDate(date: string | null): string {
    const parsed = parseDueDate(date);
    if (parsed === null) {
        return '—';
    }
    return dateFormatter.format(parsed);
}

function driverName(driver: ServiceOption['driver']): string {
    if (!driver) return '—';
    return [driver.first_name, driver.first_lastname]
        .filter(Boolean)
        .join(' ')
        .trim();
}

function primaryLabel(service: ServiceOption): string {
    return `${formatDate(service.service_date)} — ${service.vehicle?.plate ?? '—'}`;
}

function secondaryLabel(service: ServiceOption): string {
    const contract = service.contract?.contract_number ?? '—';
    const driver = driverName(service.driver);
    return `${contract} · ${driver}`;
}

export default function ServiceCombobox({
    services,
    value,
    onChange,
    forceInclude,
    placeholder = 'Seleccionar servicio...',
    searchPlaceholder = 'Buscar servicio...',
    disabled = false,
    invalid,
    id,
    className,
}: ServiceComboboxProps) {
    const [open, setOpen] = useState(false);

    const options = useMemo(() => {
        if (!forceInclude || forceInclude.length === 0) {
            return services;
        }
        const byId = new Map<number, ServiceOption>();
        for (const s of services) {
            byId.set(s.id, s);
        }
        for (const s of forceInclude) {
            if (!byId.has(s.id)) {
                byId.set(s.id, s);
            }
        }
        return Array.from(byId.values());
    }, [services, forceInclude]);

    const selected = useMemo(() => {
        if (!value) return null;
        const numValue = Number(value);
        return options.find((s) => s.id === numValue) ?? null;
    }, [options, value]);

    const displayLabel = selected ? primaryLabel(selected) : placeholder;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <div className={cn('relative', className)}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        aria-invalid={invalid}
                        disabled={disabled}
                        className={cn(
                            'w-full justify-between font-normal',
                            !selected && 'text-muted-foreground',
                        )}
                    >
                        <span className="truncate">{displayLabel}</span>
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
                        <CommandEmpty>Sin servicios recientes.</CommandEmpty>
                        <CommandGroup>
                            {options.map((s) => (
                                <CommandItem
                                    key={s.id}
                                    value={String(s.id)}
                                    keywords={[
                                        s.vehicle?.plate ?? '',
                                        s.contract?.contract_number ?? '',
                                        s.driver?.first_name ?? '',
                                        s.driver?.first_lastname ?? '',
                                    ]}
                                    onSelect={() => {
                                        onChange(String(s.id));
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            selected?.id === s.id
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate font-medium">
                                            {primaryLabel(s)}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {secondaryLabel(s)}
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
