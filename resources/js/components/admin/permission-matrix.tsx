import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

export interface PermissionItem {
    key: string;
    label: string;
    description: string;
}

export interface PermissionGroupBlock {
    id: string;
    label: string;
    permissions: PermissionItem[];
}

interface Props {
    groups: PermissionGroupBlock[];
    assigned: Set<string>;
    onChange: (next: Set<string>) => void;
    locked?: boolean;
    expandSignal?: boolean | null;
}

export function PermissionMatrix({
    groups,
    assigned,
    onChange,
    locked = false,
    expandSignal,
}: Props) {
    const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});

    // React-docs "adjust state from a prop change" pattern: track the
    // last seen `expandSignal` and reset `collapsed` during render when
    // it differs. Avoids the setState-in-useEffect / useMemo anti-pattern
    // (the React Compiler flags both) and runs at exactly the right
    // moment — before children render — so children never observe a
    // stale collapsed map. Cheap, no extra renders.
    const [prevExpandSignal, setPrevExpandSignal] = useState<
        boolean | null | undefined
    >(expandSignal);
    if (prevExpandSignal !== expandSignal) {
        setPrevExpandSignal(expandSignal);
        if (expandSignal === true) {
            setCollapsed({});
        } else if (expandSignal === false) {
            const next: Record<string, boolean> = {};
            for (const g of groups) next[g.id] = true;
            setCollapsed(next);
        }
    }

    function toggleCollapse(id: string) {
        setCollapsed((prev) => ({ ...prev, [id]: !prev[id] }));
    }

    function togglePermission(key: string) {
        if (locked) return;
        const next = new Set(assigned);
        if (next.has(key)) next.delete(key);
        else next.add(key);
        onChange(next);
    }

    function toggleGroupAll(group: PermissionGroupBlock) {
        if (locked) return;
        const allOn = group.permissions.every((p) => assigned.has(p.key));
        const next = new Set(assigned);
        if (allOn) {
            for (const p of group.permissions) next.delete(p.key);
        } else {
            for (const p of group.permissions) next.add(p.key);
        }
        onChange(next);
    }

    return (
        <div className="flex flex-col">
            {groups.map((group) => {
                const onCount = group.permissions.filter((p) =>
                    assigned.has(p.key),
                ).length;
                const allOn = onCount === group.permissions.length;
                const isCollapsed = collapsed[group.id] === true;
                return (
                    <div
                        key={group.id}
                        className="border-b border-border last:border-b-0"
                    >
                        <button
                            type="button"
                            onClick={() => toggleCollapse(group.id)}
                            className="flex w-full items-center gap-2 px-6 py-3.5 text-left transition-colors hover:bg-muted/40"
                        >
                            <ChevronDown
                                className={cn(
                                    'size-4 text-muted-foreground transition-transform',
                                    isCollapsed && '-rotate-90',
                                )}
                            />
                            <span className="flex-1 font-medium">
                                {group.label}
                            </span>
                            <Badge variant="secondary" className="text-xs">
                                {onCount}/{group.permissions.length}
                            </Badge>
                            {!locked && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        toggleGroupAll(group);
                                    }}
                                    className="text-xs"
                                >
                                    {allOn ? 'Desmarcar todo' : 'Marcar todo'}
                                </Button>
                            )}
                        </button>
                        {!isCollapsed && (
                            <div className="flex flex-col bg-muted/15">
                                {group.permissions.map((perm) => (
                                    <div
                                        key={perm.key}
                                        className="flex items-start gap-3 px-6 py-2.5 pl-14"
                                    >
                                        <div className="flex-1">
                                            <div className="text-sm font-medium">
                                                {perm.label}
                                            </div>
                                            <div className="text-xs leading-snug text-muted-foreground">
                                                {perm.description}
                                            </div>
                                            <div className="mt-0.5 font-mono text-[10.5px] text-muted-foreground/70">
                                                {perm.key}
                                            </div>
                                        </div>
                                        <Switch
                                            checked={assigned.has(perm.key)}
                                            onCheckedChange={() =>
                                                togglePermission(perm.key)
                                            }
                                            disabled={locked}
                                            aria-label={perm.label}
                                            className={cn(
                                                locked &&
                                                    'cursor-not-allowed opacity-55',
                                            )}
                                        />
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
