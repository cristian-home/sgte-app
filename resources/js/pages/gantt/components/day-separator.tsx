import type { Ymd } from '../utils/coordinates';

interface Props {
    date: Ymd;
    /** Left position (px) of this day from the timeline origin. */
    leftPx: number;
    /** Width (px) of one day = PX_PER_DAY. */
    widthPx: number;
    /** Highlight when this segment represents "today" in operation TZ. */
    isToday?: boolean;
}

const dayFormatter = new Intl.DateTimeFormat('es-CO', {
    weekday: 'short',
    day: '2-digit',
    month: 'short',
});

/**
 * Banner-style label rendered once per day at the top of the timeline.
 * Sticky-top so it stays visible during vertical scroll of the vehicle
 * rows. Positioned absolutely against the timeline container so it
 * moves naturally with horizontal scroll.
 */
export default function DaySeparator({
    date,
    leftPx,
    widthPx,
    isToday = false,
}: Props) {
    const [y, m, d] = date.split('-').map(Number);
    // Build a stable noon-UTC date so the formatter never picks the
    // wrong calendar day across DST boundaries.
    const label = dayFormatter.format(new Date(Date.UTC(y, m - 1, d, 12)));

    return (
        <div
            className={
                'absolute top-0 z-20 flex h-6 items-center border-x border-border px-2 text-xs font-medium ' +
                (isToday
                    ? 'bg-primary/10 text-primary'
                    : 'bg-muted/80 text-muted-foreground')
            }
            style={{ left: `${leftPx}px`, width: `${widthPx}px` }}
        >
            <span className="truncate capitalize">{label}</span>
        </div>
    );
}
