import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface PageHeaderProps {
    title: string;
    createUrl?: string;
    /**
     * Opens an in-page create dialog instead of navigating. When both
     * `onCreate` and `createUrl` are provided, `onCreate` wins.
     */
    onCreate?: () => void;
    createLabel?: string;
}

export function PageHeader({
    title,
    createUrl,
    onCreate,
    createLabel,
}: PageHeaderProps) {
    return (
        <div className="flex items-center justify-between">
            <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
            {onCreate ? (
                <Button onClick={onCreate}>
                    <Plus className="mr-2 size-4" />
                    {createLabel ?? 'Nuevo'}
                </Button>
            ) : (
                createUrl && (
                    <Button asChild>
                        <Link href={createUrl}>
                            <Plus className="mr-2 size-4" />
                            {createLabel ?? 'Nuevo'}
                        </Link>
                    </Button>
                )
            )}
        </div>
    );
}
