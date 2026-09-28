import { router, usePage } from '@inertiajs/react';
import { createContext, useContext, useState } from 'react';
import type { ReactNode } from 'react';

type PersonPreviewContextValue = {
    /** Ids of the people viewed before the one currently shown, oldest first. */
    stack: number[];
    /** Id of the person currently shown, or null when the modal is closed. */
    currentId: number | null;
    /** URL of the current page with the preview of the given person open. */
    hrefFor: (personId: number) => string;
    /** Swap the modal content to the given person. */
    open: (personId: number) => void;
    /** Remember a person that was left, so "Wstecz" can return to it. */
    push: (personId: number) => void;
    /** Return to the previously viewed person. */
    back: () => void;
    /** Close the modal without a server request. */
    close: () => void;
};

/** Options shared by every visit that only swaps the preview shown over the page. */
export const previewVisitOptions: {
    replace: boolean;
    preserveState: boolean;
    preserveScroll: boolean;
    only: string[];
} = {
    replace: true,
    preserveState: true,
    preserveScroll: true,
    only: ['personPreview'],
};

const PersonPreviewContext = createContext<PersonPreviewContextValue | null>(
    null,
);

function withPerson(url: string, personId: number | null): string {
    const parsed = new URL(url, window.location.origin);

    if (personId === null) {
        parsed.searchParams.delete('person');
    } else {
        parsed.searchParams.set('person', String(personId));
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}

/**
 * Owns the person preview modal state that must outlive page visits: the
 * "Wstecz" stack. The preview itself is driven by the `person` query parameter.
 */
export function PersonPreviewProvider({ children }: { children: ReactNode }) {
    const { url, props } = usePage();
    const currentId = props.personPreview?.person.id ?? null;
    const [stack, setStack] = useState<number[]>([]);

    if (currentId === null && stack.length > 0) {
        setStack([]);
    }

    const hrefFor = (personId: number) => withPerson(url, personId);

    const open = (personId: number) => {
        router.visit(hrefFor(personId), previewVisitOptions);
    };

    const push = (personId: number) => {
        setStack((previous) => [...previous, personId]);
    };

    const back = () => {
        const previousId = stack[stack.length - 1];

        if (previousId === undefined) {
            return;
        }

        setStack(stack.slice(0, -1));
        open(previousId);
    };

    const close = () => {
        router.replace({
            url: withPerson(url, null),
            props: (current) => ({ ...current, personPreview: null }),
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <PersonPreviewContext.Provider
            value={{
                stack,
                currentId,
                hrefFor,
                open,
                push,
                back,
                close,
            }}
        >
            {children}
        </PersonPreviewContext.Provider>
    );
}

export function usePersonPreview(): PersonPreviewContextValue {
    const context = useContext(PersonPreviewContext);

    if (context === null) {
        throw new Error(
            'usePersonPreview must be used inside PersonPreviewProvider',
        );
    }

    return context;
}
