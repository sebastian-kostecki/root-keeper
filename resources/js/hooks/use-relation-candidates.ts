import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { RelationCandidate } from '@/types';

export type UseRelationCandidatesReturn = {
    list: RelationCandidate[] | undefined;
    loading: boolean;
    loaded: boolean;
    load: () => void;
};

/**
 * Loads the optional `relationCandidates` prop on demand (when a picker
 * opens) and keeps the last loaded list across visits that drop it.
 */
export function useRelationCandidates(
    candidates: RelationCandidate[] | undefined,
): UseRelationCandidatesReturn {
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);

    // The optional prop is dropped by any non-partial visit (e.g. the
    // redirect back after a validation error), so keep the last loaded list.
    const [list, setList] = useState(candidates);

    if (candidates !== undefined && candidates !== list) {
        setList(candidates);
    }

    const load = () => {
        setLoaded(false);
        setLoading(true);
        router.reload({
            only: ['relationCandidates'],
            onSuccess: () => setLoaded(true),
            onFinish: () => setLoading(false),
        });
    };

    return { list, loading, loaded, load };
}
