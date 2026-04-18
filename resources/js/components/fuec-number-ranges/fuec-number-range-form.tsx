import InputError from '@/components/input-error';
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
        <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-1 md:col-span-2">
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
                <InputError message={errors.resolution_number} />
            </div>
            <div className="space-y-1">
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
                            e.target.value === '' ? '' : Number(e.target.value),
                        )
                    }
                    aria-invalid={Boolean(errors.resolution_year)}
                />
                <InputError message={errors.resolution_year} />
            </div>
            <div className="space-y-1">
                <Label htmlFor="range_from">Desde *</Label>
                <Input
                    id="range_from"
                    type="number"
                    min={1}
                    value={data.range_from}
                    onChange={(e) =>
                        setData(
                            'range_from',
                            e.target.value === '' ? '' : Number(e.target.value),
                        )
                    }
                    aria-invalid={Boolean(errors.range_from)}
                />
                <InputError message={errors.range_from} />
            </div>
            <div className="space-y-1">
                <Label htmlFor="range_to">Hasta *</Label>
                <Input
                    id="range_to"
                    type="number"
                    min={2}
                    value={data.range_to}
                    onChange={(e) =>
                        setData(
                            'range_to',
                            e.target.value === '' ? '' : Number(e.target.value),
                        )
                    }
                    aria-invalid={Boolean(errors.range_to)}
                />
                <InputError message={errors.range_to} />
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
            <div className="space-y-1 md:col-span-2">
                <Label htmlFor="notes">Notas</Label>
                <textarea
                    id="notes"
                    className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                />
                <InputError message={errors.notes} />
            </div>
        </div>
    );
}

export type { SetDataKey };
