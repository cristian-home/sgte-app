import { PatternFormat } from 'react-number-format';
import { Input } from '@/components/ui/input';

interface PhoneInputProps {
    id?: string;
    name?: string;
    /**
     * Raw digits the form posts to the backend — no spaces, no formatting.
     * Example: "3001234567". Empty string when blank.
     */
    value: string;
    /** Receives the raw digit string (no formatting). Use with Inertia setData. */
    onValueChange: (raw: string) => void;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    autoComplete?: string;
    className?: string;
}

/**
 * Colombian mobile phone input. Displays as `### ### ####` (10 digits).
 * Stores a raw 10-digit string in form data. Legacy values that contain
 * extra characters (spaces, `+57`, dashes) are stripped on first paint by
 * removeFormatting so the user keeps editing a clean value.
 */
export default function PhoneInput({
    id,
    name,
    value,
    onValueChange,
    placeholder = '300 123 4567',
    disabled,
    invalid,
    autoComplete = 'tel',
    className,
}: PhoneInputProps) {
    return (
        <PatternFormat
            id={id}
            name={name}
            customInput={Input}
            format="### ### ####"
            mask=""
            value={value}
            onValueChange={({ value: raw }) => onValueChange(raw)}
            placeholder={placeholder}
            disabled={disabled}
            aria-invalid={invalid}
            autoComplete={autoComplete}
            inputMode="tel"
            type="tel"
            className={className}
        />
    );
}
