import { Link } from '@inertiajs/react';
import {
    BookOpen,
    Calendar,
    FileText,
    Folder,
    LayoutGrid,
    MapPin,
    Receipt,
    Settings,
    Wrench,
} from 'lucide-react';
import { index as contractsIndex } from '@/actions/App/Http/Controllers/ContractController';
import { index as dayStatusesIndex } from '@/actions/App/Http/Controllers/DayStatusController';
import { index as documentTypesIndex } from '@/actions/App/Http/Controllers/DocumentTypeController';
import { index as driversIndex } from '@/actions/App/Http/Controllers/DriverController';
import { index as epsIndex } from '@/actions/App/Http/Controllers/EpsController';
import { index as fuecsIndex } from '@/actions/App/Http/Controllers/FuecController';
import { index as invoicesIndex } from '@/actions/App/Http/Controllers/InvoiceController';
import { index as pensionFundsIndex } from '@/actions/App/Http/Controllers/PensionFundController';
import { index as servicesIndex } from '@/actions/App/Http/Controllers/ServiceController';
import { index as serviceIncidentsIndex } from '@/actions/App/Http/Controllers/ServiceIncidentController';
import { index as severanceFundsIndex } from '@/actions/App/Http/Controllers/SeveranceFundController';
import { index as thirdPartiesIndex } from '@/actions/App/Http/Controllers/ThirdPartyController';
import { index as vehiclesIndex } from '@/actions/App/Http/Controllers/VehicleController';
import { index as vehicleLocationsIndex } from '@/actions/App/Http/Controllers/VehicleLocationController';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Permission } from '@/enums/Permission';
import { dashboard } from '@/routes';
import AppLogo from './app-logo';
import type { NavGroup, NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Panel',
        href: dashboard(),
        icon: LayoutGrid,
        permission: Permission.VIEW_DASHBOARD,
    },
];

const navGroups: NavGroup[] = [
    {
        label: 'Producción',
        icon: Calendar,
        permission: Permission.VIEW_SERVICES,
        items: [
            {
                title: 'Servicios',
                href: servicesIndex(),
                permission: Permission.VIEW_SERVICES,
            },
            {
                title: 'Estado del Día',
                href: dayStatusesIndex(),
                permission: Permission.VIEW_DAY_SUMMARY,
            },
            {
                title: 'Novedades',
                href: serviceIncidentsIndex(),
                permission: Permission.VIEW_INCIDENTS,
            },
        ],
    },
    {
        label: 'Administración',
        icon: Wrench,
        permission: Permission.VIEW_VEHICLES,
        items: [
            {
                title: 'Vehículos',
                href: vehiclesIndex(),
                permission: Permission.VIEW_VEHICLES,
            },
            {
                title: 'Conductores',
                href: driversIndex(),
                permission: Permission.VIEW_DRIVERS,
            },
            {
                title: 'Terceros',
                href: thirdPartiesIndex(),
                permission: Permission.VIEW_THIRD_PARTIES,
            },
            {
                title: 'Contratos',
                href: contractsIndex(),
                permission: Permission.VIEW_CONTRACTS,
            },
        ],
    },
    {
        label: 'Facturación',
        icon: Receipt,
        permission: Permission.VIEW_INVOICES,
        items: [
            {
                title: 'Facturas',
                href: invoicesIndex(),
                permission: Permission.VIEW_INVOICES,
            },
        ],
    },
    {
        label: 'FUEC',
        icon: FileText,
        permission: Permission.VIEW_FUEC,
        items: [
            {
                title: 'Documentos FUEC',
                href: fuecsIndex(),
                permission: Permission.VIEW_FUEC,
            },
        ],
    },
    {
        label: 'GPS',
        icon: MapPin,
        permission: Permission.VIEW_VEHICLES,
        items: [
            {
                title: 'Ubicaciones',
                href: vehicleLocationsIndex(),
                permission: Permission.VIEW_VEHICLES,
            },
        ],
    },
    {
        label: 'Catálogos',
        icon: Settings,
        items: [
            {
                title: 'Tipos de Documento',
                href: documentTypesIndex(),
            },
            {
                title: 'EPS',
                href: epsIndex(),
            },
            {
                title: 'Fondos de Pensiones',
                href: pensionFundsIndex(),
            },
            {
                title: 'Fondos de Cesantías',
                href: severanceFundsIndex(),
            },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repositorio',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentación',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
