import { router, usePage } from '@inertiajs/react';
import {
    panelPropFor,
    panelVisitOptions,
    withPanel,
} from '@/lib/side-panel-url';
import type { PanelKey } from '@/lib/side-panel-url';

type SidePanelNavigation = {
    /** URL of the current page with the given record open in the panel. */
    hrefFor: (id: number | string) => string;
    /** Open the panel, or swap the record shown in an open one. */
    open: (id: number | string) => void;
    /** Close the panel without a server request. */
    close: () => void;
};

/** The address at the moment of the call: a guarded action may run after other visits changed it. */
function liveUrl(): string {
    const { pathname, search, hash } = window.location;

    return `${pathname}${search}${hash}`;
}

export function useSidePanelNavigation(key: PanelKey): SidePanelNavigation {
    const { url } = usePage();
    const propName = panelPropFor(key);

    const hrefFor = (id: number | string) => withPanel(url, key, id);

    const open = (id: number | string) => {
        router.visit(withPanel(liveUrl(), key, id), panelVisitOptions);
    };

    const close = () => {
        const url = withPanel(liveUrl(), key, null);

        if (propName === null) {
            router.replace({ url, preserveScroll: true, preserveState: true });

            return;
        }

        router.replace({
            url,
            props: (current) => ({ ...current, [propName]: null }),
            preserveScroll: true,
            preserveState: true,
        });
    };

    return { hrefFor, open, close };
}
