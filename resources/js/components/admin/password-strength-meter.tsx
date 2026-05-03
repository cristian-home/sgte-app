import { cn } from '@/lib/utils';

const LABELS = ['Vacía', 'Débil', 'Aceptable', 'Buena', 'Fuerte'] as const;

export function passwordScore(password: string): number {
    if (!password) return 0;
    let score = 0;
    if (password.length >= 8) score += 1;
    if (/[A-Z]/.test(password)) score += 1;
    if (/[0-9]/.test(password)) score += 1;
    if (/[^A-Za-z0-9]/.test(password)) score += 1;
    return score;
}

interface Props {
    password: string;
    className?: string;
}

export function PasswordStrengthMeter({ password, className }: Props) {
    const score = passwordScore(password);
    const segmentColor = (i: number) => {
        if (i >= score) return 'bg-muted';
        if (score === 1) return 'bg-destructive';
        if (score === 2 || score === 3) return 'bg-amber-500';
        return 'bg-emerald-500';
    };

    return (
        <div className={cn('flex flex-col gap-1', className)}>
            <div className="flex gap-1.5">
                {[0, 1, 2, 3].map((i) => (
                    <div
                        key={i}
                        className={cn(
                            'h-1.5 flex-1 rounded-full transition-colors',
                            segmentColor(i),
                        )}
                    />
                ))}
            </div>
            <p className="text-xs text-muted-foreground">{LABELS[score]}</p>
        </div>
    );
}
