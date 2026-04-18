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

export type UserOption = {
    id: number;
    name: string;
    email: string;
};

interface UserComboboxProps {
    users: UserOption[];
    value: number | string | null;
    onChange: (value: number | null) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    id?: string;
    className?: string;
}

export default function UserCombobox({
    users,
    value,
    onChange,
    placeholder = 'Todos los usuarios',
    searchPlaceholder = 'Buscar usuario...',
    disabled = false,
    invalid,
    id,
    className,
}: UserComboboxProps) {
    const [open, setOpen] = useState(false);

    const selected = useMemo(() => {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        const numValue = Number(value);
        return users.find((user) => user.id === numValue) ?? null;
    }, [users, value]);

    const displayLabel = selected ? selected.name : placeholder;

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
                        <CommandEmpty>Sin usuarios.</CommandEmpty>
                        <CommandGroup>
                            {users.map((user) => (
                                <CommandItem
                                    key={user.id}
                                    value={String(user.id)}
                                    keywords={[user.name, user.email]}
                                    onSelect={() => {
                                        onChange(user.id);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 size-4',
                                            selected?.id === user.id
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <div className="flex min-w-0 flex-col">
                                        <span className="truncate font-medium">
                                            {user.name}
                                        </span>
                                        <span className="truncate text-xs text-muted-foreground">
                                            {user.email}
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
