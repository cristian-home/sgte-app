import { NumericFormat } from 'react-number-format';
import { Input } from '@/components/ui/input';

interface IdentificationInputProps {
    id?: string;
    name?: string;
    /**
     * Raw digit string the form posts — no separators. Example: "1234567890".
     */
    value: string;
    /** Receives the raw digit string (no formatting). */
    onValueChange: (raw: string) => void;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    autoComplete?: string;
    className?: string;
    /** Max digit count. Defaults to 12 (room for cédula, CC, CE). */
    maxLength?: number;
}

/**
 * Numeric identification input (cédula, CC, CE, etc.) — Colombian
 * convention with `.` as thousand separator. The form value is the raw
 * digit string; the display string is grouped while the user types.
 */
export default function IdentificationInput({
    id,
    name,
    value,
    onValueChange,
    placeholder,
    disabled,
    invalid,
    autoComplete = 'off',
    className,
    maxLength = 12,
}: IdentificationInputProps) {
    return (
        <NumericFormat
            id={id}
            name={name}
            customInput={Input}
            value={value === '' ? '' : value}
            onValueChange={({ value: raw }) => onValueChange(raw)}
            thousandSeparator="."
            decimalSeparator=","
            decimalScale={0}
            allowNegative={false}
            allowLeadingZeros
            placeholder={placeholder}
            disabled={disabled}
            aria-invalid={invalid}
            autoComplete={autoComplete}
            inputMode="numeric"
            isAllowed={({ value: raw }) => raw.length <= maxLength}
            className={className}
        />
    );
}
