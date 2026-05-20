import { Head } from '@inertiajs/react';
import { useState } from 'react';
import IncidentTypeController from '@/actions/App/Http/Controllers/IncidentTypeController';
import IncidentTypeDialog from '@/components/incident-types/incident-type-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    IncidentSeverityLabel,
    type IncidentSeverity,
} from '@/enums/IncidentSeverity';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, IncidentType } from '@/types';

const severityVariant: Record<
    IncidentSeverity,
    'default' | 'secondary' | 'destructive'
> = {
    informational: 'secondary',
    minor: 'default',
    major: 'destructive',
};

export default function IncidentTypesShow({
    incidentType,
}: {
    incidentType: IncidentType;
}) {
    const [editOpen, setEditOpen] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Tipos de Novedad',
            href: IncidentTypeController.index.url(),
        },
        {
            title: incidentType.name,
            href: IncidentTypeController.show.url(incidentType.id),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={incidentType.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{incidentType.name}</CardTitle>
                                <CardDescription>
                                    Código: {incidentType.code}
                                </CardDescription>
                            </div>
                            <Button
                                variant="outline"
                                onClick={() => setEditOpen(true)}
                            >
                                Editar
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Severidad
                                </p>
                                <Badge
                                    variant={
                                        severityVariant[
                                            incidentType.severity as IncidentSeverity
                                        ]
                                    }
                                >
                                    {
                                        IncidentSeverityLabel[
                                            incidentType.severity as IncidentSeverity
                                        ]
                                    }
                                </Badge>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Afecta facturación por defecto
                                </p>
                                <p>
                                    {incidentType.affects_billing_default
                                        ? 'Si'
                                        : 'No'}
                                </p>
                            </div>
                        </div>
                        {incidentType.description && (
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Descripción
                                </p>
                                <p>{incidentType.description}</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
            <IncidentTypeDialog
                open={editOpen}
                onOpenChange={setEditOpen}
                mode="edit"
                incidentType={incidentType}
            />
        </AppLayout>
    );
}
