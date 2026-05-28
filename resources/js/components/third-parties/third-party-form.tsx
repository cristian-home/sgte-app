import FieldFooter from '@/components/field-footer';
import MunicipalityCombobox, {
    type MunicipalityOption,
} from '@/components/municipality-combobox';
import { Checkbox } from '@/components/ui/checkbox';
import IdentificationInput from '@/components/ui/identification-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import NitInput from '@/components/ui/nit-input';
import PhoneInput from '@/components/ui/phone-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';

export interface DocumentTypeOption {
    id: number;
    code: string;
    name: string;
}

export interface ThirdPartyFormData {
    document_type_id: string;
    identification_number: string;
    is_natural_person: boolean;
    first_name: string;
    second_name: string;
    first_lastname: string;
    second_lastname: string;
    company_name: string;
    trade_name: string;
    municipality_id: string;
    address: string;
    phone: string;
    email: string;
    is_customer: boolean;
    is_provider: boolean;
    active: boolean;
}

interface ThirdPartyFormProps {
    data: ThirdPartyFormData;
    setData: <K extends keyof ThirdPartyFormData>(
        key: K,
        value: ThirdPartyFormData[K],
    ) => void;
    errors: Partial<Record<keyof ThirdPartyFormData, string>>;
    documentTypes: DocumentTypeOption[];
    municipalities: MunicipalityOption[];
    /**
     * When set, every field id is prefixed (e.g. `dlg_first_name`).
     * Use this when rendering inside a modal that may coexist with
     * another instance of the form on the same page.
     */
    idPrefix?: string;
}

function RequiredMarker() {
    return <span className="text-destructive"> *</span>;
}

/**
 * Shared form component for ThirdParty create + edit + create-modal.
 *
 * Preserves the flat-with-conditional layout from the original
 * Blueprint-stub create.tsx: a single `is_natural_person` toggle
 * swaps between the four name fields (Primer/Segundo Nombre +
 * Primer/Segundo Apellido) and the two legal-person name fields
 * (Razón Social + Nombre Comercial).
 *
 * The deliberate non-section layout fits this entity better than
 * sectioned-with-headings because half the visible field set
 * disappears when the toggle flips.
 */
