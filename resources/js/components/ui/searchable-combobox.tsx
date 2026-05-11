'use no memo';

import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
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

export interface SearchableComboboxProps<T> {
    items: T[];
    /** Currently selected id (stringified). Empty string when nothing is picked. */
    value: string;
    /** Receives the picked item's key (stringified id). Receives '' on clear. */
    onChange: (value: string) => void;
    /** Stable key per item — typically `String(item.id)`. */
    getKey: (item: T) => string;
    /**
     * Text that cmdk's internal filter matches against. Include any field
     * the user might type to find this item (e.g. name + code + cédula).
     * Items with multiple match dimensions can join them with spaces.
     */
    getSearchText: (item: T) => string;
    /** Renders a single row in the popover list. `active` marks the current selection. */
    renderItem: (item: T, opts: { active: boolean }) => ReactNode;
    /**
     * Renders the trigger label when an item is selected. Defaults to the
     * full result of `getSearchText` truncated. Provide this for richer
     * triggers (e.g. monospace plate + tercero subtitle).
     */
    renderTrigger?: (item: T) => ReactNode;
    /** Optional grouping. Items with the same group key are bucketed together. */
    groupBy?: (item: T) => string;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    invalid?: boolean;
    id?: string;
    name?: string;
    className?: string;
    contentClassName?: string;
}

/**
 * Generic combobox over an in-memory list, backed by shadcn Popover + cmdk
 * Command. Supports searching, optional grouping, rich row rendering via
 * `renderItem`, and a clear (X) button on the trigger. Use this instead of
 * a native `<Select>` whenever the list can grow large enough that the user
 * benefits from text search, or when each row should show secondary info
 * beyond a one-liner.
 *
 * Form integration: the value is the picked item's stringified key. An
 * empty string means "nothing selected". Pair with Inertia useForm
 * (`setData('field', value)`) — no special handling needed.
 */
export default function SearchableCombobox<T>({
    items,
    value,
    onChange,
    getKey,
    getSearchText,
    renderItem,
    renderTrigger,
    groupBy,
    placeholder = 'Seleccionar…',
    searchPlaceholder = 'Buscar…',
    emptyText = 'Sin resultados.',
    disabled = false,
    invalid,
    id,
    name,
    className,
    contentClassName,
}: SearchableComboboxProps<T>) {
    const [open, setOpen] = useState(false);

    const selected = useMemo(
        () => items.find((it) => getKey(it) === value) ?? null,
        [items, value, getKey],
    );

    // Bucket items into groups when groupBy is provided; otherwise render
    // all items in a single anonymous group.
    const grouped = useMemo(() => {
        if (!groupBy) {
            return [{ heading: undefined as string | undefined, items }];
        }
        const buckets = new Map<string, T[]>();
        for (const it of items) {
            const key = groupBy(it);
            let arr = buckets.get(key);
            if (!arr) {
                arr = [];
                buckets.set(key, arr);
            }
            arr.push(it);
        }
        return Array.from(buckets.entries()).map(([heading, items]) => ({
            heading,
            items,
        }));
    }, [items, groupBy]);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <div className={cn('relative self-start', className)}>
                <PopoverTrigger asChild>
                    <Button
                        id={id}
                        name={name}
                        type="button"
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
                            {selected
                                ? (renderTrigger?.(selected) ??
                                  getSearchText(selected))
                                : placeholder}
                        </span>
                        <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                {selected && !disabled && (
                    <button
                        type="button"
                        aria-label="Limpiar selección"
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
                className={cn(
                    'w-[--radix-popover-trigger-width] p-0',
                    contentClassName,
                )}
                align="start"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        {grouped.map((group, gi) => (
                            <CommandGroup
                                key={group.heading ?? `__g_${gi}`}
                                heading={group.heading}
                            >
                                {group.items.map((item) => {
                                    const key = getKey(item);
                                    const active = key === value;
                                    return (
                                        <CommandItem
                                            key={key}
                                            value={key}
                                            keywords={[getSearchText(item)]}
                                            onSelect={() => {
                                                onChange(key);
                                                setOpen(false);
                                            }}
                                            className="items-start gap-2"
                                        >
                                            <Check
                                                className={cn(
                                                    'mt-0.5 size-4 shrink-0',
                                                    active
                                                        ? 'opacity-100'
                                                        : 'opacity-0',
                                                )}
                                            />
                                            <div className="min-w-0 flex-1">
                                                {renderItem(item, { active })}
                                            </div>
                                        </CommandItem>
                                    );
                                })}
                            </CommandGroup>
                        ))}
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
