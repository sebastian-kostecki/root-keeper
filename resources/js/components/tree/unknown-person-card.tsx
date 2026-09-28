import { Plus } from 'lucide-react';
import type * as React from 'react';

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
    const base =
        'text-muted-foreground flex h-full min-h-16 items-center rounded-md border border-dashed p-2 text-sm';

    if (addLabel === undefined) {
        return <div className={base}>{label}</div>;
    }

    return (
        <button
            type="button"
            aria-label={addLabel}
            {...props}
            className={`${base} hover:bg-accent/50 hover:text-foreground focus-visible:ring-ring w-full cursor-pointer justify-between gap-2 text-left focus-visible:ring-[3px] focus-visible:outline-none`}
        >
            <span>{label}</span>
            <Plus className="size-4 shrink-0" aria-hidden="true" />
        </button>
    );
}
