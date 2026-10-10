import { usePage } from '@inertiajs/react';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';

/** Navigation of the marriage panel, the `marriage` query parameter. */
export function useMarriagePanel() {
    const { props } = usePage();
    const currentId = props.marriagePanel?.marriage.id ?? null;
    const { hrefFor, open, close } = useSidePanelNavigation('marriage');

    return { currentId, hrefFor, open, close };
}
