import FieldFooter from '@/components/field-footer';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

export interface FuecNumberRangeFormData {
    resolution_number: string;
    resolution_year: number | '';
    range_from: number | '';
    range_to: number | '';
    active: boolean;
    notes: string;
}

type SetDataKey<K extends keyof FuecNumberRangeFormData> = (
    key: K,
    value: FuecNumberRangeFormData[K],
) => void;

interface Props {
    data: FuecNumberRangeFormData;
    setData: <K extends keyof FuecNumberRangeFormData>(
        key: K,
        value: FuecNumberRangeFormData[K],
    ) => void;
    errors: Partial<Record<keyof FuecNumberRangeFormData, string>>;
}

export function FuecNumberRangeForm({ data, setData, errors }: Props) {
    return (
        <div className="space-y-4">
            <div className="space-y-1">
                <Label htmlFor="resolution_number">
                    Número de resolución *
                </Label>
                <Input
                    id="resolution_number"
                    value={data.resolution_number}
                    onChange={(e) =>
                        setData('resolution_number', e.target.value)
                    }
                    aria-invalid={Boolean(errors.resolution_number)}
                />
                <FieldFooter error={errors.resolution_number} />
            </div>

            <div className="grid gap-x-4 gap-y-2 md:grid-cols-3 md:grid-rows-[auto_auto_auto]">
                <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label htmlFor="resolution_year">Año *</Label>
                    <Input
                        id="resolution_year"
                        type="number"
                        min={2000}
                        max={2100}
                        value={data.resolution_year}
                        onChange={(e) =>
                            setData(
                                'resolution_year',
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                        aria-invalid={Boolean(errors.resolution_year)}
                    />
                    <FieldFooter error={errors.resolution_year} />
                </div>
                <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label htmlFor="range_from">Desde *</Label>
                    <Input
                        id="range_from"
                        type="number"
                        min={1}
                        value={data.range_from}
                        onChange={(e) =>
                            setData(
                                'range_from',
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                        aria-invalid={Boolean(errors.range_from)}
                    />
                    <FieldFooter error={errors.range_from} />
                </div>
                <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label htmlFor="range_to">Hasta *</Label>
                    <Input
                        id="range_to"
                        type="number"
                        min={2}
                        value={data.range_to}
                        onChange={(e) =>
                            setData(
                                'range_to',
                                e.target.value === ''
                                    ? ''
                                    : Number(e.target.value),
                            )
                        }
                        aria-invalid={Boolean(errors.range_to)}
                    />
                    <FieldFooter error={errors.range_to} />
                </div>
            </div>

            <div className="flex items-center gap-2">
                <Switch
                    id="active"
                    checked={data.active}
                    onCheckedChange={(checked) =>
                        setData('active', Boolean(checked))
                    }
                />
                <Label htmlFor="active">Activar este rango</Label>
            </div>

            <div className="space-y-1">
                <Label htmlFor="notes">Notas</Label>
                <textarea
                    id="notes"
                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
                <FieldFooter error={errors.notes} />
            </div>
        </div>
    );
}

export type { SetDataKey };
