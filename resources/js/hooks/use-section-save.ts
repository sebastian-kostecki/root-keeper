import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useSectionEditor } from '@/components/persons/section-editor';
import type { SectionId } from '@/components/persons/section-editor';
import type { MarriageSavePayload, PersonPayload } from '@/lib/person-payload';

type Errors = Record<string, string>;

/**
 * Save state of the sections of one panel: which section is being saved, the
 * server errors of the last failed save, and the save itself.
 *
 * `belongsToSection` says which server error keys the fields of a section
 * render; the rest surface as a stray error on the card.
 */
export function useSectionSave(
    belongsToSection: (id: SectionId, key: string) => boolean,
) {
    const editor = useSectionEditor();
    const [savingId, setSavingId] = useState<SectionId | null>(null);
    const [failure, setFailure] = useState<{
        id: SectionId;
        errors: Errors;
    } | null>(null);

    const save = (
        id: SectionId,
        url: string,
        payload: PersonPayload | MarriageSavePayload,
    ): Promise<boolean> =>
        new Promise((resolve) => {
            router.put(url, payload, {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSavingId(id),
                onSuccess: () => {
                    setFailure(null);
                    resolve(true);
                },
                onError: (errors) => {
                    setFailure({ id, errors });
                    resolve(false);
                },
                onFinish: () => {
                    setSavingId(null);
                    resolve(false);
                },
            });
        });

    const errorsFor = (id: SectionId): Errors =>
        failure !== null && failure.id === id && editor.editing === id
            ? failure.errors
            : {};

    /** First server error the section's fields do not show. */
    const strayErrorFor = (id: SectionId): string | undefined =>
        Object.entries(errorsFor(id)).find(
            ([key]) => !belongsToSection(id, key),
        )?.[1];

    return { savingId, errorsFor, strayErrorFor, save };
}