export default function ThirdPartyForm({
    data,
    setData,
    errors,
    documentTypes,
    municipalities,
    idPrefix = '',
}: ThirdPartyFormProps) {
    const id = (name: string) => (idPrefix ? `${idPrefix}_${name}` : name);
    const selectedDocType = documentTypes.find(
        (dt) => String(dt.id) === data.document_type_id,
    );
    const docCode = selectedDocType?.code.toUpperCase() ?? '';
    const isNitDocument = docCode === 'NIT';
    const isPassportDocument = docCode === 'PA' || docCode === 'PASAPORTE';

    return (
        <div className="space-y-6">
            <div className="grid gap-x-4 gap-y-2 md:grid-cols-2 md:grid-rows-[auto_auto_auto]">
                <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label htmlFor={id('document_type_id')}>
                        Tipo de Documento
                        <RequiredMarker />
                    </Label>
                    <Select
                        value={data.document_type_id}
                        onValueChange={(value) =>
                            setData('document_type_id', value)
                        }
                    >
                        <SelectTrigger id={id('document_type_id')}>
                            <SelectValue placeholder="Seleccionar..." />
                        </SelectTrigger>
                        <SelectContent>
                            {documentTypes.map((dt) => (
                                <SelectItem key={dt.id} value={String(dt.id)}>
                                    {dt.code} - {dt.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <FieldFooter error={errors.document_type_id} />
                </div>

                <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label htmlFor={id('identification_number')}>
                        Número de Identificación
                        <RequiredMarker />
                    </Label>
                    {isNitDocument ? (
                        <NitInput
                            id={id('identification_number')}
                            value={data.identification_number}
                            onValueChange={(raw) =>
                                setData('identification_number', raw)
                            }
                            invalid={!!errors.identification_number}
                        />
                    ) : isPassportDocument ? (
                        <Input
                            id={id('identification_number')}
                            value={data.identification_number}
                            aria-invalid={!!errors.identification_number}
                            onChange={(e) =>
                                setData('identification_number', e.target.value)
                            }
                        />
                    ) : (
                        <IdentificationInput
                            id={id('identification_number')}
                            value={data.identification_number}
                            onValueChange={(raw) =>
                                setData('identification_number', raw)
                            }
                            invalid={!!errors.identification_number}
                        />
                    )}
                    <FieldFooter error={errors.identification_number} />
                </div>
            </div>

            <div className="flex items-center gap-3">
                <Switch
                    id={id('is_natural_person')}
                    checked={data.is_natural_person}
                    onCheckedChange={(checked) =>
                        setData('is_natural_person', checked === true)
                    }
                />
                <Label htmlFor={id('is_natural_person')}>
                    {data.is_natural_person
                        ? 'Persona Natural'
                        : 'Persona Jurídica'}
                </Label>
                <FieldFooter error={errors.is_natural_person} />
            </div>

            {data.is_natural_person ? (
                <div className="grid gap-x-4 gap-y-2 md:grid-cols-2 md:grid-rows-[auto_auto_auto]">
                    <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                        <Label htmlFor={id('first_name')}>
                            Primer Nombre
                            <RequiredMarker />
                        </Label>
                        <Input
                            id={id('first_name')}
                            value={data.first_name}
                            onChange={(e) =>
                                setData('first_name', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.first_name} />
                    </div>
                    <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                        <Label htmlFor={id('second_name')}>
                            Segundo Nombre
                        </Label>
                        <Input
                            id={id('second_name')}
                            value={data.second_name}
                            onChange={(e) =>
                                setData('second_name', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.second_name} />
                    </div>
                </div>
            ) : null}
            {data.is_natural_person ? (
                <div className="grid gap-x-4 gap-y-2 md:grid-cols-2 md:grid-rows-[auto_auto_auto]">
                    <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                        <Label htmlFor={id('first_lastname')}>
                            Primer Apellido
                            <RequiredMarker />
                        </Label>
                        <Input
                            id={id('first_lastname')}
                            value={data.first_lastname}
                            onChange={(e) =>
                                setData('first_lastname', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.first_lastname} />
                    </div>
                    <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                        <Label htmlFor={id('second_lastname')}>
                            Segundo Apellido
                        </Label>
                        <Input
                            id={id('second_lastname')}
                            value={data.second_lastname}
                            onChange={(e) =>
                                setData('second_lastname', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.second_lastname} />
                    </div>
                </div>
            ) : (
                <div className="grid gap-x-4 gap-y-2 md:grid-cols-2 md:grid-rows-[auto_auto_auto]">
                    <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                        <Label htmlFor={id('company_name')}>
                            Razón Social
                            <RequiredMarker />
                        </Label>
                        <Input
                            id={id('company_name')}
                            value={data.company_name}
                            onChange={(e) =>
                                setData('company_name', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.company_name} />
                    </div>
                    <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                        <Label htmlFor={id('trade_name')}>
                            Nombre Comercial
                        </Label>
                        <Input
                            id={id('trade_name')}
                            value={data.trade_name}
                            onChange={(e) =>
                                setData('trade_name', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.trade_name} />
                    </div>
                </div>
            )}

            <div className="grid gap-x-4 gap-y-2 md:grid-cols-2 md:grid-rows-[auto_auto_auto]">
                <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label htmlFor={id('municipality_id')}>Ciudad</Label>
                    <MunicipalityCombobox
                        id={id('municipality_id')}
                        municipalities={municipalities}
                        value={data.municipality_id}
                        onChange={(val) => setData('municipality_id', val)}
                        invalid={!!errors.municipality_id}
                    />
                    <FieldFooter error={errors.municipality_id} />
                </div>
                <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label htmlFor={id('address')}>
                        Dirección
                        <RequiredMarker />
                    </Label>
                    <Input
                        id={id('address')}
                        value={data.address}
                        onChange={(e) => setData('address', e.target.value)}
                    />
                    <FieldFooter error={errors.address} />
                </div>
            </div>

            <div className="grid gap-x-4 gap-y-2 md:grid-cols-2 md:grid-rows-[auto_auto_auto]">
                <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label htmlFor={id('phone')}>
                        Teléfono
                        <RequiredMarker />
                    </Label>
                    <PhoneInput
                        id={id('phone')}
                        value={data.phone}
                        onValueChange={(raw) => setData('phone', raw)}
                        invalid={!!errors.phone}
                    />
                    <FieldFooter error={errors.phone} />
                </div>
                <div className="grid gap-2 md:row-span-3 md:grid-rows-subgrid">
                    <Label htmlFor={id('email')}>
                        Correo Electrónico
                        <RequiredMarker />
                    </Label>
                    <Input
                        id={id('email')}
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                    />
                    <FieldFooter error={errors.email} />
                </div>
            </div>

            <div className="flex flex-wrap gap-6">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={id('is_customer')}
                        checked={data.is_customer}
                        onCheckedChange={(checked) =>
                            setData('is_customer', checked === true)
                        }
                    />
                    <Label htmlFor={id('is_customer')}>Cliente</Label>
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={id('is_provider')}
                        checked={data.is_provider}
                        onCheckedChange={(checked) =>
                            setData('is_provider', checked === true)
                        }
                    />
                    <Label htmlFor={id('is_provider')}>Proveedor</Label>
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={id('active')}
                        checked={data.active}
                        onCheckedChange={(checked) =>
                            setData('active', checked === true)
                        }
                    />
                    <Label htmlFor={id('active')}>Activo</Label>
                </div>
            </div>
        </div>
    );
}
