import { useEffect, useRef } from 'react';
import FieldFooter from '@/components/field-footer';
import MunicipalityCombobox, {
    type MunicipalityOption,
} from '@/components/municipality-combobox';
import { Checkbox } from '@/components/ui/checkbox';
import IdentificationInput from '@/components/ui/identification-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PhoneInput from '@/components/ui/phone-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

export interface CatalogOption {
    id: number;
    code: string;
    name: string;
}

export interface DocumentTypeOption {
    id: number;
    code: string;
    name: string;
}

export interface DriverFormData {
    document_type_id: string;
    identification_number: string;
    first_name: string;
    second_name: string;
    first_lastname: string;
    second_lastname: string;
    municipality_id: string;
    address: string;
    phone: string;
    email: string;
    license_category: string;
    license_due_date: string;
    eps_id: string;
    pension_fund_id: string;
    severance_fund_id: string;
    has_social_security: boolean;
    active: boolean;
    // Solo aplica en modo create: si el operador marca "crear cuenta de
    // acceso", el backend crea un User vinculado y envía el invite.
    create_account?: boolean;
    account_email?: string;
}

interface DriverFormProps {
    data: DriverFormData;
    setData: <K extends keyof DriverFormData>(
        key: K,
        value: DriverFormData[K],
    ) => void;
    errors: Partial<Record<keyof DriverFormData, string>>;
    municipalities: MunicipalityOption[];
    documentTypes: DocumentTypeOption[];
    eps: CatalogOption[];
    pensionFunds: CatalogOption[];
    severanceFunds: CatalogOption[];
    /**
     * When set, every field id is prefixed (e.g. `dlg_first_name`).
     * Use this when rendering inside a modal that coexists with
     * another instance of the form on the same page.
     */
    idPrefix?: string;
    /**
     * En modo 'create' se muestra la sección "Cuenta de acceso al sistema".
     * En 'edit' (default) se oculta — la creación de cuenta a posteriori va
     * por la pantalla de detalle del conductor.
     */
    mode?: 'create' | 'edit';
}

function RequiredMarker() {
    return <span className="text-destructive"> *</span>;
}

