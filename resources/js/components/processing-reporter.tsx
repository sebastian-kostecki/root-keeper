import { useEffect } from 'react';

/** Bridges the `processing` flag of a `<Form>` out to a button outside it. */
export function ProcessingReporter({
    processing,
    onChange,
}: {
    processing: boolean;
    onChange: (processing: boolean) => void;
}) {
    useEffect(() => {
        onChange(processing);
    }, [processing, onChange]);

    useEffect(() => () => onChange(false), [onChange]);

    return null;
}
