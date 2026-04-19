import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import VehicleCombobox, {
    type VehicleOption,
} from '@/components/vehicles/vehicle-combobox';

export interface VehicleLocationFormData {
    vehicle_id: number | '';
    service_id: number | null | '';
    recorded_at: string;
    latitude: string;
    longitude: string;
    accuracy: string;
    is_manual: boolean;
    [key: string]: string | number | boolean | null | undefined;
}

interface Props {
    data: VehicleLocationFormData;
    setData: <K extends keyof VehicleLocationFormData>(
        key: K,
        value: VehicleLocationFormData[K],
    ) => void;
    errors: Partial<Record<keyof VehicleLocationFormData, string>>;
    vehicles: VehicleOption[];
}

export function VehicleLocationForm({
    data,
    setData,
    errors,
    vehicles,
}: Props) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-1 md:col-span-2">
                <Label htmlFor="vehicle_id">Vehículo *</Label>
                <VehicleCombobox
                    id="vehicle_id"
                    vehicles={vehicles}
                    value={data.vehicle_id === '' ? null : data.vehicle_id}
                    onChange={(value) => setData('vehicle_id', value ?? '')}
                    invalid={Boolean(errors.vehicle_id)}
                />
                <InputError message={errors.vehicle_id} />
            </div>
            <div className="space-y-1 md:col-span-2">
                <Label htmlFor="recorded_at">Fecha/Hora *</Label>
                <Input
                    id="recorded_at"
                    type="datetime-local"
                    value={data.recorded_at}
                    onChange={(e) => setData('recorded_at', e.target.value)}
                    aria-invalid={Boolean(errors.recorded_at)}
                />
                <InputError message={errors.recorded_at} />
            </div>
            <div className="space-y-1">
                <Label htmlFor="latitude">Latitud *</Label>
                <Input
                    id="latitude"
                    type="number"
                    step="any"
                    value={data.latitude}
                    onChange={(e) => setData('latitude', e.target.value)}
                    aria-invalid={Boolean(errors.latitude)}
                />
                <InputError message={errors.latitude} />
            </div>
            <div className="space-y-1">
                <Label htmlFor="longitude">Longitud *</Label>
                <Input
                    id="longitude"
                    type="number"
                    step="any"
                    value={data.longitude}
                    onChange={(e) => setData('longitude', e.target.value)}
                    aria-invalid={Boolean(errors.longitude)}
                />
                <InputError message={errors.longitude} />
            </div>
            <div className="space-y-1">
                <Label htmlFor="accuracy">Precisión (metros)</Label>
                <Input
                    id="accuracy"
                    type="number"
                    step="any"
                    value={data.accuracy}
                    onChange={(e) => setData('accuracy', e.target.value)}
                    aria-invalid={Boolean(errors.accuracy)}
                />
                <InputError message={errors.accuracy} />
            </div>
            <div className="flex items-center gap-2">
                <Switch
                    id="is_manual"
                    checked={data.is_manual}
                    onCheckedChange={(checked) =>
                        setData('is_manual', Boolean(checked))
                    }
                />
                <Label htmlFor="is_manual">Entrada manual</Label>
            </div>
        </div>
    );
}
