import InputError from '@/components/input-error';
import MunicipalityCombobox, {
    type MunicipalityOption,
} from '@/components/municipality-combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

export interface ThirdPartyOption {
    id: number;
    identification_number: string;
    first_name: string | null;
    first_lastname: string | null;
    company_name: string | null;
    is_natural_person: boolean;
}

export interface VehicleFormData {
    internal_code: string;
    plate: string;
    mobile_number: string;
    brand: string;
    line: string;
    model_year: string;
    type: string;
    engine_number: string;
    chassis_number: string;
    capacity: string;
    municipality_id: string;
    is_third_party: boolean;
    third_party_id: string;
    soat_due_date: string;
    rtm_due_date: string;
    operation_card_due_date: string;
    status: string;
}

function thirdPartyLabel(tp: ThirdPartyOption): string {
    if (tp.is_natural_person) {
        return `${tp.first_name} ${tp.first_lastname} (${tp.identification_number})`;
    }
    return `${tp.company_name} (${tp.identification_number})`;
}

interface VehicleFormProps {
    data: VehicleFormData;
    setData: <K extends keyof VehicleFormData>(
        key: K,
        value: VehicleFormData[K],
    ) => void;
    errors: Partial<Record<keyof VehicleFormData, string>>;
    municipalities: MunicipalityOption[];
    thirdParties: ThirdPartyOption[];
    idPrefix?: string;
}

