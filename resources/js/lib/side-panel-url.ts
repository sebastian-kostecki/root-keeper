/**
 * Registry of side panels: query key -> Inertia prop that carries the panel
 * data. Every panel slice (S-02, S-12) adds its entry here together with the
 * resolver on the server side.
 */
export const PANEL_PROPS = {
    person: 'personPreview',
    marriage: 'marriagePanel',
    demo: 'demoPanel',
    locality: 'localityPanel',
    parish: 'parishPanel',
    cemetery: 'cemeteryPanel',
    source: 'sourcePanel',
} as const;

/**
 * Panel keys without a server prop: the page reads them from the address and
 * they never reach `panelVisitOptions.only`.
 */
export const CLIENT_PANEL_KEYS = ['add'] as const;

export type PanelKey =
    | keyof typeof PANEL_PROPS
    | (typeof CLIENT_PANEL_KEYS)[number];

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

/** The Inertia prop of a panel key, or null for a client-only key. */
export function panelPropFor(key: PanelKey): string | null {
    return key in PANEL_PROPS
        ? PANEL_PROPS[key as keyof typeof PANEL_PROPS]
        : null;
}

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
        for (const panelKey of [
            ...Object.keys(PANEL_PROPS),
            ...CLIENT_PANEL_KEYS,
        ]) {
            parsed.searchParams.delete(panelKey);
        }

        parsed.searchParams.set(key, String(id));
    }

    return `${parsed.pathname}${parsed.search}${parsed.hash}`;
}
