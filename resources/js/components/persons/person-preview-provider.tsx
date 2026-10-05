import { usePage } from '@inertiajs/react';
import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';

type PersonPreviewContextValue = {
    /** Id of the person currently shown, or null when the panel is closed. */
    currentId: number | null;
    /** URL of the current page with the panel of the given person open. */
    hrefFor: (personId: number) => string;
    /** Open the panel, or swap its content to the given person. */
    open: (personId: number) => void;
    /** Close the panel without a server request. */
    close: () => void;
};

const PersonPreviewContext = createContext<PersonPreviewContextValue | null>(
    null,
);

/**
 * Exposes the person panel navigation to every entry point. The panel itself
 * is driven by the `person` query parameter.
 */
export function PersonPreviewProvider({ children }: { children: ReactNode }) {
    const { props } = usePage();
    const currentId = props.personPreview?.person.id ?? null;
    const { hrefFor, open, close } = useSidePanelNavigation('person');

    return (
        <PersonPreviewContext.Provider
            value={{ currentId, hrefFor, open, close }}
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
