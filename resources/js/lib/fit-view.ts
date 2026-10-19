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

/**
 * Smallest card column of the family sheet, in rem. Its eight-column grandparent
 * row is the widest and must fit a 1366 px screen; the compact card and the
 * two-line "Dodaj matkę" slot still fit this column.
 */
export const SHEET_COLUMN_MIN_REM = 7.5;

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
 * from `minRem` (`COLUMN_MIN_REM` by default) to `COLUMN_MAX_REM`; an empty
 * area gets the minimum.
 */
export function fitColumnRem({
    areaWidth,
    rootFontSize,
    columns,
    paddingRem,
    minRem = COLUMN_MIN_REM,
}: {
    areaWidth: number;
    rootFontSize: number;
    columns: number;
    paddingRem: number;
    minRem?: number;
}): number {
    if (areaWidth <= 0) {
        return minRem;
    }

    const availableRem =
        areaWidth / rootFontSize - paddingRem - (columns - 1) * COLUMN_GAP_REM;
    const columnRem =
        Math.floor(availableRem / columns / COLUMN_STEP_ROUND_REM) *
        COLUMN_STEP_ROUND_REM;

    return Math.min(COLUMN_MAX_REM, Math.max(minRem, columnRem));
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

/**
 * Height in rem that content of `contentWidth` px must have to fill an area at
 * the start scale: the area height over the scale the width fit picks (clamped
 * to `[floor, max]`). Rounded down to a step; 0 while any size is not positive.
 */
export function fillHeightRem({
    areaWidth,
    areaHeight,
    contentWidth,
    rootFontSize,
    floor,
    max,
}: {
    areaWidth: number;
    areaHeight: number;
    contentWidth: number;
    rootFontSize: number;
    floor: number;
    max: number;
}): number {
    if (
        areaWidth <= 0 ||
        areaHeight <= 0 ||
        contentWidth <= 0 ||
        rootFontSize <= 0
    ) {
        return 0;
    }

    const scale = Math.min(max, Math.max(floor, areaWidth / contentWidth));
    const rem = areaHeight / scale / rootFontSize;

    return Math.floor(rem / COLUMN_STEP_ROUND_REM) * COLUMN_STEP_ROUND_REM;
}

export type RowBounds = { min: number; max: number; weight: number };

/**
 * Spreads `budgetRem` over rows like water filling: every row starts at its
 * `min`, the surplus goes in proportion to `weight`, and a row that reaches its
 * `max` drops out while the rest share what is left. A budget below the sum of
 * the minimums gives the minimums; a surplus above the sum of the maximums stays
 * undistributed.
 */
export function distributeRows({
    budgetRem,
    rows,
}: {
    budgetRem: number;
    rows: RowBounds[];
}): number[] {
    const heights = rows.map((row) => row.min);
    let remaining = budgetRem - heights.reduce((sum, h) => sum + h, 0);
    let open = rows
        .map((_, index) => index)
        .filter(
            (index) =>
                rows[index].weight > 0 && rows[index].max > heights[index],
        );

    while (remaining > 1e-9 && open.length > 0) {
        const totalWeight = open.reduce((sum, i) => sum + rows[i].weight, 0);
        const capped = open.filter(
            (i) =>
                heights[i] + (remaining * rows[i].weight) / totalWeight >=
                rows[i].max,
        );

        if (capped.length === 0) {
            for (const i of open) {
                heights[i] += (remaining * rows[i].weight) / totalWeight;
            }

            break;
        }

        for (const i of capped) {
            remaining -= rows[i].max - heights[i];
            heights[i] = rows[i].max;
        }

        open = open.filter((i) => !capped.includes(i));
    }

    return heights;
}
