import { useEffect, useState } from 'react';
import { instantToPxFromEpoch, type Ymd } from '../utils/coordinates';

interface Props {
    epoch: Ymd;
    operationTz: string;
    /**
     * Vertical offset from the canvas top — typically the header strip
     * height, so the red line only spans the body rows and doesn't
     * cross the day banner + hour-labels strip (those aren't timeline
     * data and the line through them is visual noise).
     */
    topOffsetPx?: number;
    /**
     * Pixel offset to add to the computed left, accounting for the
     * sticky sidebar that pushes the timeline canvas to the right of
     * the absolute-positioning parent. Defaults to 0 so the component
     * still works in single-day contexts.
     */
    leftOffsetPx?: number;
}

/**
 * Vertical red line at the current instant, updated every minute. Lives
 * on the continuous multi-day canvas, so it stays accurate even when
 * the operator scrolls to days that are NOT today.
 */
export default function NowIndicator({
    epoch,
    operationTz,
    topOffsetPx = 0,
    leftOffsetPx = 0,
}: Props) {
    const [tick, setTick] = useState(0);
    useEffect(() => {
        const id = window.setInterval(() => setTick((t) => t + 1), 60_000);
        return () => window.clearInterval(id);
    }, []);

    // Recompute every tick. Using `useState` + interval is enough since
    // the position changes once per minute.
    void tick;
    const leftPx =
        leftOffsetPx +
        instantToPxFromEpoch(new Date().toISOString(), operationTz, epoch);

    // Use top + bottom (no height) so the line auto-stretches to the
    // canvas bottom regardless of how many borders the rows add. The
    // previous fixed-height computation undercounted ~1px per row.
    return (
        <div
            aria-hidden
            className="pointer-events-none absolute z-10 w-px bg-red-500"
            style={{
                left: `${leftPx}px`,
                top: `${topOffsetPx}px`,
                bottom: 0,
            }}
        />
    );
}
