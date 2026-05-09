import {
    useAddressAutofillCore,
    useSearchSession,
} from '@mapbox/search-js-react';
import { Loader2, MapPin } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from 'react';
import { Input } from '@/components/ui/input';
import {
    formatColombianAddress,
    normalizeForMapbox,
} from '@/lib/colombian-address';
import { MAPBOX_TOKEN } from '@/lib/mapbox';
import { cn } from '@/lib/utils';
import type {
    AddressAutofillSuggestion,
    AddressAutofillSuggestionResponse,
    AddressAutofillRetrieveResponse,
} from '@mapbox/search-js-core';

interface AddressAutocompleteProps {
    id: string;
    name: string;
    value: string;
    onChange: (text: string) => void;
    coordinates: string;
    onCoordinatesChange: (coords: string) => void;
    proximity?: { latitude: number; longitude: number } | null;
    disabled?: boolean;
    invalid?: boolean;
    autoComplete?: string;
    placeholder?: string;
}

const MIN_QUERY_LENGTH = 3;

export default function AddressAutocomplete({
    id,
    name,
    value,
    onChange,
    coordinates,
    onCoordinatesChange,
    proximity,
    disabled,
    invalid,
    autoComplete = 'street-address',
    placeholder,
}: AddressAutocompleteProps) {
    const autofill = useAddressAutofillCore({
        accessToken: MAPBOX_TOKEN,
        country: 'co',
        language: 'es',
        limit: 6,
    });
    const session = useSearchSession(autofill);

    const [suggestions, setSuggestions] = useState<AddressAutofillSuggestion[]>(
        [],
    );
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [loading, setLoading] = useState(false);

    const containerRef = useRef<HTMLDivElement | null>(null);
    const listboxId = useId();

    useEffect(() => {
        if (!session) return;

        const onSuggest = (res: AddressAutofillSuggestionResponse) => {
            setSuggestions(res?.suggestions ?? []);
            setActiveIndex(-1);
            setLoading(false);
        };
        const onRetrieve = (res: AddressAutofillRetrieveResponse) => {
            const feature = res?.features?.[0];
            const coords = feature?.geometry?.coordinates as
                | [number, number]
                | undefined;
            if (coords) {
                const [lng, lat] = coords;
                onCoordinatesChange(`${lat.toFixed(7)},${lng.toFixed(7)}`);
            }
            const props = feature?.properties as
                | AddressAutofillSuggestion
                | undefined;
            if (props) {
                onChange(formatColombianAddress(props));
            }
            setOpen(false);
            setSuggestions([]);
            setActiveIndex(-1);
        };
        const onError = () => {
            setLoading(false);
        };

        session.addEventListener('suggest', onSuggest);
        session.addEventListener('retrieve', onRetrieve);
        session.addEventListener('suggesterror', onError);
        return () => {
            session.removeEventListener('suggest', onSuggest);
            session.removeEventListener('retrieve', onRetrieve);
            session.removeEventListener('suggesterror', onError);
        };
    }, [session, onChange, onCoordinatesChange]);

    const proximityParam = useMemo<string>(() => {
        if (
            proximity &&
            Number.isFinite(proximity.latitude) &&
            Number.isFinite(proximity.longitude)
        ) {
            return `${proximity.longitude},${proximity.latitude}`;
        }
        return 'ip';
    }, [proximity]);

    const triggerSuggest = useCallback(
        (text: string) => {
            const normalized = normalizeForMapbox(text);
            if (normalized.length < MIN_QUERY_LENGTH) {
                setSuggestions([]);
                setLoading(false);
                return;
            }
            setLoading(true);
            session?.suggest(normalized, { proximity: proximityParam });
        },
        [session, proximityParam],
    );

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const next = e.target.value;
        onChange(next);
        if (coordinates) {
            onCoordinatesChange('');
        }
        setOpen(true);
        triggerSuggest(next);
    };

    const handleSelect = (suggestion: AddressAutofillSuggestion) => {
        session?.retrieve(suggestion);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!open || suggestions.length === 0) {
            if (
                e.key === 'ArrowDown' &&
                value.trim().length >= MIN_QUERY_LENGTH
            ) {
                setOpen(true);
                triggerSuggest(value);
                e.preventDefault();
            }
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveIndex((i) => (i + 1) % suggestions.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIndex((i) => (i <= 0 ? suggestions.length - 1 : i - 1));
        } else if (e.key === 'Enter') {
            if (activeIndex >= 0 && activeIndex < suggestions.length) {
                e.preventDefault();
                handleSelect(suggestions[activeIndex]);
            }
        } else if (e.key === 'Escape') {
            setOpen(false);
        }
    };

    useEffect(() => {
        if (!open) return;
        const onClickOutside = (e: MouseEvent) => {
            if (
                containerRef.current &&
                !containerRef.current.contains(e.target as Node)
            ) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, [open]);

    const showDropdown =
        open &&
        !disabled &&
        (loading || suggestions.length > 0) &&
        normalizeForMapbox(value).length >= MIN_QUERY_LENGTH;

    return (
        <div ref={containerRef} className="relative">
            <Input
                id={id}
                name={name}
                type="text"
                role="combobox"
                aria-expanded={showDropdown}
                aria-controls={listboxId}
                aria-autocomplete="list"
                aria-activedescendant={
                    activeIndex >= 0
                        ? `${listboxId}-option-${activeIndex}`
                        : undefined
                }
                autoComplete={autoComplete}
                value={value}
                onChange={handleInputChange}
                onKeyDown={handleKeyDown}
                onFocus={() => {
                    if (suggestions.length > 0) setOpen(true);
                }}
                disabled={disabled}
                aria-invalid={invalid}
                placeholder={placeholder}
            />
            {showDropdown && (
                <div
                    className={cn(
                        'absolute top-full right-0 left-0 z-50 mt-1 max-h-72 overflow-auto',
                        'rounded-md border bg-popover shadow-md',
                    )}
                >
                    <ul id={listboxId} role="listbox" className="py-1 text-sm">
                        {loading && suggestions.length === 0 && (
                            <li className="flex items-center gap-2 px-3 py-2 text-muted-foreground">
                                <Loader2 className="size-3 animate-spin" />
                                Buscando direcciones…
                            </li>
                        )}
                        {suggestions.map((s, i) => (
                            <li
                                key={s.mapbox_id ?? i}
                                id={`${listboxId}-option-${i}`}
                                role="option"
                                aria-selected={i === activeIndex}
                                className={cn(
                                    'flex cursor-pointer items-start gap-2 px-3 py-2',
                                    i === activeIndex
                                        ? 'bg-accent text-accent-foreground'
                                        : 'hover:bg-accent/60',
                                )}
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    handleSelect(s);
                                }}
                                onMouseEnter={() => setActiveIndex(i)}
                            >
                                <MapPin className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <div className="flex min-w-0 flex-col">
                                    <span className="truncate font-medium">
                                        {s.feature_name}
                                    </span>
                                    {s.description && (
                                        <span className="truncate text-xs text-muted-foreground">
                                            {s.description}
                                        </span>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                    <div className="border-t px-3 py-1.5 text-right text-[10px] text-muted-foreground">
                        Powered by{' '}
                        <a
                            href="https://www.mapbox.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="underline"
                        >
                            Mapbox
                        </a>
                    </div>
                </div>
            )}
        </div>
    );
}
