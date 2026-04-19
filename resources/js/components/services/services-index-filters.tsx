import {
    default as MunicipalityCombobox,
} from '@/components/municipality-combobox';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    default as VehicleCombobox,
} from '@/components/vehicles/vehicle-combobox';
import type {
    MunicipalityOption} from '@/components/municipality-combobox';
import type {
    VehicleOption} from '@/components/vehicles/vehicle-combobox';

export interface ContractFilterOption {
    id: number;
    contract_number: string;
    third_party_id: number;
    third_party?: {
        id: number;
        company_name: string | null;
        first_name: string | null;
        first_lastname: string | null;
        is_natural_person: boolean;
    } | null;
}

export interface DriverFilterOption {
    id: number;
    first_name: string;
    first_lastname: string;
    identification_number: string;
}

interface ServicesIndexFiltersProps {
    contracts: ContractFilterOption[];
    drivers: DriverFilterOption[];
    vehicles: VehicleOption[];
    municipalities: MunicipalityOption[];
    contractId: string;
    driverId: string;
    vehicleId: string;
    originMunicipalityId: string;
    destinationMunicipalityId: string;
    dateFrom: string;
    dateTo: string;
    onFilterChange: (name: string, value: string) => void;
    onApplyPreset: (preset: 'today' | 'this_week' | 'open_only') => void;
    onClearAll: () => void;
}

function contractLabel(contract: ContractFilterOption): string {
    const tp = contract.third_party;
    const customer = tp
        ? tp.is_natural_person
            ? [tp.first_name, tp.first_lastname].filter(Boolean).join(' ')
            : (tp.company_name ?? '')
        : '';
    return customer
        ? `${contract.contract_number} · ${customer}`
        : contract.contract_number;
}

function driverLabel(driver: DriverFilterOption): string {
    const name = [driver.first_name, driver.first_lastname]
        .filter(Boolean)
        .join(' ');
    return `${name} (${driver.identification_number})`;
}

export default function ServicesIndexFilters({
    contracts,
    drivers,
    vehicles,
    municipalities,
    contractId,
    driverId,
    vehicleId,
    originMunicipalityId,
    destinationMunicipalityId,
    dateFrom,
    dateTo,
    onFilterChange,
    onApplyPreset,
    onClearAll,
}: ServicesIndexFiltersProps) {
    const hasAny =
        contractId ||
        driverId ||
        vehicleId ||
        originMunicipalityId ||
        destinationMunicipalityId ||
        dateFrom ||
        dateTo;

    return (
        <div className="space-y-3 rounded-md border bg-muted/20 p-4">
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-medium">Presets:</span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onApplyPreset('today')}
                >
                    Hoy
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onApplyPreset('this_week')}
                >
                    Esta semana
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => onApplyPreset('open_only')}
                >
                    Pendientes de cerrar
                </Button>
                {hasAny && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onClearAll}
                    >
                        Limpiar filtros avanzados
                    </Button>
                )}
            </div>

            <div className="grid gap-3 md:grid-cols-3">
                <div className="space-y-1">
                    <Label htmlFor="filter-contract">Contrato</Label>
                    <Select
                        value={contractId || 'all'}
                        onValueChange={(value) =>
                            onFilterChange(
                                'contract_id',
                                value === 'all' ? '' : value,
                            )
                        }
                    >
                        <SelectTrigger id="filter-contract">
                            <SelectValue placeholder="Todos los contratos" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Todos los contratos
                            </SelectItem>
                            {contracts.map((c) => (
                                <SelectItem key={c.id} value={String(c.id)}>
                                    {contractLabel(c)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1">
                    <Label htmlFor="filter-driver">Conductor</Label>
                    <Select
                        value={driverId || 'all'}
                        onValueChange={(value) =>
                            onFilterChange(
                                'driver_id',
                                value === 'all' ? '' : value,
                            )
                        }
                    >
                        <SelectTrigger id="filter-driver">
                            <SelectValue placeholder="Todos los conductores" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Todos los conductores
                            </SelectItem>
                            {drivers.map((d) => (
                                <SelectItem key={d.id} value={String(d.id)}>
                                    {driverLabel(d)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="space-y-1">
                    <Label htmlFor="filter-vehicle">Vehículo</Label>
                    <VehicleCombobox
                        id="filter-vehicle"
                        vehicles={vehicles}
                        value={vehicleId || null}
                        onChange={(value) =>
                            onFilterChange(
                                'vehicle_id',
                                value === null ? '' : String(value),
                            )
                        }
                        placeholder="Todos los vehículos"
                    />
                </div>

                <div className="space-y-1">
                    <Label htmlFor="filter-origin">Municipio Origen</Label>
                    <MunicipalityCombobox
                        id="filter-origin"
                        municipalities={municipalities}
                        value={originMunicipalityId || null}
                        onChange={(value) =>
                            onFilterChange('origin_municipality_id', value)
                        }
                        placeholder="Todos los orígenes"
                    />
                </div>

                <div className="space-y-1">
                    <Label htmlFor="filter-destination">
                        Municipio Destino
                    </Label>
                    <MunicipalityCombobox
                        id="filter-destination"
                        municipalities={municipalities}
                        value={destinationMunicipalityId || null}
                        onChange={(value) =>
                            onFilterChange('destination_municipality_id', value)
                        }
                        placeholder="Todos los destinos"
                    />
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <div className="space-y-1">
                        <Label htmlFor="filter-date-from">Desde</Label>
                        <Input
                            id="filter-date-from"
                            type="date"
                            value={dateFrom}
                            onChange={(e) =>
                                onFilterChange('date_from', e.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor="filter-date-to">Hasta</Label>
                        <Input
                            id="filter-date-to"
                            type="date"
                            value={dateTo}
                            onChange={(e) =>
                                onFilterChange('date_to', e.target.value)
                            }
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}
