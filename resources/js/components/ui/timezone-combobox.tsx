import { useMemo } from 'react';
import SearchableCombobox from '@/components/ui/searchable-combobox';

interface TimezoneComboboxProps {
    id?: string;
    name?: string;
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    className?: string;
}

interface TimezoneOption {
    id: string;
    region: string;
}

const REGION_PRIORITY: Record<string, number> = {
    America: 0,
    US: 1,
    Atlantic: 2,
    Europe: 3,
    Africa: 4,
    Asia: 5,
    Pacific: 6,
    Australia: 7,
    Indian: 8,
    Antarctica: 9,
    Etc: 10,
};

function listTimezones(): string[] {
    const supported = Intl.supportedValuesOf?.('timeZone');
    if (supported && supported.length > 0) {
        return supported;
    }
    return ['America/Bogota', 'America/New_York', 'America/Mexico_City', 'UTC'];
}

export default function TimezoneCombobox({
    id,
    name,
    value,
    onChange,
    placeholder = 'Seleccionar zona horaria…',
    disabled,
    invalid,
    className,
}: TimezoneComboboxProps) {
    const items = useMemo<TimezoneOption[]>(() => {
        return listTimezones()
            .map<TimezoneOption>((tz) => {
                const region = tz.split('/')[0] ?? 'Etc';
                return { id: tz, region };
            })
            .sort((a, b) => {
                const ra = REGION_PRIORITY[a.region] ?? 99;
                const rb = REGION_PRIORITY[b.region] ?? 99;
                if (ra !== rb) return ra - rb;
                return a.id.localeCompare(b.id);
            });
    }, []);

    return (
        <SearchableCombobox<TimezoneOption>
            id={id}
            name={name}
            items={items}
            value={value}
            onChange={onChange}
            getKey={(t) => t.id}
            getSearchText={(t) => `${t.id} ${t.id.replace(/[_/]/g, ' ')}`}
            groupBy={(t) => t.region}
            renderItem={(t) => (
                <span className="font-mono text-xs">{t.id}</span>
            )}
            renderTrigger={(t) => (
                <span className="font-mono text-xs">{t.id}</span>
            )}
            placeholder={placeholder}
            searchPlaceholder="Buscar zona…"
            emptyText="Sin coincidencias."
            disabled={disabled}
            invalid={invalid}
            className={className}
        />
    );
}
