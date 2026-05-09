import { AlertTriangle, Loader2, MapPin, X } from 'lucide-react';
import {
    useCallback,
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
} from 'react';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import { Input } from '@/components/ui/input';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { dlog, dperf } from '@/lib/debug-log';
import {
    type GeocodingAccuracy,
    type GeocodingFeature,
    findFeatureByMapboxId,
    forwardGeocode,
    pickRoutableCoords,
} from '@/lib/mapbox-geocoding';
import { cn } from '@/lib/utils';

const MIN_QUERY_LENGTH = 3;
const TYPEAHEAD_DEBOUNCE_MS = 250;
const TYPEAHEAD_LIMIT = 10;

export type CoordinatesSource = 'mapbox' | 'manual' | '';

interface AddressAutocompleteProps {
    id: string;
    name: string;
    value: string;
    onChange: (text: string) => void;
    coordinates: string;
    coordinatesSource: CoordinatesSource;
    coordinatesAccuracy: string;
    onCoordinatesChange: (
        coords: string,
        source: 'mapbox' | 'manual',
        accuracy: string | null,
    ) => void;
    onCommitInFlight: (inFlight: boolean) => void;
    onOpenMapPicker: () => void;
    proximity?: {
        latitude: number;
        longitude: number;
        cityName: string | null;
    } | null;
    disabled?: boolean;
    invalid?: boolean;
    autoComplete?: string;
    placeholder?: string;
}

