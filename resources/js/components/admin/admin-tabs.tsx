import { Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { index as permissionsIndex } from '@/actions/App/Http/Controllers/PermissionController';
import { index as rolesIndex } from '@/actions/App/Http/Controllers/RoleController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/UserController';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

type AdminTab = 'users' | 'roles' | 'permissions';

interface Props {
    current: AdminTab;
}

const TABS: { key: AdminTab; label: string; href: () => { url: string } }[] = [
    { key: 'users', label: 'Usuarios', href: usersIndex },
    { key: 'roles', label: 'Roles', href: rolesIndex },
    { key: 'permissions', label: 'Permisos', href: permissionsIndex },
];

export function AdminTabs({ current }: Props) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <div className="inline-flex h-9 items-center rounded-md bg-muted p-1 text-muted-foreground">
                {TABS.map((tab) => (
                    <Link
                        key={tab.key}
                        href={tab.href().url}
                        className={cn(
                            'inline-flex items-center justify-center rounded-sm px-3 py-1.5 text-sm font-medium transition-all',
                            current === tab.key
                                ? 'bg-background text-foreground shadow-sm'
                                : 'hover:text-foreground',
                        )}
                    >
                        {tab.label}
                    </Link>
                ))}
            </div>
            <Badge variant="outline" className="gap-1">
                <ShieldCheck className="size-3" /> Referencia
            </Badge>
        </div>
    );
}
