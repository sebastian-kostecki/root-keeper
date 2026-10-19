import { Fragment, useRef } from 'react';
import { AncestorCard } from '@/components/tree/ancestor-card';
import { ZoomCanvas } from '@/components/zoom-canvas';
import type { AncestorLink } from '@/lib/ancestor-layout';
import { layoutAncestorChart } from '@/lib/ancestor-layout';
import {
    ANCESTORS_ANCHOR_SCALE,
    ANCESTORS_FIT_FLOOR,
    ANCESTORS_ZOOM_MAX,
    COLUMN_GAP_REM,
} from '@/lib/fit-view';
import type { AncestorChart as AncestorChartData } from '@/types';

/** Tyle co siatka drzewa: wykres z mniejszą liczbą kolumn nie dostaje szerszej karty niż drzewo. */
const WIDE_CHART_COLUMNS = 8;

/** Height of the row of bars between two card rows (h-6). */
const LINK_ROW_CLASS = 'relative h-6';

/** Distance from one column's left edge to the next one's, in rem, at a card column. */
function columnStepRem(column: number): number {
    return column + COLUMN_GAP_REM;
}

/**
 * Left edge in rem of a point given in column units, where column `c` is
 * centred at `c + 0.5`. Lines are placed in rem, so they follow the same grid.
 */
function toRem(columnUnits: number, column: number): number {
    return columnUnits * columnStepRem(column) - COLUMN_GAP_REM / 2;
}

/** Bars from one child up to its two parents; one row per generation gap. */
function LinkRow({
    links,
    widthRem,
    column,
}: {
    links: AncestorLink[];
    widthRem: number;
    /** Card column in rem, the same one the cards are drawn with. */
    column: number;
}) {
    return (
        <div className={LINK_ROW_CLASS} style={{ width: `${widthRem}rem` }}>
            {links.map((link, index) => {
                const from = toRem(link.from, column);
                const to = toRem(link.to, column);
                const trunk = toRem(link.trunk, column);

                return (
                    <Fragment key={index}>
                        <div
                            className="bg-tree-line absolute top-0 h-1/2 w-px -translate-x-1/2"
                            style={{ left: `${from}rem` }}
                            aria-hidden="true"
                        />
                        <div
                            className="bg-tree-line absolute top-0 h-1/2 w-px -translate-x-1/2"
                            style={{ left: `${to}rem` }}
                            aria-hidden="true"
                        />
                        <div
                            className="bg-tree-line absolute top-1/2 h-px"
                            style={{
                                left: `${from}rem`,
                                width: `${to - from}rem`,
                            }}
                            aria-hidden="true"
                        />
                        <div
                            className="bg-tree-line absolute bottom-0 h-1/2 w-px -translate-x-1/2"
                            style={{ left: `${trunk}rem` }}
                            aria-hidden="true"
                        />
                    </Fragment>
                );
            })}
        </div>
    );
}

/**
 * The ancestor chart: one row of cards per generation, the focus person at the
 * bottom, with the bars between the rows. Starts fitted and zooms in on request.
 */
export function AncestorChart({ chart }: { chart: AncestorChartData }) {
    const anchorRef = useRef<HTMLDivElement>(null);
    const { columns, generations, cards, links } = layoutAncestorChart(
        chart.root,
    );
    const rows = Array.from(
        { length: generations },
        (_, index) => generations - index,
    );

    return (
        <ZoomCanvas
            key={`${chart.focus.id}:${chart.generations}`}
            anchorRef={anchorRef}
            floor={ANCESTORS_FIT_FLOOR}
            anchorScale={ANCESTORS_ANCHOR_SCALE}
            max={ANCESTORS_ZOOM_MAX}
            columns={Math.max(columns, WIDE_CHART_COLUMNS)}
            paddingRem={1}
            zoomControls
        >
            {(column) => {
                const widthRem =
                    columns * columnStepRem(column) - COLUMN_GAP_REM;
                const rowStyle = {
                    width: `${widthRem}rem`,
                    gridTemplateColumns: `repeat(${columns}, ${column}rem)`,
                    columnGap: `${COLUMN_GAP_REM}rem`,
                };

                return (
                    <div className="flex w-max flex-col p-2">
                        {rows.map((generation) => (
                            <Fragment key={generation}>
                                <div className="grid" style={rowStyle}>
                                    {cards
                                        .filter(
                                            (card) =>
                                                card.generation === generation,
                                        )
                                        .map((card) => (
                                            <div
                                                key={card.key}
                                                ref={
                                                    card.generation === 1
                                                        ? anchorRef
                                                        : undefined
                                                }
                                                className="justify-self-center"
                                                style={{
                                                    gridColumn: `${card.column + 1} / span ${card.span}`,
                                                    width: `${column}rem`,
                                                }}
                                            >
                                                <AncestorCard
                                                    node={card.node}
                                                    isFocus={
                                                        card.generation === 1
                                                    }
                                                />
                                            </div>
                                        ))}
                                </div>
                                {generation > 1 && (
                                    <LinkRow
                                        links={links.filter(
                                            (link) =>
                                                link.generation ===
                                                generation - 1,
                                        )}
                                        widthRem={widthRem}
                                        column={column}
                                    />
                                )}
                            </Fragment>
                        ))}
                    </div>
                );
            }}
        </ZoomCanvas>
    );
}
