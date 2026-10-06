import { router } from '@inertiajs/react';
import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useRef,
    useState,
} from 'react';
import type { MouseEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';

export type SectionId =
    | 'personal'
    | 'birth'
    | 'death'
    | 'residences'
    | `marriage-${number}`;

/** Hints how an edit starts, e.g. "Oznacz zgon" opens Zgon with the checkbox on. */
export type SectionIntent = 'mark-deceased';

type SectionEditorContextValue = {
    editing: SectionId | null;
    intent: SectionIntent | null;
    isDirty: boolean;
    /** Opens a section for editing; guarded when another section is dirty. */
    start: (id: SectionId, intent?: SectionIntent) => void;
    /** Leaves edit mode without asking. */
    cancel: () => void;
    markDirty: (dirty: boolean) => void;
    /** Runs `action` now, or after the user resolves the guard bar. */
    requestExit: (action: () => void) => void;
    /** The editing card hands over its submit so the guard can save it. */
    registerSubmit: (submit: (() => Promise<boolean>) | null) => void;
    hasPendingExit: boolean;
    isSaving: boolean;
    dirtyLabel: string;
    discardAndExit: () => void;
    saveAndExit: () => void;
};

const SectionEditorContext = createContext<SectionEditorContextValue | null>(
    null,
);

/** Locative form used in "Masz niezapisane zmiany w …". */
function labelFor(id: SectionId | null): string {
    if (id === null) {
        return 'nowej osobie';
    }

    if (id === 'personal') {
        return 'danych osobowych';
    }

    if (id === 'residences') {
        return 'zamieszkaniach';
    }

    if (id === 'birth') {
        return 'urodzeniu';
    }

    if (id === 'death') {
        return 'zgonie';
    }

    return 'małżeństwie';
}

type EditState = { id: SectionId; intent: SectionIntent | null };

type PendingExit = { action: () => void };

/**
 * Owns "which section is editing, is it dirty, what exit is waiting for the
 * user's decision" for the person panel. Hosted by the layout, so it also wraps
 * the page beside the panel; `personId` is null while no panel is open. Resets
 * whenever the shown person changes, including to and from null.
 */
export function SectionEditorProvider({
    personId,
    children,
}: {
    personId: number | null;
    children: ReactNode;
}) {
    const [shownPersonId, setShownPersonId] = useState(personId);
    const [edit, setEdit] = useState<EditState | null>(null);
    const [isDirty, setIsDirty] = useState(false);
    const [pending, setPending] = useState<PendingExit | null>(null);
    const [isSaving, setIsSaving] = useState(false);
    const submitRef = useRef<(() => Promise<boolean>) | null>(null);
    /** True while an exit the user already let through runs; its visits pass the page guard. */
    const isLeavingRef = useRef(false);

    if (shownPersonId !== personId) {
        setShownPersonId(personId);
        setEdit(null);
        setIsDirty(false);
        setPending(null);
    }

    /**
     * A visit started beside the panel (sidebar, breadcrumbs, another record's
     * panel from a list) drops `?person` and the unsaved section with it, so
     * while a section is dirty it waits on the guard bar. Saves, prefetches and
     * visits that keep the shown person open pass.
     */
    useEffect(() => {
        if (!isDirty || personId === null) {
            return;
        }

        return router.on('before', (event) => {
            const { visit } = event.detail;
            const keepsPerson =
                visit.url.searchParams.get('person') === String(personId);

            if (
                isLeavingRef.current ||
                visit.prefetch ||
                visit.method !== 'get' ||
                keepsPerson
            ) {
                return;
            }

            if (!isSaving) {
                // The state of this visit stays behind; the retry gets its own.
                const {
                    url,
                    id: _id,
                    cancelled: _cancelled,
                    completed: _completed,
                    interrupted: _interrupted,
                    ...options
                } = visit;

                setPending({ action: () => router.visit(url, options) });
            }

            return false;
        });
    }, [isDirty, isSaving, personId]);

    /** Runs an exit the user let through, so the page guard lets its visit pass. */
    const leave = (action: () => void) => {
        isLeavingRef.current = true;

        try {
            action();
        } finally {
            isLeavingRef.current = false;
        }
    };

    const cancel = () => {
        setEdit(null);
        setIsDirty(false);
        setPending(null);
    };

    const markDirty = useCallback((dirty: boolean) => {
        setIsDirty(dirty);

        if (!dirty) {
            setPending(null);
        }
    }, []);

    const requestExit = (action: () => void) => {
        if (isSaving) {
            return;
        }

        if (!isDirty) {
            setEdit(null);
            action();

            return;
        }

        setPending({ action });
    };

    const start = (id: SectionId, intent?: SectionIntent) => {
        if (edit?.id === id || isSaving) {
            return;
        }

        const open = () => setEdit({ id, intent: intent ?? null });

        if (isDirty) {
            setPending({ action: open });

            return;
        }

        open();
    };

    const registerSubmit = useCallback(
        (submit: (() => Promise<boolean>) | null) => {
            submitRef.current = submit;
        },
        [],
    );

    const discardAndExit = () => {
        if (pending === null) {
            return;
        }

        const { action } = pending;

        cancel();
        leave(action);
    };

    const saveAndExit = () => {
        const submit = submitRef.current;

        if (pending === null || submit === null) {
            setPending(null);

            return;
        }

        const { action } = pending;

        setIsSaving(true);

        void submit()
            .catch(() => false)
            .then((isSaved) => {
                setIsSaving(false);
                setPending(null);

                if (isSaved) {
                    leave(action);
                }
            });
    };

    return (
        <SectionEditorContext.Provider
            value={{
                editing: edit?.id ?? null,
                intent: edit?.intent ?? null,
                isDirty,
                start,
                cancel,
                markDirty,
                requestExit,
                registerSubmit,
                hasPendingExit: pending !== null,
                isSaving,
                dirtyLabel: labelFor(edit?.id ?? null),
                discardAndExit,
                saveAndExit,
            }}
        >
            {children}
        </SectionEditorContext.Provider>
    );
}

export function useSectionEditor(): SectionEditorContextValue {
    const context = useContext(SectionEditorContext);

    if (context === null) {
        throw new Error(
            'useSectionEditor must be used inside SectionEditorProvider',
        );
    }

    return context;
}

/** Same as `useSectionEditor`, but null outside `AppLayout`. */
export function useOptionalSectionEditor(): SectionEditorContextValue | null {
    return useContext(SectionEditorContext);
}

export function isPlainClick(event: MouseEvent): boolean {
    return (
        event.button === 0 &&
        !event.metaKey &&
        !event.ctrlKey &&
        !event.shiftKey &&
        !event.altKey
    );
}

/**
 * For links that leave the current view: on a plain click while a section is
 * dirty, cancels the navigation and hands it to the guard bar as `visit`.
 * Returns true when the click was taken over.
 */
export function useGuardedClick(): (
    event: MouseEvent,
    visit: () => void,
) => boolean {
    const editor = useOptionalSectionEditor();

    return (event, visit) => {
        if (
            editor === null ||
            !editor.isDirty ||
            event.defaultPrevented ||
            !isPlainClick(event)
        ) {
            return false;
        }

        event.preventDefault();
        editor.requestExit(visit);

        return true;
    };
}

/** Bar shown over the panel when an exit is waiting on unsaved changes. */
export function UnsavedChangesBar() {
    const {
        hasPendingExit,
        dirtyLabel,
        isSaving,
        discardAndExit,
        saveAndExit,
    } = useSectionEditor();

    if (!hasPendingExit) {
        return null;
    }

    return (
        <div
            role="alert"
            className="bg-background absolute inset-x-0 bottom-0 z-30 flex flex-col gap-3 border-t px-6 py-4 shadow-lg sm:flex-row sm:items-center sm:justify-between"
        >
            <p className="text-sm font-medium">
                Masz niezapisane zmiany w {dirtyLabel}.
            </p>
            <div className="flex justify-end gap-2">
                <Button
                    type="button"
                    variant="secondary"
                    disabled={isSaving}
                    onClick={discardAndExit}
                >
                    Porzuć
                </Button>
                <Button type="button" disabled={isSaving} onClick={saveAndExit}>
                    Zapisz
                </Button>
            </div>
        </div>
    );
}
