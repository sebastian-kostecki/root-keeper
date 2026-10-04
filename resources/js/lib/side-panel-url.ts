/**
 * Registry of side panels: query key -> Inertia prop that carries the panel
 * data. Every panel slice (S-01, S-02, S-12) adds its entry here together with
 * the resolver on the server side.
 */
export const PANEL_PROPS = {
    person: 'personPreview',
    demo: 'demoPanel',
} as const;

export type PanelKey = keyof typeof PANEL_PROPS;

/** Options shared by every visit that only swaps the panel shown beside the page. */
export const panelVisitOptions: {
    replace: true;
    preserveState: true;
    preserveScroll: true;
    only: string[];
} = {
    replace: true,
    preserveState: true,
    preserveScroll: true,
    only: Object.values(PANEL_PROPS),
};

const BASE_URL = 'http://localhost';

/**
 * Set (or, with null, remove) the query key of one panel in the given URL.
 * Opening a panel also removes the keys of every other panel.
 */
export function withPanel(
    url: string,
    key: PanelKey,
    id: number | string | null,
): string {
    const parsed = new URL(url, BASE_URL);

    if (id === null) {
        parsed.searchParams.delete(key);
    } else {
        for (const panelKey of Object.keys(PANEL_PROPS)) {
            parsed.searchParams.delete(panelKey);
        }

        parsed.searchParams.set(key, String(id));
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}
