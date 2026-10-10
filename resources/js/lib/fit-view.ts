/** Lowest scale the tree starts at; below it the start view anchors on the couple. */
export const TREE_FIT_FLOOR = 0.75;

/** Highest scale of the start view, so a small family is not blown up. */
export const TREE_FIT_MAX = 1.5;

/**
 * Lowest scale the ancestors view starts at. Six full generations are 32 cards
 * in the top row, about 5490 px at 10 rem per card and 0.75 rem between them;
 * that row fits a 1000 px screen at scale 0.18, so 0.1 keeps the whole row as
 * the start view down to screens of about 550 px and only anchors below that.
 */
export const ANCESTORS_FIT_FLOOR = 0.1;

/** Highest zoom of the ancestors view, the same cap as the tree start view. */
export const ANCESTORS_ZOOM_MAX = 1.5;

type Size = { width: number; height: number };

export type FitView = { kind: 'whole' | 'anchor'; scale: number };

/**
 * Picks the start view: the whole content when it fits at `floor` or more
 * (capped at `max`), otherwise an anchor view held at `floor`. Returns `null`
 * while the viewport or the content has no size yet.
 */
export function chooseFitView({
    viewport,
    content,
    floor,
    max,
}: {
    viewport: Size;
    content: Size;
    floor: number;
    max: number;
}): FitView | null {
    if (
        viewport.width <= 0 ||
        viewport.height <= 0 ||
        content.width <= 0 ||
        content.height <= 0
    ) {
        return null;
    }

    const scale = Math.min(
        viewport.width / content.width,
        viewport.height / content.height,
        max,
    );

    return scale < floor
        ? { kind: 'anchor', scale: floor }
        : { kind: 'whole', scale };
}
