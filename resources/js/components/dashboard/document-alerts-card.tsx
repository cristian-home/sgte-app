import { Link } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export type DocumentAlert = {
    kind: 'vehicle' | 'driver' | 'contract';
    label: string;
    subject: string;
    due_date: string | null;
    days_remaining: number;
    link: string;
};

const dateFormatter = new Intl.DateTimeFormat('es-CO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

function formatDueDate(isoDate: string | null): string {
    if (!isoDate) return '—';
    return dateFormatter.format(new Date(`${isoDate}T00:00:00`));
}

function formatDaysRemaining(days: number): {
    text: string;
    tone: 'destructive' | 'warning' | 'muted';
} {
    if (days < 0) {
        return {
            text: `Vencido hace ${Math.abs(days)} día${Math.abs(days) === 1 ? '' : 's'}`,
            tone: 'destructive',
        };
    }
    if (days === 0) return { text: 'Vence hoy', tone: 'destructive' };
    if (days <= 7) {
        return {
            text: `Vence en ${days} día${days === 1 ? '' : 's'}`,
            tone: 'destructive',
        };
    }
    return { text: `Vence en ${days} días`, tone: 'warning' };
}

/**
 * Extracted from the inline block in pages/dashboard.tsx so the new
 * cockpit layout can compose it without inheriting a 100-line page
 * file. Behavior unchanged: lists `documentAlerts` returned by
 * `DashboardController::buildDocumentAlerts`.
 */
export function DocumentAlertsCard({
    alerts,
    className,
}: {
    alerts: DocumentAlert[];
    className?: string;
}) {
    return (
        <Card className={className}>
            <CardHeader>
                <div className="flex items-center gap-2">
                    <AlertTriangle
                        className="size-5 text-amber-500"
                        aria-hidden
                    />
                    <CardTitle>Alertas de documentos</CardTitle>
                </div>
                <CardDescription>
                    Documentos y contratos vencidos o por vencer.
                </CardDescription>
            </CardHeader>
            <CardContent>
                {alerts.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No hay documentos por vencer. Todo al día.
                    </p>
                ) : (
                    <ul className="divide-y divide-border">
                        {alerts.map((alert, index) => {
                            const formatted = formatDaysRemaining(
                                alert.days_remaining,
                            );
                            return (
                                <li
                                    key={`${alert.kind}-${alert.subject}-${alert.label}-${index}`}
                                >
                                    <Link
                                        href={alert.link}
                                        className="-mx-2 flex items-center justify-between gap-4 rounded-md px-2 py-3 text-sm transition-colors hover:bg-muted/50"
                                    >
                                        <div className="flex min-w-0 flex-col">
                                            <span className="font-medium">
                                                {alert.label}
                                            </span>
                                            <span className="truncate text-muted-foreground">
                                                {alert.subject}
                                            </span>
                                        </div>
                                        <div className="flex flex-col items-end gap-1 text-right">
                                            <Badge
                                                variant={
                                                    formatted.tone ===
                                                    'destructive'
                                                        ? 'destructive'
                                                        : formatted.tone ===
                                                            'warning'
                                                          ? 'secondary'
                                                          : 'outline'
                                                }
                                            >
                                                {formatted.text}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">
                                                {formatDueDate(alert.due_date)}
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}
