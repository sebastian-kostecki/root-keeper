/** Lowest scale the tree starts at; below it the start view anchors on the couple. */
export const TREE_FIT_FLOOR = 0.75;

/** Highest scale of the start view, so a small family is not blown up. */
export const TREE_FIT_MAX = 1.5;

/**
 * Lowest scale the ancestors view starts at. Six full generations are 32 cards
 * in the top row, about 6520 px at the minimum column of 12 rem per card and
 * 0.75 rem between them; that row fits a 1000 px screen at scale 0.15, so 0.1
 * keeps the whole row as the start view down to screens of about 650 px and
 * only anchors below that.
 */
export const ANCESTORS_FIT_FLOOR = 0.1;

/** Highest zoom of the ancestors view, the same cap as the tree start view. */
export const ANCESTORS_ZOOM_MAX = 1.5;

/** Smallest card column, in rem: the column of the first render and the floor of the fit. */
export const COLUMN_MIN_REM = 12;

/** Largest card column, in rem. Must stay below 20rem, the threshold of the `@xs` strip. */
export const COLUMN_MAX_REM = 18;

/** Gap between two card columns, in rem. */
export const COLUMN_GAP_REM = 0.75;

/** The fitted column is rounded down to this step, in rem. */
export const COLUMN_STEP_ROUND_REM = 0.25;

/**
 * Card column, in rem, at which `columns` cards fill an area of `areaWidth` px
 * with `paddingRem` of padding around them. Rounded down to a step, so a resize
 * of the area changes the column at most once per 4 px. Clamped to the range
 * from `COLUMN_MIN_REM` to `COLUMN_MAX_REM`; an empty area gets the minimum.
 */
export function fitColumnRem({
    areaWidth,
    rootFontSize,
    columns,
    paddingRem,
}: {
    areaWidth: number;
    rootFontSize: number;
    columns: number;
    paddingRem: number;
}): number {
    if (areaWidth <= 0) {
        return COLUMN_MIN_REM;
    }

    const availableRem =
        areaWidth / rootFontSize - paddingRem - (columns - 1) * COLUMN_GAP_REM;
    const columnRem =
        Math.floor(availableRem / columns / COLUMN_STEP_ROUND_REM) *
        COLUMN_STEP_ROUND_REM;

    return Math.min(COLUMN_MAX_REM, Math.max(COLUMN_MIN_REM, columnRem));
}

type Size = { width: number; height: number };

export type FitView = { kind: 'whole' | 'anchor'; scale: number };

/**
 * Picks the anchor of an anchored start view: the primary (a couple, centred on
 * its axis) when it fits the wrapper with `margin` on each side, otherwise the
 * fallback (the focus card). Both widths are measured at the target scale. A
 * width that equals the limit still fits.
 */
export function chooseAnchor(
    primaryWidth: number,
    wrapperWidth: number,
    margin: number,
): 'primary' | 'fallback' {
    return primaryWidth + 2 * margin <= wrapperWidth ? 'primary' : 'fallback';
}

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
