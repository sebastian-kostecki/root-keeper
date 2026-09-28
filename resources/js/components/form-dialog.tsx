import type * as React from 'react';
import {
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * Shared long-form dialog layout: full-screen below `sm`, centred above, with a
 * pinned header and footer and a scrolling body. Overlay clicks never close it.
 */
export function FormDialogContent({
    className,
    ...props
}: React.ComponentProps<typeof DialogContent>) {
    return (
        <DialogContent
            onInteractOutside={(event) => event.preventDefault()}
            className={cn(
                'top-0 left-0 flex h-dvh w-full max-w-none translate-x-0 translate-y-0 flex-col gap-0 rounded-none border-0 p-0',
                'sm:top-[50%] sm:left-[50%] sm:h-auto sm:max-h-[90vh] sm:max-w-2xl sm:translate-x-[-50%] sm:translate-y-[-50%] sm:rounded-lg sm:border',
                className,
            )}
            {...props}
        />
    );
}

export function FormDialogHeader({
    title,
    description,
    className,
    ...props
}: Omit<React.ComponentProps<'div'>, 'title'> & {
    title: string;
    description?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col gap-2 px-6 pt-6 pr-12 pb-4',
                className,
            )}
            {...props}
        >
            <DialogTitle>{title}</DialogTitle>
            {description && (
                <DialogDescription>{description}</DialogDescription>
            )}
        </div>
    );
}

export function FormDialogBody({
    className,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <div
            className={cn(
                'min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-4',
                className,
            )}
            {...props}
        />
    );
}

export function FormDialogFooter({
    className,
    ...props
}: React.ComponentProps<typeof DialogFooter>) {
    return (
        <DialogFooter
            className={cn('border-t px-6 py-4', className)}
            {...props}
        />
    );
}
