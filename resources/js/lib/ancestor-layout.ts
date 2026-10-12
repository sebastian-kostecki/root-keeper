import type { AncestorNode } from '@/types';

/** A card in the grid: its slot starts at `column` and covers `span` columns. */
export type AncestorCardPlacement = {
    /** Path through the tree (`0`, `0.1`, `0.1.0`), so a repeated person keeps two keys. */
    key: string;
    node: AncestorNode;
    /** 1 is the focus person at the bottom; higher numbers go up. */
    generation: number;
    /** 0-based column where the slot starts. */
    column: number;
    /** Columns of the slot: the sum of the parents' slots, or 1 for a card without parents. */
    span: number;
};

/**
 * The bar joining a child's two parents, with the trunk down to the child. Every
 * position is a column centre in column units; `from <= trunk <= to`.
 */
export type AncestorLink = {
    /** Generation of the child; the bar sits between this row and the one above it. */
    generation: number;
    from: number;
    to: number;
    trunk: number;
};

export type AncestorLayout = {
    columns: number;
    generations: number;
    cards: AncestorCardPlacement[];
    links: AncestorLink[];
};

/**
 * Positions the ancestor tree on a column grid. A node with parents takes the
 * columns of its parents (their slots side by side) and sits centred over them;
 * a node without parents takes one column. Each node with parents gets one link.
 */
export function layoutAncestorChart(root: AncestorNode): AncestorLayout {
    const cards: AncestorCardPlacement[] = [];
    const links: AncestorLink[] = [];

    /** Places the subtree from `column` and returns the number of columns it takes. */
    function place(
        node: AncestorNode,
        key: string,
        generation: number,
        column: number,
    ): number {
        if (node.kind === 'unknown' || node.parents.length === 0) {
            cards.push({ key, node, generation, column, span: 1 });

            return 1;
        }

        const centres: number[] = [];
        let cursor = column;

        node.parents.forEach((parent, index) => {
            const span = place(
                parent,
                `${key}.${index}`,
                generation + 1,
                cursor,
            );

            centres.push(cursor + span / 2);
            cursor += span;
        });

        const span = cursor - column;
        const trunk = column + span / 2;

        cards.push({ key, node, generation, column, span });
        links.push({
            generation,
            from: centres[0] ?? trunk,
            to: centres.at(-1) ?? trunk,
            trunk,
        });

        return span;
    }

    const columns = place(root, '0', 1, 0);
    const generations = Math.max(...cards.map((card) => card.generation));

    return { columns, generations, cards, links };
}
