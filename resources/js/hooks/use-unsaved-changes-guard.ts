import { useState } from 'react';
import type { MouseEvent } from 'react';

type Options = {
    /** Key of the record shown in the panel: its id, 'new', or null when closed. */
    openKey: number | string | null;
    /** Switches the panel to the record with this id (or 'new'). */
    open: (id: number | string) => void;
};

/** Asks before a panel action drops unsaved form changes. */
export function useUnsavedChangesGuard({ openKey, open }: Options) {
    const [isDirty, setIsDirty] = useState(false);
    const [pendingAction, setPendingAction] = useState<(() => void) | null>(
        null,
    );

    /** Run the action now, or after the user agrees to drop the unsaved changes. */
    const guard = (action: () => void) => {
        if (isDirty) {
            setPendingAction(() => action);

            return;
        }

        action();
    };

    const guardLink = (event: MouseEvent<Element>, id: number | string) => {
        // Let the browser handle modified clicks (new tab) as usual.
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        // The record is already open: nothing to switch to, and the form keeps its changes.
        if (id === openKey) {
            event.preventDefault();

            return;
        }

        if (!isDirty) {
            return;
        }

        event.preventDefault();
        guard(() => open(id));
    };

    const discard = () => {
        const action = pendingAction;

        setPendingAction(null);
        setIsDirty(false);
        action?.();
    };

    return {
        setIsDirty,
        guard,
        guardLink,
        discardDialog: {
            open: pendingAction !== null,
            onCancel: () => setPendingAction(null),
            onDiscard: discard,
        },
    };
}
