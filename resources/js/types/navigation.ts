import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { Permission } from '@/enums/Permission';

export type BreadcrumbItem = {
    title: string;
    href: string;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    permission?: Permission;
};

export type FeatureFlagKey = 'fuec' | 'gps';

export type NavGroup = {
    label: string;
    icon?: LucideIcon;
    permission?: Permission;
    /**
     * When set, the group is only rendered when
     * `auth.featureFlags[featureFlag] === true`. Used by optional
     * modules (FUEC, GPS) that ship behind a config flag.
     */
    featureFlag?: FeatureFlagKey;
    items: NavItem[];
};
