import { Plus } from 'lucide-react';
import type * as React from 'react';
import {
    cardSurface,
    placeholderAction,
    placeholderStatic,
} from '@/components/tree/card-styles';
import { cn } from '@/lib/utils';

type Props = Omit<React.ComponentProps<'button'>, 'children'> & {
    /** Visible text: the missing person's label, or the verb when `addLabel` is set. */
    label: string;
    /** When set, the card is a button (a dialog trigger) with this aria-label. */
    addLabel?: string;
};

/**
 * A placeholder for a person the tree does not know. With `addLabel` it is a
 * dashed button (a dialog trigger) showing a plus and the verb; without, a
 * static muted block with the label.
 */
export function UnknownPersonCard({ label, addLabel, ...props }: Props) {
    if (addLabel === undefined) {
        return (
            <div className={cn(cardSurface, placeholderStatic, 'text-base')}>
                {label}
            </div>
        );
    }

    return (
        <button
            type="button"
            aria-label={addLabel}
            {...props}
            className={cn(
                cardSurface,
                placeholderAction,
                'w-full cursor-pointer flex-row gap-1 text-base',
            )}
        >
            <Plus className="size-4 shrink-0" aria-hidden="true" />
            <span>{label}</span>
        </button>
    );
}
