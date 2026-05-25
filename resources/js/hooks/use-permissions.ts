import { usePage } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

import { Role } from '@/enums/Role';
import type { Permission } from '@/enums/Permission';
import type { Role as RoleType } from '@/enums/Role';

export function usePermissions() {
    const { auth } = usePage().props;

    const isSuperAdmin = useMemo(
        () => auth.roles.includes(Role.SUPER_ADMIN as RoleType),
        [auth.roles],
    );

    const can = useCallback(
        (permission: Permission) => {
            if (isSuperAdmin) {
                return true;
            }

            return auth.permissions.includes(permission);
        },
        [isSuperAdmin, auth.permissions],
    );

    const hasRole = useCallback(
        (role: RoleType) => auth.roles.includes(role),
        [auth.roles],
    );

    return {
        can,
        hasRole,
        permissions: auth.permissions,
        roles: auth.roles,
        isSuperAdmin,
    };
}
