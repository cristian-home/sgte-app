import { Check, ChevronDown, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
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

export interface RoleOption {
    value: string;
    label: string;
}

interface Props {
    options: RoleOption[];
    value: string[];
    onChange: (next: string[]) => void;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    id?: string;
    className?: string;
}

export function RoleMultiCombobox({
    options,
    value,
    onChange,
    placeholder = 'Selecciona roles…',
    disabled,
    invalid,
    id,
    className,
}: Props) {
    const [open, setOpen] = useState(false);

    function toggle(roleValue: string) {
        if (value.includes(roleValue)) {
            onChange(value.filter((v) => v !== roleValue));
        } else {
            onChange([...value, roleValue]);
        }
    }

    function remove(e: React.MouseEvent, roleValue: string) {
        e.preventDefault();
        e.stopPropagation();
        onChange(value.filter((v) => v !== roleValue));
    }

    const selectedOptions = options.filter((o) => value.includes(o.value));

    return (
        <Popover open={open} onOpenChange={setOpen} modal>
            <PopoverTrigger asChild>
                <button
                    id={id}
                    type="button"
                    disabled={disabled}
                    aria-invalid={invalid}
                    className={cn(
                        'inline-flex min-h-9 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-1.5 text-sm transition-colors hover:bg-accent/30',
                        'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                        'disabled:opacity-50',
                        invalid && 'border-destructive ring-destructive/30',
                        className,
                    )}
                >
                    <span className="flex flex-1 flex-wrap items-center gap-1">
                        {selectedOptions.length === 0 && (
                            <span className="text-muted-foreground">
                                {placeholder}
                            </span>
                        )}
                        {selectedOptions.map((opt) => (
                            <Badge
                                key={opt.value}
                                variant="secondary"
                                className="gap-1"
                            >
                                {opt.label}
                                {!disabled && (
                                    <span
                                        role="button"
                                        tabIndex={-1}
                                        onClick={(e) => remove(e, opt.value)}
                                        className="-mr-1 ml-0.5 inline-flex size-3.5 items-center justify-center rounded-sm hover:bg-muted-foreground/20"
                                        aria-label={`Quitar ${opt.label}`}
                                    >
                                        <X className="size-3" />
                                    </span>
                                )}
                            </Badge>
                        ))}
                    </span>
                    <ChevronDown className="ml-2 size-4 shrink-0 text-muted-foreground" />
                </button>
            </PopoverTrigger>
            <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0">
                <Command>
                    <CommandInput placeholder="Buscar rol…" />
                    <CommandList>
                        <CommandEmpty>Sin resultados.</CommandEmpty>
                        <CommandGroup>
                            {options.map((opt) => {
                                const checked = value.includes(opt.value);
                                return (
                                    <CommandItem
                                        key={opt.value}
                                        value={opt.label}
                                        onSelect={() => toggle(opt.value)}
                                    >
                                        <Check
                                            className={cn(
                                                'size-4',
                                                checked
                                                    ? 'opacity-100'
                                                    : 'opacity-0',
                                            )}
                                        />
                                        {opt.label}
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
