import { describe, expect, it } from 'vite-plus/test';
import type { AncestorNode, SheetPerson } from '@/types';
import { layoutAncestorChart } from './ancestor-layout';

function sheetPerson(id: number): SheetPerson {
    return {
        id,
        display_name: `Osoba ${id}`,
        gender: null,
        lifespan: null,
        is_deceased: false,
        age: null,
        photo_version: null,
    };
}

function known(
    id: number,
    parents: AncestorNode[] = [],
    repeated = false,
): AncestorNode {
    return { kind: 'person', person: sheetPerson(id), repeated, parents };
}

function unknown(): AncestorNode {
    return { kind: 'unknown', label: 'Nieznany rodzic' };
}

/** A focus person with two parents, each with two unknown parents: 3 generations, 4 columns. */
function threeGenerations(): AncestorNode {
    return known(1, [
        known(2, [unknown(), unknown()]),
        known(3, [unknown(), unknown()]),
    ]);
}

describe('layoutAncestorChart', () => {
    it('places the focus person over two unknown parents', () => {
        const layout = layoutAncestorChart(known(1, [unknown(), unknown()]));

        expect(layout.columns).toBe(2);
        expect(layout.generations).toBe(2);
        expect(layout.cards).toHaveLength(3);
        expect(layout.cards).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    key: '0',
                    generation: 1,
                    column: 0,
                    span: 2,
                }),
                expect.objectContaining({
                    key: '0.0',
                    generation: 2,
                    column: 0,
                    span: 1,
                }),
                expect.objectContaining({
                    key: '0.1',
                    generation: 2,
                    column: 1,
                    span: 1,
                }),
            ]),
        );
        expect(layout.links).toEqual([
            { generation: 1, from: 0.5, to: 1.5, trunk: 1 },
        ]);
    });

    it('lays out three full generations in four columns', () => {
        const layout = layoutAncestorChart(threeGenerations());

        expect(layout.columns).toBe(4);
        expect(layout.generations).toBe(3);
        expect(
            layout.cards.filter((card) => card.generation === 3),
        ).toHaveLength(4);
    });

    it('makes a branch cut short by an unknown parent narrower', () => {
        const layout = layoutAncestorChart(
            known(1, [known(2, [unknown(), unknown()]), unknown()]),
        );

        expect(layout.columns).toBe(3);
        expect(layout.cards.find((card) => card.key === '0.1')).toMatchObject({
            generation: 2,
            column: 2,
            span: 1,
        });
    });

    it('gives a repeated person one card per place in the chart', () => {
        const layout = layoutAncestorChart(
            known(1, [known(2, [], true), known(2, [], true)]),
        );
        const repeatedCards = layout.cards.filter(
            (card) => card.node.kind === 'person' && card.node.person.id === 2,
        );

        expect(repeatedCards.map((card) => card.key)).toEqual(['0.0', '0.1']);
        expect(new Set(layout.cards.map((card) => card.key)).size).toBe(3);
    });

    it('centres the uneven branches of a three-column chart', () => {
        const layout = layoutAncestorChart(
            known(1, [known(2, [unknown(), unknown()]), unknown()]),
        );

        expect(layout.links).toHaveLength(2);
        expect(layout.links).toEqual(
            expect.arrayContaining([
                { generation: 1, from: 1, to: 2.5, trunk: 1.5 },
                { generation: 2, from: 0.5, to: 1.5, trunk: 1 },
            ]),
        );
    });

    it('keeps every link with from <= trunk <= to', () => {
        const layout = layoutAncestorChart(
            known(1, [known(2, [unknown(), unknown()]), known(3)]),
        );

        expect(layout.links.length).toBeGreaterThan(0);

        for (const link of layout.links) {
            expect(link.from).toBeLessThanOrEqual(link.trunk);
            expect(link.trunk).toBeLessThanOrEqual(link.to);
        }
    });
});
