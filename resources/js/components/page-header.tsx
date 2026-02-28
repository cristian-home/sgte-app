import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface PageHeaderProps {
    title: string;
    createUrl?: string;
    createLabel?: string;
}

export function PageHeader({ title, createUrl, createLabel }: PageHeaderProps) {
    return (
        <div className="flex items-center justify-between">
            <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
            {createUrl && (
                <Button asChild>
                    <Link href={createUrl}>
                        <Plus className="mr-2 size-4" />
                        {createLabel ?? 'Nuevo'}
                    </Link>
                </Button>
            )}
        </div>
    );
}
