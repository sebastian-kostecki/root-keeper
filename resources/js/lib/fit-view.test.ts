import { describe, expect, it } from 'vite-plus/test';
import { chooseFitView, TREE_FIT_FLOOR, TREE_FIT_MAX } from './fit-view';

const options = { floor: TREE_FIT_FLOOR, max: TREE_FIT_MAX };

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
