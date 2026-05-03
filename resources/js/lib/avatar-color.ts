const HUES = [40, 180, 260, 140, 20, 300, 220, 90];

export function avatarColorForId(id: number): string {
    const hue = HUES[id % HUES.length];
    return `oklch(0.75 0.09 ${hue})`;
}
