import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    subjectTypeLabel,
    type ActivityRow,
    type SubjectTypeOption,
} from '@/types/audit-log';

interface AuditLogDetailSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    activity: ActivityRow | null;
    subjectTypes: SubjectTypeOption[];
}

const dateTimeFormatter = new Intl.DateTimeFormat('es-CO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
});

function formatTimestamp(iso: string | null): string {
    if (!iso) return '—';
    return dateTimeFormatter.format(new Date(iso));
}

function formatValue(value: unknown): string {
    if (value === null || value === undefined) return '—';
    if (typeof value === 'string') return value === '' ? '""' : value;
    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }
    try {
        return JSON.stringify(value);
    } catch {
        return String(value);
    }
}

function RESERVED_PROPERTY_KEYS() {
    return new Set([
        'attributes',
        'old',
        'justification',
        'edited_on_executed_day',
    ]);
}

export default function AuditLogDetailSheet({
    open,
    onOpenChange,
    activity,
    subjectTypes,
}: AuditLogDetailSheetProps) {
    const justification =
        typeof activity?.properties?.justification === 'string'
            ? activity.properties.justification
            : null;

    const editedOnExecutedDay =
        activity?.properties?.edited_on_executed_day === true;

    const attributes = activity?.attributes ?? {};
    const oldAttributes = activity?.old_attributes ?? {};
    const diffKeys = Array.from(
        new Set([
            ...Object.keys(oldAttributes ?? {}),
            ...Object.keys(attributes ?? {}),
        ]),
    );
    const hasDiff = diffKeys.length > 0;

    const reservedKeys = RESERVED_PROPERTY_KEYS();
    const extraPropertyEntries = Object.entries(
        activity?.properties ?? {},
    ).filter(([key]) => !reservedKeys.has(key));
    const hasExtraProperties = extraPropertyEntries.length > 0;

    const subjectLabel = activity
        ? subjectTypeLabel(activity.subject_type, subjectTypes)
        : '—';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="flex w-full flex-col gap-4 overflow-y-auto sm:max-w-xl"
            >
                <SheetHeader>
                    <SheetTitle className="flex flex-wrap items-center gap-2 text-base">
                        {activity?.causer?.name ?? 'Sistema'}
                        {activity?.event && (
                            <Badge variant="outline">{activity.event}</Badge>
                        )}
                        {editedOnExecutedDay && (
                            <Badge className="bg-amber-500/15 text-amber-700 hover:bg-amber-500/20 dark:text-amber-400">
                                Día ejecutado
                            </Badge>
                        )}
                    </SheetTitle>
                    <SheetDescription className="space-y-1 text-xs text-muted-foreground">
                        {activity?.causer?.email && (
                            <div>{activity.causer.email}</div>
                        )}
                        <div className="font-mono">
                            {formatTimestamp(activity?.created_at ?? null)}
                        </div>
                        {activity?.subject_type && (
                            <div>
                                {subjectLabel}
                                {activity.subject_id
                                    ? ` #${activity.subject_id}`
                                    : ''}
                            </div>
                        )}
                    </SheetDescription>
                </SheetHeader>

                {!activity ? (
                    <div className="px-4 text-sm text-muted-foreground">
                        Sin actividad seleccionada.
                    </div>
                ) : (
                    <div className="flex flex-col gap-4 px-4 pb-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm">
                                    Descripción
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-sm text-muted-foreground">
                                {activity.description || '—'}
                            </CardContent>
                        </Card>

                        {justification !== null && (
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm">
                                        Justificación
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <blockquote className="border-l-4 border-amber-500 pl-4 text-sm italic">
                                        {justification}
                                    </blockquote>
                                </CardContent>
                            </Card>
                        )}

                        {hasDiff && (
                            <Card>
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm">
                                        Cambios
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    <div className="grid grid-cols-[1fr_1fr_1fr] gap-2 border-b pb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        <div>Campo</div>
                                        <div>Antes</div>
                                        <div>Después</div>
                                    </div>
                                    {diffKeys.map((key) => (
                                        <div
                                            key={key}
                                            className="grid grid-cols-[1fr_1fr_1fr] items-start gap-2 text-xs"
                                        >
                                            <div className="font-mono font-medium">
                                                {key}
                                            </div>
                                            <div className="font-mono break-all text-muted-foreground">
                                                {formatValue(
                                                    (
                                                        oldAttributes as Record<
                                                            string,
                                                            unknown
                                                        >
                                                    )[key],
                                                )}
                                            </div>
                                            <div className="font-mono break-all">
                                                {formatValue(
                                                    (
                                                        attributes as Record<
                                                            string,
                                                            unknown
                                                        >
                                                    )[key],
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {hasExtraProperties && (
                            <details className="rounded-md border p-3 text-xs">
                                <summary className="cursor-pointer font-medium">
                                    Propiedades adicionales
                                </summary>
                                <pre className="mt-2 overflow-x-auto whitespace-pre-wrap">
                                    {JSON.stringify(
                                        Object.fromEntries(
                                            extraPropertyEntries,
                                        ),
                                        null,
                                        2,
                                    )}
                                </pre>
                            </details>
                        )}
                    </div>
                )}

                <SheetFooter>
                    <SheetClose asChild>
                        <Button variant="outline">Cerrar</Button>
                    </SheetClose>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
