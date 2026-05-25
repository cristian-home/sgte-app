import { Check, RotateCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface Props {
    visible: boolean;
    changeCount: number;
    addedCount: number;
    removedCount: number;
    onDiscard: () => void;
    onSave: () => void;
    saving?: boolean;
}

export function SaveBar({
    visible,
    changeCount,
    addedCount,
    removedCount,
    onDiscard,
    onSave,
    saving,
}: Props) {
    if (!visible) return null;
    return (
        <div
            role="region"
            aria-label="Cambios sin guardar"
            className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card shadow-[0_-4px_12px_rgba(0,0,0,0.06)]"
        >
            <div className="mx-auto flex max-w-[1400px] items-center justify-between gap-4 px-6 py-3">
                <div className="flex items-center gap-2.5">
                    <span className="size-2 rounded-full bg-amber-500" />
                    <div className="flex flex-col">
                        <span className="text-sm font-medium">
                            {changeCount} cambio
                            {changeCount === 1 ? '' : 's'} sin guardar
                        </span>
                        <span className="text-xs text-muted-foreground">
                            +{addedCount} −{removedCount}
                        </span>
                    </div>
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onDiscard}
                        disabled={saving}
                    >
                        <RotateCcw className="size-4" /> Descartar
                    </Button>
                    <Button size="sm" onClick={onSave} disabled={saving}>
                        <Check className="size-4" /> Guardar cambios
                    </Button>
                </div>
            </div>
        </div>
    );
}
