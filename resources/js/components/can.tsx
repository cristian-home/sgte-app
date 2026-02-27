import { usePermissions } from '@/hooks/use-permissions';
import type { ReactNode } from 'react';
import type { Permission } from '@/enums/Permission';


export function Can({
    permission,
    fallback = null,
    children,
}: {
    permission: Permission;
    fallback?: ReactNode;
    children: ReactNode;
}) {
    const { can } = usePermissions();

    return can(permission) ? children : fallback;
}
