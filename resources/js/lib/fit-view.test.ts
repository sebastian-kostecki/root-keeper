import { describe, expect, it } from 'vite-plus/test';
import {
    chooseAnchor,
    chooseFitView,
    distributeRows,
    fillHeightRem,
    COLUMN_MAX_REM,
    COLUMN_MIN_REM,
    COLUMN_STEP_ROUND_REM,
    fitColumnRem,
    TREE_FIT_FLOOR,
    TREE_FIT_MAX,
} from './fit-view';

const options = { floor: TREE_FIT_FLOOR, max: TREE_FIT_MAX };

describe('fitColumnRem', () => {
    const fit = (areaWidth: number, columns = 8) =>
        fitColumnRem({
            areaWidth,
            rootFontSize: 16,
            columns,
            paddingRem: 1,
        });

    it('keeps the minimum column on a 1366 screen', () => {
        expect(fit(1070)).toBe(12);
    });

    it('raises a column of 11.9 rem to the minimum', () => {
        expect(fit(1624)).toBe(12);
    });

    it('rounds the column down to a step of 0.25 rem', () => {
        expect(fit(2264)).toBe(16.75);
    });

    it('caps the column at the maximum on a 2560 screen', () => {
        expect(fit(3544)).toBe(COLUMN_MAX_REM);
    });

    it('gives the minimum for an empty area', () => {
        expect(fit(0)).toBe(COLUMN_MIN_REM);
    });

    it('fills a row of two columns to the maximum', () => {
        expect(fit(2264, 2)).toBe(18);
    });

    it('is always a step inside the range', () => {
        for (let areaWidth = 0; areaWidth <= 4000; areaWidth += 37) {
            const column = fit(areaWidth);

            expect(column).toBeGreaterThanOrEqual(COLUMN_MIN_REM);
            expect(column).toBeLessThanOrEqual(COLUMN_MAX_REM);
            expect(column % COLUMN_STEP_ROUND_REM).toBe(0);
        }
    });
});

describe('fitColumnRem with minRem', () => {
    const fit = (areaWidth: number, minRem?: number) =>
        fitColumnRem({
            areaWidth,
            rootFontSize: 16,
            columns: 8,
            paddingRem: 1,
            minRem,
        });

    it('goes down to a lower minimum on a 1366 screen', () => {
        expect(fit(1070, 7.5)).toBe(7.5);
    });

    it('gives the given minimum for an empty area', () => {
        expect(fit(0, 7.5)).toBe(7.5);
    });

    it('does not change a column above the minimum', () => {
        expect(fit(2264, 7.5)).toBe(16.75);
    });
});

describe('fillHeightRem', () => {
    const fill = (overrides: Partial<Parameters<typeof fillHeightRem>[0]>) =>
        fillHeightRem({
            areaWidth: 1000,
            areaHeight: 600,
            contentWidth: 1000,
            rootFontSize: 16,
            ...options,
            ...overrides,
        });

    it('divides the area height by the scale', () => {
        expect(fill({ contentWidth: 800 })).toBe(30);
    });

    it('holds the scale at the floor for wide content', () => {
        expect(fill({ contentWidth: 4000 })).toBe(50);
    });

    it('holds the scale at the max for narrow content', () => {
        expect(fill({ contentWidth: 100 })).toBe(25);
    });

    it('rounds down to a step of 0.25 rem', () => {
        expect(fill({ areaHeight: 601 })).toBe(37.5);
    });

    it('gives 0 while a size is not positive', () => {
        expect(fill({ areaWidth: 0 })).toBe(0);
        expect(fill({ areaHeight: 0 })).toBe(0);
        expect(fill({ contentWidth: 0 })).toBe(0);
    });
});

describe('distributeRows', () => {
    const rows = [
        { min: 5, max: 7, weight: 1 },
        { min: 6, max: 9, weight: 2 },
    ];

    it('gives the minimums for a budget below their sum', () => {
        expect(distributeRows({ budgetRem: 8, rows })).toEqual([5, 6]);
    });

    it('gives the minimums for a budget of 0', () => {
        expect(distributeRows({ budgetRem: 0, rows })).toEqual([5, 6]);
    });

    it('shares the surplus in proportion to the weights', () => {
        expect(distributeRows({ budgetRem: 14, rows })).toEqual([6, 8]);
    });

    it('drops a row that reaches its max and shares the rest', () => {
        const [first, second] = distributeRows({ budgetRem: 16, rows });

        expect(first).toBe(7);
        expect(second).toBe(9);
        expect(
            distributeRows({
                budgetRem: 14,
                rows: [
                    { min: 1, max: 2, weight: 1 },
                    { min: 1, max: 20, weight: 1 },
                ],
            }),
        ).toEqual([2, 12]);
    });

    it('leaves the surplus above the maximums undistributed', () => {
        expect(distributeRows({ budgetRem: 100, rows })).toEqual([7, 9]);
    });
});

describe('chooseAnchor', () => {
    it('anchors on the pair when it fits with the margin on both sides', () => {
        expect(chooseAnchor(300, 1000, 16)).toBe('primary');
    });

    it('falls back to the focus card when the pair does not fit', () => {
        expect(chooseAnchor(990, 1000, 16)).toBe('fallback');
    });

    it('keeps the pair at exactly the limit, margin included', () => {
        expect(chooseAnchor(968, 1000, 16)).toBe('primary');
        expect(chooseAnchor(968.5, 1000, 16)).toBe('fallback');
    });
});

describe('chooseFitView', () => {
    it('shows the whole content when it fits above the floor', () => {
        expect(
            chooseFitView({
                viewport: { width: 1000, height: 800 },
                content: { width: 1000, height: 1000 },
                ...options,
            }),
        ).toEqual({ kind: 'whole', scale: 0.8 });
    });

    it('caps the scale at max on a large screen', () => {
        expect(
            chooseFitView({
                viewport: { width: 2560, height: 1440 },
                content: { width: 1000, height: 600 },
                ...options,
            }),
        ).toEqual({ kind: 'whole', scale: TREE_FIT_MAX });
    });

    it('anchors at the floor when the whole would be smaller', () => {
        expect(
            chooseFitView({
                viewport: { width: 700, height: 700 },
                content: { width: 1000, height: 600 },
                ...options,
            }),
        ).toEqual({ kind: 'anchor', scale: TREE_FIT_FLOOR });
    });

    it('shows the whole content at exactly the floor', () => {
        expect(
            chooseFitView({
                viewport: { width: 750, height: 900 },
                content: { width: 1000, height: 600 },
                ...options,
            }),
        ).toEqual({ kind: 'whole', scale: TREE_FIT_FLOOR });
    });

    it('returns null while there is no layout', () => {
        const size = { width: 1000, height: 600 };

        expect(
            chooseFitView({
                viewport: { width: 0, height: 600 },
                content: size,
                ...options,
            }),
        ).toBeNull();
        expect(
            chooseFitView({
                viewport: size,
                content: { width: 1000, height: 0 },
                ...options,
            }),
        ).toBeNull();
    });
});
