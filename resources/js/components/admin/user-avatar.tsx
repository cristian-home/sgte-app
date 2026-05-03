import { cn } from '@/lib/utils';

const HUES = [40, 180, 260, 140, 20, 300, 220, 90];

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((s) => s[0]?.toUpperCase() ?? '')
        .join('');
}

function colorForId(id: number): string {
    const hue = HUES[id % HUES.length];
    return `oklch(0.75 0.09 ${hue})`;
}

interface Props {
    id: number;
    name: string;
    size?: number;
    className?: string;
}

export function UserAvatar({ id, name, size = 32, className }: Props) {
    const dim = `${size}px`;
    return (
        <span
            aria-hidden
            className={cn(
                'inline-flex items-center justify-center rounded-full font-semibold text-white select-none',
                className,
            )}
            style={{
                width: dim,
                height: dim,
                backgroundColor: colorForId(id),
                fontSize: `${Math.max(10, Math.round(size * 0.42))}px`,
            }}
        >
            {initials(name) || '?'}
        </span>
    );
}