export default function AddressAutocomplete({
    id,
    name,
    value,
    onChange,
    coordinates,
    coordinatesSource,
    coordinatesAccuracy,
    onCoordinatesChange,
    onCommitInFlight,
    onOpenMapPicker,
    proximity,
    disabled,
    invalid,
    autoComplete = 'off',
    placeholder,
}: AddressAutocompleteProps) {
    const channel = `autocomplete:${id}`;

    const [suggestions, setSuggestions] = useState<GeocodingFeature[]>([]);
    const [open, setOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [loadingSuggest, setLoadingSuggest] = useState(false);
    const [committing, setCommitting] = useState(false);
    const [commitError, setCommitError] = useState<string | null>(null);

    const containerRef = useRef<HTMLDivElement | null>(null);
    const listboxId = useId();

    const suggestAbortRef = useRef<AbortController | null>(null);
    const commitAbortRef = useRef<AbortController | null>(null);
    const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Notify the parent every time `committing` flips so it can disable
    // the form's Save button. Wrapped in a ref to avoid leaking stale
    // closures into the timer/abort handlers.
    useEffect(() => {
        onCommitInFlight(committing);
    }, [committing, onCommitInFlight]);

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

    const targetCity = useMemo(
        () => normalizeCity(proximity?.cityName ?? ''),
        [proximity?.cityName],
    );

    const cancelPendingSuggest = useCallback(() => {
        if (suggestAbortRef.current) {
            suggestAbortRef.current.abort();
            suggestAbortRef.current = null;
        }
        if (debounceTimerRef.current) {
            clearTimeout(debounceTimerRef.current);
            debounceTimerRef.current = null;
        }
    }, []);

    const cancelPendingCommit = useCallback(() => {
        if (commitAbortRef.current) {
            commitAbortRef.current.abort();
            commitAbortRef.current = null;
        }
    }, []);

    useEffect(() => {
        return () => {
            cancelPendingSuggest();
            cancelPendingCommit();
        };
    }, [cancelPendingSuggest, cancelPendingCommit]);

    const runSuggest = useCallback(
        (text: string) => {
            const normalized = normalizeForMapbox(text);
            if (normalized.length < MIN_QUERY_LENGTH) {
                dlog(channel, 'suggest skip (below min length)', {
                    text,
                    normalized,
                    minLength: MIN_QUERY_LENGTH,
                });
                setSuggestions([]);
                setLoadingSuggest(false);
                return;
            }

            cancelPendingSuggest();
            const ac = new AbortController();
            suggestAbortRef.current = ac;
            setLoadingSuggest(true);
            dlog(channel, 'suggest fire', {
                rawText: text,
                normalized,
                proximity: proximityParam,
                targetCity: targetCity || null,
            });
            forwardGeocode(
                normalized,
                {
                    permanent: false,
                    country: 'co',
                    language: 'es',
                    types: 'address,street',
                    limit: TYPEAHEAD_LIMIT,
                    proximity: proximityParam,
                    autocomplete: true,
                },
                ac.signal,
            )
                .then((res) => {
                    if (ac.signal.aborted) return;
                    const total = res.features?.length ?? 0;
                    let features = res.features ?? [];
                    let cityFiltered = false;
                    if (targetCity) {
                        const matched = features.filter(
                            (f) =>
                                normalizeCity(
                                    f.properties.context?.place?.name ?? '',
                                ) === targetCity,
                        );
                        if (matched.length > 0) {
                            features = matched;
                            cityFiltered = true;
                        }
                    }
                    dlog(channel, 'suggest result', {
                        total,
                        kept: features.length,
                        cityFiltered,
                        targetCity: targetCity || null,
                        items: features.map((f) => ({
                            name: f.properties.name,
                            place: f.properties.context?.place?.name ?? null,
                            accuracy: f.properties.coordinates.accuracy ?? null,
                        })),
                    });
                    setSuggestions(features);
                    setActiveIndex(-1);
                    setLoadingSuggest(false);
                })
                .catch((err) => {
                    if ((err as { name?: string }).name === 'AbortError') {
                        dlog(channel, 'suggest aborted');
                        return;
                    }
                    dlog(channel, 'suggest error', {
                        error: (err as Error).message,
                    });
                    setLoadingSuggest(false);
                });
        },
        [cancelPendingSuggest, channel, proximityParam, targetCity],
    );

    const scheduleSuggest = useCallback(
        (text: string) => {
            cancelPendingSuggest();
            debounceTimerRef.current = setTimeout(
                () => runSuggest(text),
                TYPEAHEAD_DEBOUNCE_MS,
            );
        },
        [cancelPendingSuggest, runSuggest],
    );

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const next = e.target.value;
        dlog(channel, 'input change', {
            value: next,
            length: next.length,
            existingSource: coordinatesSource || null,
        });
        // Text edits never auto-clear coords — we treat the input as
        // free annotation on top of whatever pick / pin the operator
        // confirmed. The X button (visible whenever there's text) is
        // the explicit "start over" gesture; that's where coords get
        // wiped together with the address. Symmetrizes Mapbox-pick
        // and manual-pin paths and lets the operator refine the
        // canonical Mapbox text (e.g. "83 17 Calle 41A Sur") into
        // Colombian nomenclature without losing the lat/lng.
        onChange(next);
        setCommitError(null);
        setOpen(true);
        scheduleSuggest(next);
    };

    const commitPick = useCallback(
        async (suggestion: GeocodingFeature) => {
            cancelPendingSuggest();
            cancelPendingCommit();

            const ac = new AbortController();
            commitAbortRef.current = ac;
            setCommitting(true);
            setCommitError(null);
            const done = dperf(channel, 'commit', {
                pickedName: suggestion.properties.name,
                pickedMapboxId: suggestion.properties.mapbox_id,
                pickedAccuracy:
                    suggestion.properties.coordinates.accuracy ?? null,
            });

            try {
                // Re-query with the SUGGESTION's canonical name (not the
                // user's typed text). The typeahead returned `name` as
                // Mapbox knows it; using that string maximizes the chance
                // the same `mapbox_id` comes back from the permanent
                // endpoint. autocomplete=true is kept so prefix-equivalent
                // matches are also accepted (defense in depth).
                const queryForCommit = suggestion.properties.name;
                const res = await forwardGeocode(
                    queryForCommit,
                    {
                        permanent: true,
                        country: 'co',
                        language: 'es',
                        types: 'address,street',
                        limit: 10,
                        proximity: proximityParam,
                        autocomplete: true,
                    },
                    ac.signal,
                );
                if (ac.signal.aborted) return;
                // mapbox_id is stable for known features but Colombia
                // addresses are often interpolated (the secondary number
                // is rarely a real datapoint), and the temp/permanent
                // tiers can produce different mapbox_ids for the same
                // query. Prefer mapbox_id match when possible; fall back
                // to the top result of the same query, which Mapbox
                // canonicalizes consistently.
                const byId = findFeatureByMapboxId(
                    res,
                    suggestion.properties.mapbox_id,
                );
                const matched = byId ?? res.features?.[0] ?? null;
                if (!matched) {
                    done({
                        outcome: 'no-match',
                        respFeatures: res.features?.length ?? 0,
                    });
                    setCommitError(
                        'No se pudo confirmar la dirección. Intenta de nuevo o marca el punto en el mapa.',
                    );
                    return;
                }
                const { lat, lng } = pickRoutableCoords(matched);
                const accuracy =
                    matched.properties.coordinates.accuracy ?? null;
                const usedRoutable =
                    !!matched.properties.coordinates.routable_points?.find(
                        (p) => p.name === 'default',
                    );
                done({
                    outcome: byId ? 'matched-by-id' : 'fallback-features[0]',
                    matchedName: matched.properties.name,
                    matchedMapboxId: matched.properties.mapbox_id,
                    accuracy,
                    usedRoutable,
                    coords: `${lat.toFixed(7)},${lng.toFixed(7)}`,
                });
                // Replace the input text with Mapbox's canonical name.
                // Without this the saved address can be a fragment of
                // what the operator actually picked ("universidad" when
                // they chose "Universidad Externado") and the conductor
                // would read the fragment, not the place. Anglo-format
                // street addresses are still the API's call ("83 17
                // Calle 41A Sur"); the operator can refine the text
                // afterward without losing coords (text edits no longer
                // auto-clear them).
                onChange(matched.properties.name);
                onCoordinatesChange(
                    `${lat.toFixed(7)},${lng.toFixed(7)}`,
                    'mapbox',
                    accuracy,
                );
                setOpen(false);
                setSuggestions([]);
                setActiveIndex(-1);
            } catch (err) {
                if ((err as { name?: string }).name === 'AbortError') {
                    done({ outcome: 'aborted' });
                    return;
                }
                done({
                    outcome: 'error',
                    error: (err as Error).message,
                });
                setCommitError(
                    'No se pudo confirmar la dirección. Intenta de nuevo o marca el punto en el mapa.',
                );
            } finally {
                if (commitAbortRef.current === ac) {
                    commitAbortRef.current = null;
                }
                setCommitting(false);
            }
        },
        [
            cancelPendingCommit,
            cancelPendingSuggest,
            channel,
            onChange,
            onCoordinatesChange,
            proximityParam,
        ],
    );

    const handleSelect = (suggestion: GeocodingFeature) => {
        dlog(channel, 'suggestion pick', {
            name: suggestion.properties.name,
            mapboxId: suggestion.properties.mapbox_id,
            accuracy: suggestion.properties.coordinates.accuracy ?? null,
            place: suggestion.properties.context?.place?.name ?? null,
        });
        void commitPick(suggestion);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (!open || suggestions.length === 0) {
            if (
                e.key === 'ArrowDown' &&
                value.trim().length >= MIN_QUERY_LENGTH
            ) {
                setOpen(true);
                runSuggest(value);
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
        (loadingSuggest || suggestions.length > 0) &&
        normalizeForMapbox(value).length >= MIN_QUERY_LENGTH;

    const hasText = value.trim().length > 0;
    const needsConfirmation = hasText && !coordinates;
    const showClearButton = hasText && !committing && !disabled;

    const handleClear = useCallback(() => {
        dlog(channel, 'cleared by X');
        cancelPendingSuggest();
        cancelPendingCommit();
        setSuggestions([]);
        setActiveIndex(-1);
        setOpen(false);
        setCommitError(null);
        onChange('');
        onCoordinatesChange('', 'mapbox', null);
    }, [
        cancelPendingCommit,
        cancelPendingSuggest,
        channel,
        onChange,
        onCoordinatesChange,
    ]);

    return (
        <div ref={containerRef} className="space-y-1">
            <ButtonGroup className="w-full">
                <div className="relative flex-1">
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
                        // Stop password managers from injecting their
                        // toolbar icons into the address field.
                        data-1p-ignore="true"
                        data-lpignore="true"
                        spellCheck={false}
                        value={value}
                        onChange={handleInputChange}
                        onKeyDown={handleKeyDown}
                        onFocus={() => {
                            if (suggestions.length > 0) setOpen(true);
                        }}
                        disabled={disabled || committing}
                        aria-invalid={invalid}
                        placeholder={placeholder}
                        className="pr-9"
                    />
                    {committing && (
                        <Loader2 className="absolute top-1/2 right-2 size-4 -translate-y-1/2 animate-spin text-muted-foreground" />
                    )}
                    {showClearButton && (
                        <button
                            type="button"
                            onClick={handleClear}
                            aria-label="Limpiar dirección"
                            title="Limpiar dirección"
                            className="absolute top-1/2 right-2 inline-flex size-5 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <X className="size-3.5" />
                        </button>
                    )}
                    {showDropdown && (
                        <div
                            className={cn(
                                'absolute top-full right-0 left-0 z-50 mt-1 max-h-72 overflow-auto',
                                'rounded-md border bg-popover shadow-md',
                            )}
                        >
                            <ul
                                id={listboxId}
                                role="listbox"
                                className="py-1 text-sm"
                            >
                                {loadingSuggest && suggestions.length === 0 && (
                                    <li className="flex items-center gap-2 px-3 py-2 text-muted-foreground">
                                        <Loader2 className="size-3 animate-spin" />
                                        Buscando direcciones…
                                    </li>
                                )}
                                {suggestions.map((s, i) => (
                                    <li
                                        key={s.properties.mapbox_id}
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
                                                {s.properties.name}
                                            </span>
                                            {s.properties.place_formatted && (
                                                <span className="truncate text-xs text-muted-foreground">
                                                    {
                                                        s.properties
                                                            .place_formatted
                                                    }
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
                <TooltipProvider delayDuration={300}>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => {
                                    dlog(channel, 'map picker open requested', {
                                        currentValue: value,
                                        currentCoords: coordinates || null,
                                        currentSource:
                                            coordinatesSource || null,
                                    });
                                    cancelPendingSuggest();
                                    setOpen(false);
                                    onOpenMapPicker();
                                }}
                                disabled={disabled || committing}
                                aria-label="Marcar en mapa"
                                className="shrink-0"
                            >
                                <MapPin className="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Marcar en mapa</TooltipContent>
                    </Tooltip>
                </TooltipProvider>
            </ButtonGroup>

            {commitError && (
                <p className="flex items-start gap-1 text-xs text-red-600 dark:text-red-400">
                    <AlertTriangle className="mt-0.5 size-3 shrink-0" />
                    <span>{commitError}</span>
                </p>
            )}

            {needsConfirmation && !commitError && !committing && (
                <p className="flex items-start gap-1 text-xs text-amber-700 dark:text-amber-400">
                    <AlertTriangle className="mt-0.5 size-3 shrink-0" />
                    <span>
                        Selecciona una sugerencia o marca el punto en el mapa
                        para confirmar la ubicación.
                    </span>
                </p>
            )}

            {coordinates && (
                <CoordinatesBadge
                    coordinates={coordinates}
                    source={coordinatesSource}
                    accuracy={coordinatesAccuracy}
                />
            )}
        </div>
    );
}

function CoordinatesBadge({
    coordinates,
    source,
    accuracy,
}: {
    coordinates: string;
    source: CoordinatesSource;
    accuracy: string;
}) {
    const tone = badgeTone(source, accuracy as GeocodingAccuracy | '');
    return (
        <p
            className={cn(
                'flex items-center gap-1 text-xs',
                tone === 'green'
                    ? 'text-emerald-700 dark:text-emerald-400'
                    : tone === 'yellow'
                      ? 'text-amber-700 dark:text-amber-400'
                      : 'text-muted-foreground',
            )}
        >
            <MapPin className="size-3" />
            <span>
                Coordenadas: <code>{coordinates}</code>
                {source && ` · ${sourceLabel(source)}`}
                {accuracy && source === 'mapbox' && ` · ${accuracy}`}
            </span>
        </p>
    );
}

function sourceLabel(source: CoordinatesSource): string {
    if (source === 'mapbox') return 'Mapbox';
    if (source === 'manual') return 'pin manual';
    return '';
}

function badgeTone(
    source: CoordinatesSource,
    accuracy: GeocodingAccuracy | '',
): 'green' | 'yellow' | 'gray' {
    if (source === 'mapbox') {
        if (
            accuracy === 'rooftop' ||
            accuracy === 'parcel' ||
            accuracy === 'point'
        ) {
            return 'green';
        }
        return 'yellow';
    }
    return 'gray';
}

/**
 * Strip Colombian address punctuation (`#`, `-`) and collapse whitespace
 * before sending the query to Mapbox. The visible input value is never
 * rewritten — this only normalizes the outgoing query string.
 */
function normalizeForMapbox(input: string): string {
    return input.replace(/[#-]/g, ' ').replace(/\s+/g, ' ').trim();
}

const COMBINING_MARKS = /[̀-ͯ]/g;
const COLOMBIA_CAPITAL_SUFFIX = /,?\s*d\.?\s*c\.?\s*$/i;

/**
 * Normalize a Colombian municipality name for case- and accent-insensitive
 * comparison against Mapbox's place context. Strips diacritics, the
 * `, D.C.` / `D.C.` suffix that DANE uses for Bogotá, and trims/lowercases.
 */
function normalizeCity(name: string | null | undefined): string {
    if (!name) return '';
    return name
        .normalize('NFD')
        .replace(COMBINING_MARKS, '')
        .toLowerCase()
        .replace(COLOMBIA_CAPITAL_SUFFIX, '')
        .trim();
}
