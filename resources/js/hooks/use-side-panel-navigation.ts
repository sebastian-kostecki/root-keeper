import { router, usePage } from '@inertiajs/react';
import {
    PANEL_PROPS,
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

export function useSidePanelNavigation(key: PanelKey): SidePanelNavigation {
    const { url } = usePage();

    const hrefFor = (id: number | string) => withPanel(url, key, id);

    const open = (id: number | string) => {
        router.visit(hrefFor(id), panelVisitOptions);
    };

    const close = () => {
        router.replace({
            url: withPanel(url, key, null),
            props: (current) => ({ ...current, [PANEL_PROPS[key]]: null }),
            preserveScroll: true,
            preserveState: true,
        });
    };

    return { hrefFor, open, close };
}
