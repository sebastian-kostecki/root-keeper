import { Plus } from 'lucide-react';
import type * as React from 'react';
import type { ParentSlotTarget } from '@/types';

const ROLE_NOUN = {
    father: 'ojca',
    mother: 'matkę',
    parent: 'rodzica',
} as const;

type Props = Omit<React.ComponentProps<'button'>, 'children'> & {
    label: string;
    /** When set, the card is a button that adds a parent to this person. */
    parentOf?: ParentSlotTarget;
};

/**
 * A dashed placeholder for a person the tree does not know. With `parentOf`
 * it is a button (a dialog trigger); without, a static label.
 */
export function UnknownPersonCard({ label, parentOf, ...props }: Props) {
    const base =
        'text-muted-foreground flex h-full min-h-16 items-center rounded-md border border-dashed p-2 text-sm';

    if (parentOf === undefined) {
        return <div className={base}>{label}</div>;
    }

    return (
        <button
            type="button"
            aria-label={`Dodaj ${ROLE_NOUN[parentOf.role]}: ${parentOf.display_name}`}
            {...props}
            className={`${base} hover:bg-accent/50 hover:text-foreground focus-visible:ring-ring w-full cursor-pointer justify-between gap-2 text-left focus-visible:ring-[3px] focus-visible:outline-none`}
        >
            <span>{label}</span>
            <Plus className="size-4 shrink-0" aria-hidden="true" />
        </button>
    );
}
