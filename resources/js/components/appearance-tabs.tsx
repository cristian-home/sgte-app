import { Monitor, Moon, Sun } from 'lucide-react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';
import type { LucideIcon } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';

interface AppearanceToggleTabProps {
    className?: string;
}

export default function AppearanceToggleTab({
    className = '',
}: AppearanceToggleTabProps) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Claro' },
        { value: 'dark', icon: Moon, label: 'Oscuro' },
        { value: 'system', icon: Monitor, label: 'Sistema' },
    ];

    return (
        <ToggleGroup
            type="single"
            variant="outline"
            value={appearance}
            onValueChange={(value) => {
                if (!value) return;
                updateAppearance(value as Appearance);
            }}
            className={cn('inline-flex', className)}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <ToggleGroupItem key={value} value={value} className="px-3">
                    <Icon aria-hidden className="size-4" />
                    <span className="text-sm">{label}</span>
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
