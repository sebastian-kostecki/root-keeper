import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { RelationCandidate } from '@/types';

export type UseOptionalListReturn<T> = {
    list: T[] | undefined;
    loading: boolean;
    loaded: boolean;
    load: () => void;
};

export type UseRelationCandidatesReturn =
    UseOptionalListReturn<RelationCandidate>;

/**
 * Loads an optional list prop on demand (when a picker opens) and keeps the
 * last loaded list across visits that drop it.
 */
export function useOptionalList<T>(
    propName: string,
    items: T[] | undefined,
): UseOptionalListReturn<T> {
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);

    // The optional prop is dropped by any non-partial visit (e.g. the
    // redirect back after a validation error), so keep the last loaded list.
    const [list, setList] = useState(items);

    if (items !== undefined && items !== list) {
        setList(items);
    }

    const load = () => {
        setLoaded(false);
        setLoading(true);
        router.reload({
            only: [propName],
            onSuccess: () => setLoaded(true),
            onFinish: () => setLoading(false),
        });
    };

    return { list, loading, loaded, load };
}

/**
 * Loads the optional `relationCandidates` prop on demand (when a picker opens).
 */
export function useRelationCandidates(
    candidates: RelationCandidate[] | undefined,
): UseRelationCandidatesReturn {
    return useOptionalList('relationCandidates', candidates);
}
