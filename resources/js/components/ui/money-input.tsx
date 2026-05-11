import { NumericFormat } from 'react-number-format';
import { Input } from '@/components/ui/input';

export type MoneyCurrency = 'COP';

interface MoneyInputProps {
    id?: string;
    name?: string;
    /**
     * Raw integer string the form posts to the backend — no separators,
     * no prefix. Example: "1234567". Empty string when the field is blank.
     */
    value: string;
    /**
     * Receives the raw integer string (no formatting). Use this directly
     * with Inertia's `setData`.
     */
    onValueChange: (raw: string) => void;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    autoComplete?: string;
    /** Defaults to 'COP'. Reserved for future locales. */
    currency?: MoneyCurrency;
    className?: string;
    /** Visual prefix override. Defaults to '$ ' for COP. */
    prefix?: string;
}

/**
 * Money input pre-configured for Colombian pesos (COP): dot as thousand
 * separator, comma as decimal (unused — `decimalScale={0}`), `$` prefix,
 * non-negative. The display string is formatted while the user types but
 * the form data stays a plain integer string for backend compatibility.
 *
 * Wraps react-number-format's NumericFormat over shadcn's Input via
 * `customInput`. Performance-friendly: NumericFormat handles formatting
 * locally without controlled re-render loops on each keystroke.
 */
export default function MoneyInput({
    id,
    name,
    value,
    onValueChange,
    placeholder,
    disabled,
    invalid,
    autoComplete = 'off',
    currency: _currency = 'COP',
    className,
    prefix = '$ ',
}: MoneyInputProps) {
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
            prefix={prefix}
            placeholder={placeholder ?? '$ 0'}
            disabled={disabled}
            aria-invalid={invalid}
            autoComplete={autoComplete}
            inputMode="numeric"
            className={className}
        />
    );
}
