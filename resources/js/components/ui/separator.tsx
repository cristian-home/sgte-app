import * as SeparatorPrimitive from '@radix-ui/react-separator';
import * as React from 'react';

import { cn } from '@/lib/utils';

type LabelPosition = 'start' | 'center' | 'end';

type SeparatorProps = React.ComponentProps<typeof SeparatorPrimitive.Root> & {
    label?: React.ReactNode;
    labelPosition?: LabelPosition;
};

function Separator({
    className,
    orientation = 'horizontal',
    decorative = true,
    label,
    labelPosition = 'center',
    ...props
}: SeparatorProps) {
    if (!label) {
        return (
            <SeparatorPrimitive.Root
                data-slot="separator-root"
                decorative={decorative}
                orientation={orientation}
                className={cn(
                    'bg-border shrink-0 data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-px',
                    className,
                )}
                {...props}
            />
        );
    }

    const isHorizontal = orientation === 'horizontal';
    const shortLineSize = isHorizontal ? 'w-3' : 'h-3';
    const lineBase = isHorizontal ? 'h-px' : 'w-px';

    const startClasses = cn(
        'bg-border shrink-0',
        lineBase,
        labelPosition === 'start' ? shortLineSize : 'flex-1',
    );
    const endClasses = cn(
        'bg-border shrink-0',
        lineBase,
        labelPosition === 'end' ? shortLineSize : 'flex-1',
    );

    return (
        <div
            data-slot="separator-root"
            role={decorative ? 'none' : 'separator'}
            aria-orientation={decorative ? undefined : orientation}
            className={cn(
                'flex items-center gap-3',
                isHorizontal ? 'w-full' : 'h-full flex-col',
                className,
            )}
        >
            <div aria-hidden="true" className={startClasses} />
            <span
                className={cn(
                    'shrink-0 text-xs text-muted-foreground',
                    !isHorizontal && '[writing-mode:vertical-rl]',
                )}
            >
                {label}
            </span>
            <div aria-hidden="true" className={endClasses} />
        </div>
    );
}

export { Separator };
