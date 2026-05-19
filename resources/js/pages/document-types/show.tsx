import { Head, Link } from '@inertiajs/react';
import DocumentTypeController from '@/actions/App/Http/Controllers/DocumentTypeController';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, DocumentType } from '@/types';

export default function DocumentTypesShow({
    documentType,
}: {
    documentType: DocumentType;
}) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Tipos de Documento',
            href: DocumentTypeController.index.url(),
        },
        {
            title: documentType.name,
            href: DocumentTypeController.show.url(documentType.id),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={documentType.name} />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{documentType.name}</CardTitle>
                                <CardDescription>
                                    Código: {documentType.code}
                                </CardDescription>
                            </div>
                            <Link
                                href={DocumentTypeController.edit.url(
                                    documentType.id,
                                )}
                            >
                                <Button variant="outline">Editar</Button>
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Persona natural
                                </p>
                                <Badge
                                    variant={
                                        documentType.is_natural_person
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {documentType.is_natural_person
                                        ? 'Sí'
                                        : 'No'}
                                </Badge>
                            </div>
                            <div>
                                <p className="text-sm font-medium text-muted-foreground">
                                    Persona jurídica
                                </p>
                                <Badge
                                    variant={
                                        documentType.is_legal_person
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {documentType.is_legal_person ? 'Sí' : 'No'}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