export default function DriverForm({
    data,
    setData,
    errors,
    municipalities,
    documentTypes,
    eps,
    pensionFunds,
    severanceFunds,
    idPrefix = '',
    mode = 'edit',
}: DriverFormProps) {
    const id = (name: string) => (idPrefix ? `${idPrefix}_${name}` : name);
    const invalid = (field: keyof DriverFormData) =>
        errors[field] ? true : undefined;

    // Pre-rellenar account_email con email del Driver mientras el operador
    // escribe ese campo y aún no haya tocado manualmente account_email.
    const accountEmailEditedRef = useRef(false);
    useEffect(() => {
        if (mode !== 'create' || !data.create_account) {
            return;
        }
        if (
            !accountEmailEditedRef.current &&
            data.email &&
            !data.account_email
        ) {
            setData('account_email', data.email);
        }
    }, [mode, data.create_account, data.email, data.account_email, setData]);

    return (
        <div className="space-y-8">
            {/* Section 1: Identificación */}
            <section className="space-y-4">
                <h3 className="text-base font-semibold">Identificación</h3>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
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
                            <SelectTrigger
                                id={id('document_type_id')}
                                aria-invalid={invalid('document_type_id')}
                            >
                                <SelectValue placeholder="Selecciona un tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                {documentTypes.map((dt) => (
                                    <SelectItem
                                        key={dt.id}
                                        value={String(dt.id)}
                                    >
                                        {dt.code} — {dt.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldFooter error={errors.document_type_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('identification_number')}>
                            Número de Identificación
                            <RequiredMarker />
                        </Label>
                        <IdentificationInput
                            id={id('identification_number')}
                            value={data.identification_number}
                            onValueChange={(raw) =>
                                setData('identification_number', raw)
                            }
                            invalid={invalid('identification_number')}
                        />
                        <FieldFooter error={errors.identification_number} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('first_name')}>
                            Primer Nombre
                            <RequiredMarker />
                        </Label>
                        <Input
                            id={id('first_name')}
                            value={data.first_name}
                            aria-invalid={invalid('first_name')}
                            onChange={(e) =>
                                setData('first_name', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.first_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('second_name')}>
                            Segundo Nombre
                        </Label>
                        <Input
                            id={id('second_name')}
                            value={data.second_name}
                            aria-invalid={invalid('second_name')}
                            onChange={(e) =>
                                setData('second_name', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.second_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('first_lastname')}>
                            Primer Apellido
                            <RequiredMarker />
                        </Label>
                        <Input
                            id={id('first_lastname')}
                            value={data.first_lastname}
                            aria-invalid={invalid('first_lastname')}
                            onChange={(e) =>
                                setData('first_lastname', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.first_lastname} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('second_lastname')}>
                            Segundo Apellido
                        </Label>
                        <Input
                            id={id('second_lastname')}
                            value={data.second_lastname}
                            aria-invalid={invalid('second_lastname')}
                            onChange={(e) =>
                                setData('second_lastname', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.second_lastname} />
                    </div>
                </div>
            </section>

            {/* Section 2: Datos de Contacto */}
            <section className="space-y-4">
                <h3 className="text-base font-semibold">Datos de Contacto</h3>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor={id('municipality_id')}>Municipio</Label>
                        <MunicipalityCombobox
                            id={id('municipality_id')}
                            municipalities={municipalities}
                            value={data.municipality_id || null}
                            onChange={(value) =>
                                setData('municipality_id', value)
                            }
                            invalid={invalid('municipality_id')}
                            placeholder="Selecciona un municipio"
                        />
                        <FieldFooter error={errors.municipality_id} />
                    </div>

                    <div className="grid gap-2 md:col-span-2">
                        <Label htmlFor={id('address')}>
                            Dirección
                            <RequiredMarker />
                        </Label>
                        <Input
                            id={id('address')}
                            value={data.address}
                            aria-invalid={invalid('address')}
                            onChange={(e) => setData('address', e.target.value)}
                        />
                        <FieldFooter error={errors.address} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('phone')}>
                            Teléfono
                            <RequiredMarker />
                        </Label>
                        <PhoneInput
                            id={id('phone')}
                            value={data.phone}
                            onValueChange={(raw) => setData('phone', raw)}
                            invalid={invalid('phone')}
                        />
                        <FieldFooter error={errors.phone} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('email')}>
                            Correo Electrónico
                            <RequiredMarker />
                        </Label>
                        <Input
                            id={id('email')}
                            type="email"
                            value={data.email}
                            aria-invalid={invalid('email')}
                            onChange={(e) => setData('email', e.target.value)}
                        />
                        <FieldFooter error={errors.email} />
                    </div>
                </div>
            </section>

            {/* Section 3: Licencia */}
            <section className="space-y-4">
                <h3 className="text-base font-semibold">Licencia</h3>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor={id('license_category')}>
                            Categoría
                            <RequiredMarker />
                        </Label>
                        <ToggleGroup
                            id={id('license_category')}
                            type="single"
                            variant="outline"
                            value={data.license_category}
                            onValueChange={(value) => {
                                if (!value) return;
                                setData('license_category', value);
                            }}
                            aria-invalid={invalid('license_category')}
                            className="w-full justify-stretch"
                        >
                            <ToggleGroupItem value="C1" className="flex-1">
                                C1
                            </ToggleGroupItem>
                            <ToggleGroupItem value="C2" className="flex-1">
                                C2
                            </ToggleGroupItem>
                            <ToggleGroupItem value="C3" className="flex-1">
                                C3
                            </ToggleGroupItem>
                        </ToggleGroup>
                        <FieldFooter error={errors.license_category} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('license_due_date')}>
                            Fecha de Vencimiento
                            <RequiredMarker />
                        </Label>
                        <Input
                            id={id('license_due_date')}
                            type="date"
                            value={data.license_due_date}
                            aria-invalid={invalid('license_due_date')}
                            onChange={(e) =>
                                setData('license_due_date', e.target.value)
                            }
                        />
                        <FieldFooter error={errors.license_due_date} />
                    </div>
                </div>
            </section>

            {/* Section 4: Afiliaciones */}
            <section className="space-y-4">
                <h3 className="text-base font-semibold">Afiliaciones</h3>
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor={id('eps_id')}>
                            EPS
                            <RequiredMarker />
                        </Label>
                        <Select
                            value={data.eps_id}
                            onValueChange={(value) => setData('eps_id', value)}
                        >
                            <SelectTrigger
                                id={id('eps_id')}
                                aria-invalid={invalid('eps_id')}
                            >
                                <SelectValue placeholder="Selecciona una EPS" />
                            </SelectTrigger>
                            <SelectContent>
                                {eps.map((entry) => (
                                    <SelectItem
                                        key={entry.id}
                                        value={String(entry.id)}
                                    >
                                        {entry.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldFooter error={errors.eps_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('pension_fund_id')}>
                            Fondo de Pensiones
                            <RequiredMarker />
                        </Label>
                        <Select
                            value={data.pension_fund_id}
                            onValueChange={(value) =>
                                setData('pension_fund_id', value)
                            }
                        >
                            <SelectTrigger
                                id={id('pension_fund_id')}
                                aria-invalid={invalid('pension_fund_id')}
                            >
                                <SelectValue placeholder="Selecciona un fondo" />
                            </SelectTrigger>
                            <SelectContent>
                                {pensionFunds.map((entry) => (
                                    <SelectItem
                                        key={entry.id}
                                        value={String(entry.id)}
                                    >
                                        {entry.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldFooter error={errors.pension_fund_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor={id('severance_fund_id')}>
                            Fondo de Cesantías
                            <RequiredMarker />
                        </Label>
                        <Select
                            value={data.severance_fund_id}
                            onValueChange={(value) =>
                                setData('severance_fund_id', value)
                            }
                        >
                            <SelectTrigger
                                id={id('severance_fund_id')}
                                aria-invalid={invalid('severance_fund_id')}
                            >
                                <SelectValue placeholder="Selecciona un fondo" />
                            </SelectTrigger>
                            <SelectContent>
                                {severanceFunds.map((entry) => (
                                    <SelectItem
                                        key={entry.id}
                                        value={String(entry.id)}
                                    >
                                        {entry.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldFooter error={errors.severance_fund_id} />
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <Switch
                        id={id('has_social_security')}
                        checked={data.has_social_security}
                        onCheckedChange={(checked) =>
                            setData('has_social_security', checked)
                        }
                    />
                    <Label htmlFor={id('has_social_security')}>
                        Seguridad social activa
                    </Label>
                </div>
                <FieldFooter error={errors.has_social_security} />
            </section>

            {/* Section 5: Estado */}
            <section className="space-y-4">
                <h3 className="text-base font-semibold">Estado</h3>
                <div className="flex items-center gap-3">
                    <Switch
                        id={id('active')}
                        checked={data.active}
                        onCheckedChange={(checked) =>
                            setData('active', checked)
                        }
                    />
                    <Label htmlFor={id('active')}>Conductor activo</Label>
                </div>
                <FieldFooter error={errors.active} />
            </section>

            {mode === 'create' && (
                <section className="space-y-4">
                    <h3 className="text-base font-semibold">
                        Cuenta de acceso al sistema
                    </h3>
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id={id('create_account')}
                            checked={data.create_account ?? false}
                            onCheckedChange={(checked) => {
                                setData('create_account', checked === true);
                                if (checked !== true) {
                                    accountEmailEditedRef.current = false;
                                    setData('account_email', '');
                                }
                            }}
                        />
                        <div className="grid gap-1">
                            <Label
                                htmlFor={id('create_account')}
                                className="cursor-pointer"
                            >
                                Crear cuenta de acceso para este conductor
                            </Label>
                            <p className="text-sm text-muted-foreground">
                                Se enviará un enlace al correo para que el
                                conductor configure su contraseña. El enlace
                                expira en 60 minutos.
                            </p>
                        </div>
                    </div>

                    {data.create_account && (
                        <div className="grid gap-2 md:max-w-md">
                            <Label htmlFor={id('account_email')}>
                                Correo de acceso
                                <RequiredMarker />
                            </Label>
                            <Input
                                id={id('account_email')}
                                type="email"
                                value={data.account_email ?? ''}
                                aria-invalid={invalid('account_email')}
                                onChange={(e) => {
                                    accountEmailEditedRef.current = true;
                                    setData('account_email', e.target.value);
                                }}
                            />
                            <FieldFooter error={errors.account_email} />
                        </div>
                    )}
                </section>
            )}
        </div>
    );
}
