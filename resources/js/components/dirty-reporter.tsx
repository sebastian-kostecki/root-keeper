import { useEffect } from 'react';

/** Bridges a `<Form>` render-prop `isDirty` out to the page that guards the panel. */
export function DirtyReporter({
    isDirty,
    onDirtyChange,
}: {
    isDirty: boolean;
    onDirtyChange: (isDirty: boolean) => void;
}) {
    useEffect(() => {
        onDirtyChange(isDirty);
    }, [isDirty, onDirtyChange]);

    useEffect(() => () => onDirtyChange(false), [onDirtyChange]);

    return null;
}
