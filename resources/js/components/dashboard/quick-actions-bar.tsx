import { Link, usePage } from '@inertiajs/react';
import { FileText, Plus, ShieldAlert } from 'lucide-react';
import { Can } from '@/components/can';
import { Button } from '@/components/ui/button';
import { Permission } from '@/enums/Permission';

/**
 * Compact bar of "I want to start doing X" entry points, shown at the
 * top of the dashboard. Each button gated by the matching permission
 * via `<Can>`; the FUEC action additionally respects the
 * `auth.featureFlags.fuec` flag the same way the sidebar does (so the
 * shortcut never appears when the module is disabled at the env
 * level).
 */
export function QuickActionsBar() {
    const page = usePage<{
        auth?: { featureFlags?: { fuec?: boolean; gps?: boolean } };
    }>();
    const fuecEnabled = page.props.auth?.featureFlags?.fuec === true;

    return (
        <div className="flex flex-wrap gap-2">
            <Can permission={Permission.CREATE_SERVICES}>
                <Button asChild size="sm">
                    <Link href="/services/create">
                        <Plus className="size-4" />
                        <span className="ml-2">Crear Servicio</span>
                    </Link>
                </Button>
            </Can>
            <Can permission={Permission.CREATE_INCIDENTS}>
                <Button asChild size="sm" variant="outline">
                    <Link href="/service-incidents/create">
                        <ShieldAlert className="size-4" />
                        <span className="ml-2">Registrar Novedad</span>
                    </Link>
                </Button>
            </Can>
            {fuecEnabled && (
                <Can permission={Permission.GENERATE_FUEC}>
                    <Button asChild size="sm" variant="outline">
                        <Link href="/fuecs/create">
                            <FileText className="size-4" />
                            <span className="ml-2">Generar FUEC</span>
                        </Link>
                    </Button>
                </Can>
            )}
        </div>
    );
}
