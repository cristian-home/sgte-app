import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, FileText, Truck, User, Users } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type KpiBucket = {
    total: number;
    [subKey: string]: number;
};

type DocumentAlert = {
    kind: 'vehicle' | 'driver';
    label: string;
    subject: string;
    due_date: string | null;
    days_remaining: number;
    link: string;
};

type DashboardProps = {
    kpis: {
        vehicles: KpiBucket;
        drivers: KpiBucket;
        services_today: KpiBucket;
        invoices_pending: KpiBucket;
    };
    documentAlerts: DocumentAlert[];
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Panel',
        href: dashboard().url,
    },
];

const dateFormatter = new Intl.DateTimeFormat('es-CO', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

function formatDueDate(isoDate: string | null): string {
    if (!isoDate) {
        return '—';
    }
    return dateFormatter.format(new Date(`${isoDate}T00:00:00`));
}

function formatDaysRemaining(days: number): { text: string; tone: 'destructive' | 'warning' | 'muted' } {
    if (days < 0) {
        return { text: `Vencido hace ${Math.abs(days)} día${Math.abs(days) === 1 ? '' : 's'}`, tone: 'destructive' };
    }
    if (days === 0) {
        return { text: 'Vence hoy', tone: 'destructive' };
    }
    if (days <= 7) {
        return { text: `Vence en ${days} día${days === 1 ? '' : 's'}`, tone: 'destructive' };
    }
    return { text: `Vence en ${days} días`, tone: 'warning' };
}

export default function Dashboard({ kpis, documentAlerts }: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Panel" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        icon={Truck}
                        title="Vehículos"
                        total={kpis.vehicles.total}
                        breakdown={[
                            { label: 'Activos', value: kpis.vehicles.active, tone: 'success' },
                            { label: 'Mantenimiento', value: kpis.vehicles.maintenance, tone: 'warning' },
                        ]}
                    />
                    <KpiCard
                        icon={User}
                        title="Conductores"
                        total={kpis.drivers.total}
                        breakdown={[
                            { label: 'Activos', value: kpis.drivers.active, tone: 'success' },
                            { label: 'Inactivos', value: kpis.drivers.inactive, tone: 'muted' },
                        ]}
                    />
                    <KpiCard
                        icon={Users}
                        title="Servicios hoy"
                        total={kpis.services_today.total}
                        breakdown={[
                            { label: 'Abiertos', value: kpis.services_today.open, tone: 'warning' },
                            { label: 'Cerrados', value: kpis.services_today.closed, tone: 'success' },
                        ]}
                    />
                    <KpiCard
                        icon={FileText}
                        title="Facturas pendientes"
                        total={kpis.invoices_pending.total}
                        breakdown={[
                            { label: 'Vencidas', value: kpis.invoices_pending.overdue, tone: 'destructive' },
                        ]}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <AlertTriangle className="h-5 w-5 text-amber-500" aria-hidden />
                            <CardTitle>Alertas de documentos</CardTitle>
                        </div>
                        <CardDescription>
                            Documentos vencidos o por vencer en los próximos 30 días.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {documentAlerts.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No hay documentos por vencer. Todo al día.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {documentAlerts.map((alert, index) => {
                                    const formatted = formatDaysRemaining(alert.days_remaining);
                                    return (
                                        <li
                                            key={`${alert.kind}-${alert.subject}-${alert.label}-${index}`}
                                        >
                                            <Link
                                                href={alert.link}
                                                className="-mx-2 flex items-center justify-between gap-4 rounded-md px-2 py-3 text-sm transition-colors hover:bg-muted/50"
                                            >
                                                <div className="flex min-w-0 flex-col">
                                                    <span className="font-medium">{alert.label}</span>
                                                    <span className="truncate text-muted-foreground">
                                                        {alert.subject}
                                                    </span>
                                                </div>
                                                <div className="flex flex-col items-end gap-1 text-right">
                                                    <Badge
                                                        variant={
                                                            formatted.tone === 'destructive'
                                                                ? 'destructive'
                                                                : formatted.tone === 'warning'
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
            </div>
        </AppLayout>
    );
}

type KpiTone = 'success' | 'warning' | 'destructive' | 'muted';

function KpiCard({
    icon: Icon,
    title,
    total,
    breakdown,
}: {
    icon: typeof Truck;
    title: string;
    total: number;
    breakdown: { label: string; value: number; tone: KpiTone }[];
}) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-center justify-between">
                    <CardTitle className="text-sm font-medium text-muted-foreground">
                        {title}
                    </CardTitle>
                    <Icon className="h-5 w-5 text-muted-foreground" aria-hidden />
                </div>
            </CardHeader>
            <CardContent>
                <div className="text-3xl font-semibold tabular-nums">{total}</div>
                <div className="mt-2 flex flex-wrap gap-2">
                    {breakdown.map((item) => (
                        <Badge
                            key={item.label}
                            variant={
                                item.tone === 'destructive'
                                    ? 'destructive'
                                    : item.tone === 'success'
                                      ? 'default'
                                      : item.tone === 'warning'
                                        ? 'secondary'
                                        : 'outline'
                            }
                        >
                            {item.label}: {item.value}
                        </Badge>
                    ))}
                </div>
            </CardContent>
        </Card>
    );
}
