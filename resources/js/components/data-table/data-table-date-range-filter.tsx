'use no memo';

import { PlusCircle, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';

export interface DateRangeValue {
    from: string;
    to: string;
}

interface DataTableDateRangeFilterProps {
    label: string;
    from: string;
    to: string;
    onChange: (range: DateRangeValue) => void;
    /** Optional id applied to the Desde input — useful for Dusk anchors. */
    fromInputId?: string;
    /** Optional id applied to the Hasta input — useful for Dusk anchors. */
    toInputId?: string;
}

/**
 * Toolbar-style date-range filter matching the DataTableFacetedFilter
 * visual language (dashed outline "+ Label" trigger, PlusCircle icon,
 * badge rendering the selected range). Popover exposes Desde + Hasta
 * date inputs plus a "Limpiar" action that clears both fields.
 *
 * Emits the full {from, to} tuple on every change so callers can
 * forward both values to the server-table filter hook in a single
 * handler without tracking intermediate state.
 */
export function DataTableDateRangeFilter({
    label,
    from,
    to,
    onChange,
    fromInputId,
    toInputId,
}: DataTableDateRangeFilterProps) {
    const hasSelection = from !== '' || to !== '';

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
                    {hasSelection && (
                        <>
                            <Separator
                                orientation="vertical"
                                className="mx-2 h-4"
                            />
                            <Badge
                                variant="secondary"
                                className="rounded-sm px-1 font-normal"
                            >
                                {from || '…'} → {to || '…'}
                            </Badge>
                        </>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-64 space-y-3 p-3" align="start">
                <div className="space-y-1">
                    <Label htmlFor={fromInputId}>Desde</Label>
                    <Input
                        id={fromInputId}
                        type="date"
                        value={from}
                        onChange={(e) => onChange({ from: e.target.value, to })}
                    />
                </div>
                <div className="space-y-1">
                    <Label htmlFor={toInputId}>Hasta</Label>
                    <Input
                        id={toInputId}
                        type="date"
                        value={to}
                        onChange={(e) => onChange({ from, to: e.target.value })}
                    />
                </div>
                {hasSelection && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-full justify-center"
                        onClick={() => onChange({ from: '', to: '' })}
                    >
                        <X className="mr-2 size-4" />
                        Limpiar
                    </Button>
                )}
            </PopoverContent>
        </Popover>
    );
}
