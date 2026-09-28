import { Plus } from 'lucide-react';
import type * as React from 'react';
import { cardInteractive, cardSurface } from '@/components/tree/card-styles';
import { cn } from '@/lib/utils';

type Props = Omit<React.ComponentProps<'button'>, 'children'> & {
    label: string;
    /** When set, the card is a button (a dialog trigger) with this aria-label. */
    addLabel?: string;
};

/**
 * A dashed placeholder for a person the tree does not know. With `addLabel`
 * it is a button (a dialog trigger); without, a static label.
 */
export function UnknownPersonCard({ label, addLabel, ...props }: Props) {
    const base = cn(
        cardSurface,
        'text-muted-foreground flex items-center border-dashed text-sm',
    );

    if (addLabel === undefined) {
        return <div className={base}>{label}</div>;
    }

    return (
        <button
            type="button"
            aria-label={addLabel}
            {...props}
            className={cn(
                base,
                cardInteractive,
                'hover:border-l-foreground/20 hover:text-foreground w-full cursor-pointer justify-between gap-2 text-left',
            )}
        >
            <span>{label}</span>
            <Plus className="size-4 shrink-0" aria-hidden="true" />
        </button>
    );
}
