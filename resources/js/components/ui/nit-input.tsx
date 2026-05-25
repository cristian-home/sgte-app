import { PatternFormat } from 'react-number-format';
import { Input } from '@/components/ui/input';

interface NitInputProps {
    id?: string;
    name?: string;
    /**
     * Raw digit string (no separators, no dash). Example: "9001234567"
     * (9 digits + 1 check digit). Empty string when blank.
     */
    value: string;
    /** Receives the raw digit string. */
    onValueChange: (raw: string) => void;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    autoComplete?: string;
    className?: string;
}

/**
 * Colombian NIT input — display `###.###.###-#` (last digit is the
 * verification digit). The form value is the raw digit string with no
 * separators, up to 10 digits (9 + check). PatternFormat shows the
 * separators progressively as the user types.
 */
export default function NitInput({
    id,
    name,
    value,
    onValueChange,
    placeholder = '900.123.456-7',
    disabled,
    invalid,
    autoComplete = 'off',
    className,
}: NitInputProps) {
    return (
        <PatternFormat
            id={id}
            name={name}
            customInput={Input}
            format="###.###.###-#"
            mask=""
            value={value}
            onValueChange={({ value: raw }) => onValueChange(raw)}
            placeholder={placeholder}
            disabled={disabled}
            aria-invalid={invalid}
            autoComplete={autoComplete}
            inputMode="numeric"
            className={className}
        />
    );
}
