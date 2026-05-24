import { useEffect, useMemo, useRef, useState } from 'react';
import { ServiceMiniCard } from '@/components/driver/service-mini-card';
import { cn } from '@/lib/utils';
import type { Service } from '@/types';

const HOURS = Array.from({ length: 24 }, (_, h) => h);

function hourLabel(h: number): string {
    return `${String(h).padStart(2, '0')}h`;
}

function nowLabel(now: { hour: number; minute: number }): string {
    return `${String(now.hour).padStart(2, '0')}:${String(now.minute).padStart(2, '0')}`;
}

function plannedHourInTz(at: string, tz: string): number {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: tz,
        hour: '2-digit',
        hour12: false,
    }).formatToParts(new Date(at));
    const value = parts.find((p) => p.type === 'hour')?.value ?? '0';
    const parsed = Number(value);
    return Number.isFinite(parsed) ? Math.max(0, Math.min(23, parsed)) : 0;
}

function currentHourMinuteInTz(tz: string): { hour: number; minute: number } {
    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: tz,
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(new Date());
    const hour = Number(parts.find((p) => p.type === 'hour')?.value ?? '0');
    const minute = Number(parts.find((p) => p.type === 'minute')?.value ?? '0');
    return { hour, minute };
}

interface DayTimelineProps {
    services: Service[];
    isToday: boolean;
    operationTz: string;
    onSelectService: (id: number) => void;
}

export function DayTimeline({
    services,
    isToday,
    operationTz,
    onSelectService,
}: DayTimelineProps) {
    const containerRef = useRef<HTMLDivElement | null>(null);
    const nowRef = useRef<HTMLDivElement | null>(null);
    const firstServiceRef = useRef<HTMLDivElement | null>(null);
    const [now, setNow] = useState(() => currentHourMinuteInTz(operationTz));

    // Tick the "AHORA" line every 60s when viewing today.
    useEffect(() => {
        if (!isToday) {
            return;
        }
        const id = setInterval(() => {
            setNow(currentHourMinuteInTz(operationTz));
        }, 60_000);
        return () => clearInterval(id);
    }, [isToday, operationTz]);

    // Group services by their planned-start hour bucket in the operation TZ.
    const buckets = useMemo(() => {
        const map = new Map<number, Service[]>();
        for (const service of services) {
            const tz = service.timezone || operationTz;
            const hour = plannedHourInTz(service.planned_start_at, tz);
            const list = map.get(hour) ?? [];
            list.push(service);
            map.set(hour, list);
        }
        return map;
    }, [services, operationTz]);

    // First hour that has at least one service — used to anchor the
    // auto-scroll target when not viewing today.
    const firstServiceHour = useMemo(() => {
        for (const h of HOURS) {
            if ((buckets.get(h) ?? []).length > 0) {
                return h;
            }
        }
        return null;
    }, [buckets]);

    // Auto-scroll: on today's view, center the AHORA line. Otherwise scroll
    // to the first service of the day if any exist.
    useEffect(() => {
        const target = isToday ? nowRef.current : firstServiceRef.current;
        if (target) {
            target.scrollIntoView({ block: 'center', behavior: 'auto' });
        }
    }, [isToday]);

    return (
        <div
            ref={containerRef}
            className="relative min-h-0 flex-1 overflow-y-auto rounded-md border bg-card"
        >
            <div className="relative">
                {HOURS.map((h) => {
                    const items = buckets.get(h) ?? [];
                    const isEmpty = items.length === 0;
                    const isFirstService = h === firstServiceHour;
                    const isCurrentHour = isToday && now.hour === h;
                    return (
                        <div
                            key={h}
                            className={cn(
                                'relative flex gap-4 border-b border-border/40 last:border-b-0',
                                // Shrink empty rows so a sparse day reads as a
                                // tight ladder; rows with services stay tall
                                // enough to host one or more mini cards.
                                isEmpty ? 'min-h-8' : 'min-h-24',
                            )}
                        >
                            <div
                                className={cn(
                                    'sticky left-0 w-14 shrink-0 pt-2 pl-3 font-mono text-xs',
                                    isCurrentHour
                                        ? 'text-destructive'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {isCurrentHour ? (
                                    <div className="flex flex-col leading-tight">
                                        <span className="text-[9px] font-semibold tracking-wider uppercase">
                                            Ahora
                                        </span>
                                        <span>{nowLabel(now)}</span>
                                    </div>
                                ) : (
                                    hourLabel(h)
                                )}
                            </div>
                            <div
                                ref={
                                    isFirstService ? firstServiceRef : undefined
                                }
                                className="relative z-10 flex min-w-0 flex-1 flex-col gap-2 py-2 pr-3"
                            >
                                {items.map((service) => (
                                    <ServiceMiniCard
                                        key={service.id}
                                        service={service}
                                        onOpen={() =>
                                            onSelectService(service.id)
                                        }
                                    />
                                ))}
                            </div>

                            {/* AHORA line — positioned by minute fraction
                                inside the current hour row. The label
                                ("Ahora HH:MM") lives in the rail cell so
                                the line itself only needs to draw the
                                hairline through the cards area, starting
                                after the rail (left-14). The cards column
                                is z-elevated, so the line stays behind any
                                card that lands at the same row. */}
                            {isCurrentHour && (
                                <div
                                    ref={nowRef}
                                    className="pointer-events-none absolute right-0 left-14 flex items-center"
                                    style={{
                                        top: `${(now.minute / 60) * 100}%`,
                                    }}
                                    aria-hidden="true"
                                >
                                    <div className="h-px flex-1 bg-destructive/60" />
                                </div>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
