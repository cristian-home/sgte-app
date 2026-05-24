import { Head } from '@inertiajs/react';
import { AlertTriangle, Truck, User, Users } from 'lucide-react';
import { Can } from '@/components/can';
import {
    DocumentAlertsCard,
    type DocumentAlert,
} from '@/components/dashboard/document-alerts-card';
import {
    KpiCard,
    type KpiSparklinePoint,
} from '@/components/dashboard/kpi-card';
import {
    LiveVehiclesMap,
    type DashboardActiveVehicle,
} from '@/components/dashboard/live-vehicles-map';
import {
    OverdueInvoicesCard,
    type DashboardOverdueInvoice,
} from '@/components/dashboard/overdue-invoices-card';
import { QuickActionsBar } from '@/components/dashboard/quick-actions-bar';
import {
    TodayServicesGantt,
    type DashboardTodayService,
} from '@/components/dashboard/today-services-gantt';
import { Permission } from '@/enums/Permission';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

type KpiBucket = {
    total: number;
    [subKey: string]: number;
};

type DashboardProps = {
    kpis: {
        vehicles: KpiBucket;
        drivers: KpiBucket;
        services_today: KpiBucket;
        incidents_today: KpiBucket;
    };
    trends: {
        services: KpiSparklinePoint[];
        incidents: KpiSparklinePoint[];
    };
    todayServices: DashboardTodayService[];
    activeVehicles: DashboardActiveVehicle[];
    overdueInvoices: DashboardOverdueInvoice[];
    documentAlerts: DocumentAlert[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Panel', href: dashboard().url },
];

export default function Dashboard({
    kpis,
    trends,
    todayServices,
    activeVehicles,
    overdueInvoices,
    documentAlerts,
}: DashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Panel" />
            <div className="flex flex-col gap-4 p-4">
                <QuickActionsBar />

                <div className="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <KpiCard
                        icon={Truck}
                        title="Vehículos"
                        total={kpis.vehicles.total}
                        breakdown={[
                            {
                                label: 'Activos',
                                value: kpis.vehicles.active,
                                tone: 'success',
                            },
                            {
                                label: 'Mantenimiento',
                                value: kpis.vehicles.maintenance,
                                tone: 'warning',
                            },
                        ]}
                    />
                    <KpiCard
                        icon={User}
                        title="Conductores"
                        total={kpis.drivers.total}
                        breakdown={[
                            {
                                label: 'Activos',
                                value: kpis.drivers.active,
                                tone: 'success',
                            },
                            {
                                label: 'Inactivos',
                                value: kpis.drivers.inactive,
                                tone: 'muted',
                            },
                        ]}
                    />
                    <KpiCard
                        icon={Users}
                        title="Servicios hoy"
                        total={kpis.services_today.total}
                        breakdown={[
                            {
                                label: 'Abiertos',
                                value: kpis.services_today.open,
                                tone: 'warning',
                            },
                            {
                                label: 'Cerrados',
                                value: kpis.services_today.closed,
                                tone: 'success',
                            },
                        ]}
                        sparkline={trends.services}
                    />
                    <KpiCard
                        icon={AlertTriangle}
                        title="Incidentes hoy"
                        total={kpis.incidents_today.total}
                        breakdown={[
                            {
                                label: 'Facturables',
                                value: kpis.incidents_today.affects_billing,
                                tone: 'destructive',
                            },
                            {
                                label: 'De conductor',
                                value: kpis.incidents_today.from_driver,
                                tone: 'muted',
                            },
                        ]}
                        sparkline={trends.incidents}
                    />
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Can permission={Permission.VIEW_SERVICES}>
                        <TodayServicesGantt
                            services={todayServices}
                            className="lg:col-span-2"
                        />
                    </Can>
                    <Can permission={Permission.VIEW_VEHICLE_LOCATIONS}>
                        <LiveVehiclesMap vehicles={activeVehicles} />
                    </Can>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <DocumentAlertsCard alerts={documentAlerts} />
                    <Can permission={Permission.VIEW_INVOICES}>
                        <OverdueInvoicesCard invoices={overdueInvoices} />
                    </Can>
                </div>
            </div>
        </AppLayout>
    );
}
