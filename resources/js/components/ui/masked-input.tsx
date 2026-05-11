import { MaskInput, type MaskType } from 'maska';
import { useEffect, useRef } from 'react';
import { Input } from '@/components/ui/input';

interface MaskedInputProps {
    id?: string;
    name?: string;
    /** Current masked value (with separators). The consumer keeps the
     * masked string in form data. If you need the unmasked digits, use
     * the onMaska callback option instead. */
    value: string;
    onChange: (value: string) => void;
    /**
     * Mask string or array. Examples:
     * - `'+57 ### ### ####'` — Colombian mobile
     * - `'###-###'` — short codes
     * - `['##.###.###-#', '##.###.###']` — dynamic mask, picks the matching pattern
     */
    mask: MaskType;
    /** Eager mode: prefill static characters of the mask as the user types. Default true. */
    eager?: boolean;
    /** Reversed mode: align mask to the right (useful for digit-only inputs). */
    reversed?: boolean;
    placeholder?: string;
    disabled?: boolean;
    invalid?: boolean;
    autoComplete?: string;
    className?: string;
    type?: React.HTMLInputTypeAttribute;
}

/**
 * Generic masked input on top of shadcn's Input + Maska vanilla JS. Use
 * for phone numbers, plates, serials, fixed-length codes, etc. The form
 * value is the masked string (including separators) — this is the most
 * common pattern: storage and display match. If a use case needs the
 * raw digits, swap to a small wrapper that exposes Maska's
 * `MaskaDetail.unmasked` via a separate callback.
 *
 * Why Maska: it's the smallest mask lib (~3KB gz, zero deps), supports
 * dynamic mask arrays, custom tokens, and lazy/eager modes. We mount it
 * once per input via ref and rely on its native DOM events.
 */
export default function MaskedInput({
    id,
    name,
    value,
    onChange,
    mask,
    eager = true,
    reversed,
    placeholder,
    disabled,
    invalid,
    autoComplete = 'off',
    className,
    type = 'text',
}: MaskedInputProps) {
    const ref = useRef<HTMLInputElement | null>(null);

    // Mount Maska once and update its options when `mask`/`eager`/`reversed`
    // change. Bridge Maska's onMaska callback to our React onChange.
    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        const instance = new MaskInput(el, {
            mask,
            eager,
            reversed,
            onMaska: (detail) => onChange(detail.masked),
        });
        return () => instance.destroy();
        // We intentionally re-init when the mask configuration changes so
        // dynamic masks (array form) update their pattern set.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [mask, eager, reversed]);

    return (
        <Input
            ref={ref}
            id={id}
            name={name}
            type={type}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            disabled={disabled}
            aria-invalid={invalid}
            autoComplete={autoComplete}
            className={className}
        />
    );
}