export default function VehicleForm({
    data,
    setData,
    errors,
    municipalities,
    thirdParties,
    idPrefix = '',
}: VehicleFormProps) {
    const id = (name: string) => (idPrefix ? `${idPrefix}_${name}` : name);
    const invalid = (field: keyof VehicleFormData) =>
        errors[field] ? true : undefined;

    return (
        <>
            <div className="grid gap-4 md:grid-cols-3 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('internal_code')}
                >
                    <Label htmlFor={id('internal_code')}>Código Interno</Label>
                    <Input
                        id={id('internal_code')}
                        value={data.internal_code}
                        aria-invalid={invalid('internal_code')}
                        onChange={(e) =>
                            setData('internal_code', e.target.value)
                        }
                    />
                    <InputError message={errors.internal_code} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('plate')}
                >
                    <Label htmlFor={id('plate')}>Placa</Label>
                    <Input
                        id={id('plate')}
                        value={data.plate}
                        aria-invalid={invalid('plate')}
                        onChange={(e) =>
                            setData('plate', e.target.value.toUpperCase())
                        }
                        maxLength={6}
                    />
                    <InputError message={errors.plate} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('mobile_number')}
                >
                    <Label htmlFor={id('mobile_number')}>Número Móvil</Label>
                    <Input
                        id={id('mobile_number')}
                        value={data.mobile_number}
                        aria-invalid={invalid('mobile_number')}
                        onChange={(e) =>
                            setData('mobile_number', e.target.value)
                        }
                    />
                    <InputError message={errors.mobile_number} />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('brand')}
                >
                    <Label htmlFor={id('brand')}>Marca</Label>
                    <Input
                        id={id('brand')}
                        value={data.brand}
                        aria-invalid={invalid('brand')}
                        onChange={(e) => setData('brand', e.target.value)}
                    />
                    <InputError message={errors.brand} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('line')}
                >
                    <Label htmlFor={id('line')}>Linea</Label>
                    <Input
                        id={id('line')}
                        value={data.line}
                        aria-invalid={invalid('line')}
                        onChange={(e) => setData('line', e.target.value)}
                    />
                    <InputError message={errors.line} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('model_year')}
                >
                    <Label htmlFor={id('model_year')}>Ano Modelo</Label>
                    <Input
                        id={id('model_year')}
                        type="number"
                        value={data.model_year}
                        aria-invalid={invalid('model_year')}
                        onChange={(e) => setData('model_year', e.target.value)}
                    />
                    <InputError message={errors.model_year} />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-3 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('type')}
                >
                    <Label htmlFor={id('type')}>Tipo</Label>
                    <Select
                        value={data.type}
                        onValueChange={(value) => setData('type', value)}
                    >
                        <SelectTrigger
                            id={id('type')}
                            aria-invalid={invalid('type')}
                        >
                            <SelectValue placeholder="Seleccionar..." />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="bus">Bus</SelectItem>
                            <SelectItem value="buseta">Buseta</SelectItem>
                            <SelectItem value="van">Van</SelectItem>
                            <SelectItem value="automobile">
                                Automovil
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError message={errors.type} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('capacity')}
                >
                    <Label htmlFor={id('capacity')}>Capacidad</Label>
                    <Input
                        id={id('capacity')}
                        type="number"
                        value={data.capacity}
                        aria-invalid={invalid('capacity')}
                        onChange={(e) => setData('capacity', e.target.value)}
                    />
                    <InputError message={errors.capacity} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('municipality_id')}
                >
                    <Label htmlFor={id('municipality_id')}>Municipio</Label>
                    <MunicipalityCombobox
                        id={id('municipality_id')}
                        municipalities={municipalities}
                        value={data.municipality_id}
                        onChange={(val) => setData('municipality_id', val)}
                        invalid={!!errors.municipality_id}
                    />
                    <InputError message={errors.municipality_id} />
                </div>
            </div>

            <div className="grid gap-4 md:grid-cols-2 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('engine_number')}
                >
                    <Label htmlFor={id('engine_number')}>Número de Motor</Label>
                    <Input
                        id={id('engine_number')}
                        value={data.engine_number}
                        aria-invalid={invalid('engine_number')}
                        onChange={(e) =>
                            setData('engine_number', e.target.value)
                        }
                    />
                    <InputError message={errors.engine_number} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('chassis_number')}
                >
                    <Label htmlFor={id('chassis_number')}>
                        Numero de Chasis
                    </Label>
                    <Input
                        id={id('chassis_number')}
                        value={data.chassis_number}
                        aria-invalid={invalid('chassis_number')}
                        onChange={(e) =>
                            setData('chassis_number', e.target.value)
                        }
                    />
                    <InputError message={errors.chassis_number} />
                </div>
            </div>

            <div className="flex items-center gap-3">
                <Switch
                    id={id('is_third_party')}
                    checked={data.is_third_party}
                    onCheckedChange={(checked) =>
                        setData('is_third_party', checked === true)
                    }
                />
                <Label htmlFor={id('is_third_party')}>
                    Vehículo de Tercero
                </Label>
                <InputError message={errors.is_third_party} />
            </div>

            {data.is_third_party && (
                <div
                    className="group/field grid gap-2 md:w-1/2"
                    data-error={invalid('third_party_id')}
                >
                    <Label htmlFor={id('third_party_id')}>
                        Tercero Propietario
                    </Label>
                    <Select
                        value={data.third_party_id}
                        onValueChange={(value) =>
                            setData('third_party_id', value)
                        }
                    >
                        <SelectTrigger
                            id={id('third_party_id')}
                            aria-invalid={invalid('third_party_id')}
                        >
                            <SelectValue placeholder="Seleccionar tercero..." />
                        </SelectTrigger>
                        <SelectContent>
                            {thirdParties.map((tp) => (
                                <SelectItem key={tp.id} value={String(tp.id)}>
                                    {thirdPartyLabel(tp)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.third_party_id} />
                </div>
            )}

            <div className="grid gap-4 md:grid-cols-3 md:grid-rows-[auto_1fr_auto]">
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('soat_due_date')}
                >
                    <Label htmlFor={id('soat_due_date')}>
                        Vencimiento SOAT
                    </Label>
                    <Input
                        id={id('soat_due_date')}
                        type="date"
                        value={data.soat_due_date}
                        aria-invalid={invalid('soat_due_date')}
                        onChange={(e) =>
                            setData('soat_due_date', e.target.value)
                        }
                    />
                    <InputError message={errors.soat_due_date} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('rtm_due_date')}
                >
                    <Label htmlFor={id('rtm_due_date')}>Vencimiento RTM</Label>
                    <Input
                        id={id('rtm_due_date')}
                        type="date"
                        value={data.rtm_due_date}
                        aria-invalid={invalid('rtm_due_date')}
                        onChange={(e) =>
                            setData('rtm_due_date', e.target.value)
                        }
                    />
                    <InputError message={errors.rtm_due_date} />
                </div>
                <div
                    className="group/field grid gap-2 md:row-span-3 md:grid-rows-subgrid"
                    data-error={invalid('operation_card_due_date')}
                >
                    <Label htmlFor={id('operation_card_due_date')}>
                        Vencimiento Tarjeta de Operacion
                    </Label>
                    <Input
                        id={id('operation_card_due_date')}
                        type="date"
                        value={data.operation_card_due_date}
                        aria-invalid={invalid('operation_card_due_date')}
                        onChange={(e) =>
                            setData('operation_card_due_date', e.target.value)
                        }
                    />
                    <InputError message={errors.operation_card_due_date} />
                </div>
            </div>

            <div
                className="group/field grid gap-2 md:w-1/3"
                data-error={invalid('status')}
            >
                <Label htmlFor={id('status')}>Estado</Label>
                <Select
                    value={data.status}
                    onValueChange={(value) => setData('status', value)}
                >
                    <SelectTrigger
                        id={id('status')}
                        aria-invalid={invalid('status')}
                    >
                        <SelectValue placeholder="Seleccionar..." />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="active">Activo</SelectItem>
                        <SelectItem value="maintenance">
                            En Mantenimiento
                        </SelectItem>
                        <SelectItem value="retired">Retirado</SelectItem>
                    </SelectContent>
                </Select>
                <InputError message={errors.status} />
            </div>
        </>
    );
}
