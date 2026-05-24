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
import type { DocumentType, ThirdParty } from '@/types/models';

export type ThirdPartyOption = Pick<
    ThirdParty,
    | 'id'
    | 'identification_number'
    | 'is_natural_person'
    | 'first_name'
    | 'first_lastname'
    | 'company_name'
    | 'is_customer'
    | 'is_provider'
> & {
    document_type?: Pick<DocumentType, 'id' | 'code' | 'name'> | null;
};

interface ThirdPartyComboboxProps {
    thirdParties: ThirdPartyOption[];
    value: string | number | null;
    onChange: (value: string) => void;
    /**
     * Filter options by role. `'customer'` → only `is_customer === true`;
     * `'provider'` → only `is_provider === true`; omit for all.
     */
    role?: 'customer' | 'provider';
    /**
     * Extra options that MUST appear in the list even if the role
     * filter would otherwise hide them. Designed for edit forms
     * whose currently-selected third party has been flipped off the
     * role axis (e.g. ex-cliente). Deduped by id.
     */
    forceInclude?: ThirdPartyOption[];
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    id?: string;
    className?: string;
}

function computedName(tp: ThirdPartyOption): string {
    if (tp.is_natural_person) {
        return (
            [tp.first_name, tp.first_lastname]
                .filter(Boolean)
                .join(' ')
                .trim() || '—'
        );
    }
    return tp.company_name ?? '—';
}

function secondaryLabel(tp: ThirdPartyOption): string {
    const code = tp.document_type?.code ?? '?';
    return `${code} ${tp.identification_number}`;
}

export default function ThirdPartyCombobox({
    thirdParties,
    value,
    onChange,
    role,
    forceInclude,
    placeholder = 'Seleccionar tercero...',
    searchPlaceholder = 'Buscar tercero...',
    disabled = false,
    invalid,
    id,
    className,
}: ThirdPartyComboboxProps) {
    'use no memo';
    const [open, setOpen] = useState(false);

    const options = useMemo(() => {
        const filtered = role
            ? thirdParties.filter((tp) =>
                  role === 'customer' ? tp.is_customer : tp.is_provider,
              )
            : thirdParties;

        if (!forceInclude || forceInclude.length === 0) {
            return filtered;
        }

        const byId = new Map<number, ThirdPartyOption>();
        for (const tp of filtered) {
            byId.set(tp.id, tp);
        }
        for (const tp of forceInclude) {
            if (!byId.has(tp.id)) {
                byId.set(tp.id, tp);
            }
        }
        return Array.from(byId.values());
    }, [thirdParties, role, forceInclude]);

    const selected = useMemo(() => {
        if (!value) return null;
        const numValue = Number(value);
        return options.find((tp) => tp.id === numValue) ?? null;
    }, [options, value]);

    const displayLabel = selected ? computedName(selected) : placeholder;

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
                        <CommandEmpty>Sin terceros.</CommandEmpty>
                        <CommandGroup>
                            {options.map((tp) => (
                                <CommandItem
                                    key={tp.id}
                                    value={String(tp.id)}
                                    keywords={[
                                        tp.identification_number,
                                        tp.first_name ?? '',
                                        tp.first_lastname ?? '',
                                        tp.company_name ?? '',
                                    ]}
                                    onSelect={() => {
                                        onChange(String(tp.id));
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            selected?.id === tp.id
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate font-medium">
                                            {computedName(tp)}
                                        </span>
                                        <span className="truncate font-mono text-xs text-muted-foreground">
                                            {secondaryLabel(tp)}
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
