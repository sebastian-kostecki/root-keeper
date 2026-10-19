import type { RefObject } from 'react';
import { useRef, useState } from 'react';
// clsx, not cn: tailwind-merge reads the width utilities `border-line` / `border-lineage` as colours and drops the real colour class.
import { clsx } from 'clsx';
import { useOptionalSectionEditor } from '@/components/persons/section-editor';
import { addTriggerLabels } from '@/components/tree/add-person-config';
import { MarriageRingsIcon } from '@/components/tree/marriage-rings-icon';
import { MarryCoupleDialog } from '@/components/tree/marry-couple-dialog';
import { SheetPersonCard } from '@/components/tree/sheet-person-card';
import type { SheetCardVariant } from '@/components/tree/sheet-person-card';
import { UnionNode } from '@/components/tree/union-node';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import { ZoomCanvas } from '@/components/zoom-canvas';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { centreHints } from '@/lib/centre-hints';
import { addValueFor } from '@/lib/add-person-target';
import type { AddTarget } from '@/lib/add-person-target';
import {
    COLUMN_GAP_REM,
    distributeRows,
    SHEET_COLUMN_MIN_REM,
} from '@/lib/fit-view';
import type {
    FamilySheet as FamilySheetData,
    PairMarriage,
    SheetSlot,
} from '@/types';

type BlockKey = 'focus' | 'partner';

/** Cards in one row of the sheet grid, the width of the grandparent row. */
const SHEET_COLUMNS = 8;

/** Padding around the sheet in rem (`p-2`), left out of the fitted column. */
const SHEET_PADDING_REM = 1;

/** Width of the eight-column grandparent row for a card column: the base width of the sheet. */
function gridRem(column: number): number {
    return SHEET_COLUMNS * column + (SHEET_COLUMNS - 1) * COLUMN_GAP_REM;
}

/** The couple card is at most this wide, however wide the column grows. */
const PAIR_CARD_MAX_REM = 28;

/** The couple card is three columns wide and the gaps between them, capped at `PAIR_CARD_MAX_REM`. */
function coupleCardRem(column: number): number {
    return Math.min(3 * column + 2 * COLUMN_GAP_REM, PAIR_CARD_MAX_REM);
}

/** Limits of the row heights in rem; the surplus of the area goes to the rows by weight. */
const SHEET_ROW_BOUNDS = {
    grandparents: { min: 5, max: 7, weight: 1 },
    parents: { min: 6, max: 9, weight: 1.25 },
    pair: { min: 10, max: 16, weight: 2 },
    children: { min: 6, max: 10, weight: 1.25 },
};

/** Heights in rem of the connectors between rows (`h-11`, `h-12` with an action, `h-7.5`). */
const CONNECTOR_REM = 2.75;
const CONNECTOR_ACTION_REM = 3;
const TRUNK_REM = 1.875;
const TRUNK_ACTION_REM = 3;

/** Child cell padding in rem above (`pt-4`) and below (`pb-2`) the card. */
const CHILD_CELL_TOP_REM = 1;
const CHILD_CELL_BOTTOM_REM = 0.5;
const CHILD_CELL_PADDING_REM = CHILD_CELL_TOP_REM + CHILD_CELL_BOTTOM_REM;

/** Height in rem of the visible "Dzieci (N)" label (`text-xs`, line height 1 rem). */
const CHILDREN_LABEL_REM = 1;

/** Gap in rem between the label and the add-child slot of an empty children row (`gap-1`). */
const NO_CHILDREN_GAP_REM = 0.25;

/** Vertical space in rem of the sheet that does not stretch with the rows. */
function sheetFixedRem({
    parentsAction,
    marryCentre,
    childCount,
}: {
    parentsAction: boolean;
    marryCentre: boolean;
    childCount: number;
}): number {
    const childrenExtra =
        childCount === 0
            ? NO_CHILDREN_GAP_REM
            : CHILD_CELL_PADDING_REM +
              (slotStandsBeside(childCount) ? 0 : CHILD_CELL_BOTTOM_REM);

    return (
        2 * SHEET_PADDING_REM +
        CONNECTOR_REM +
        (parentsAction ? CONNECTOR_ACTION_REM : CONNECTOR_REM) +
        (marryCentre ? TRUNK_ACTION_REM : TRUNK_REM) +
        CHILDREN_LABEL_REM +
        childrenExtra
    );
}

/** Heights in rem of the four rows for a budget; the minimums when the budget is 0. */
function sheetRowHeights(
    budgetRem: number,
    childCount: number,
): Record<keyof typeof SHEET_ROW_BOUNDS, number> {
    // A row of four or more children has the add-child slot under it, so it counts twice.
    const childFactor = childCount > 0 && !slotStandsBeside(childCount) ? 2 : 1;
    const { grandparents, parents, pair, children } = SHEET_ROW_BOUNDS;
    const [gp, pa, co, ch] = distributeRows({
        budgetRem,
        rows: [
            grandparents,
            parents,
            pair,
            {
                min: children.min * childFactor,
                max: children.max * childFactor,
                weight: children.weight * childFactor,
            },
        ],
    });

    return {
        grandparents: gp,
        parents: pa,
        pair: co,
        children: ch / childFactor,
    };
}

function rowStyle(minHeightRem: number) {
    return { minHeight: `${minHeightRem}rem` };
}

/** The eight fixed columns, shared by the sheet grid and the couple row inside it. */
function gridStyle(column: number) {
    return {
        gridTemplateColumns: `repeat(${SHEET_COLUMNS}, ${column}rem)`,
        columnGap: `${COLUMN_GAP_REM}rem`,
    };
}

/** Children-row cells carry the column gap as padding, so their bar segments touch. */
const CELL_PADDING_STYLE = { paddingInline: `${COLUMN_GAP_REM / 2}rem` };

/** Children cards are two columns wide up to this many children, one column beyond. */
const WIDE_CHILDREN_MAX = 4;

/** Line tokens of the ordinary tree line (1.5 px) and of the ancestor line (2 px, primary). */
const LINE = {
    colour: 'border-tree-line',
    text: 'text-tree-line',
    frame: 'border-line',
    top: 'border-t-line',
    right: 'border-r-line',
    bottom: 'border-b-line',
    left: 'border-l-line',
};

const LINEAGE = {
    colour: 'border-primary',
    text: 'text-primary',
    frame: 'border-lineage',
    top: 'border-t-lineage',
    right: 'border-r-lineage',
    bottom: 'border-b-lineage',
    left: 'border-l-lineage',
};

type LineTone = typeof LINE;

/**
 * The open add panel sheet-wide: its `add` value, and the ref that the trigger
 * of that value takes. The ref is set on that trigger only, so the sheet can
 * reveal it.
 */
export type ActiveAdd = {
    value: string | null;
    revealRef: RefObject<HTMLButtonElement | null>;
};

/**
 * An empty slot of the tree: its card opens the add-person panel for the
 * target. A click while another panel is dirty waits on that panel's guard.
 */
function AddPersonSlot({
    target,
    active,
    compact = false,
}: {
    target: AddTarget;
    active: ActiveAdd;
    compact?: boolean;
}) {
    const { open } = useSidePanelNavigation('add');
    const editor = useOptionalSectionEditor();
    const value = addValueFor(target);
    const { label, addLabel } = addTriggerLabels(target);
    const isActive = active.value === value;

    return (
        <UnknownPersonCard
            ref={isActive ? active.revealRef : undefined}
            label={label}
            addLabel={addLabel}
            active={isActive}
            compact={compact}
            onClick={() => {
                if (editor === null) {
                    open(value);

                    return;
                }

                editor.requestExit(() => open(value));
            }}
        />
    );
}

function Slot({
    slot,
    spouses,
    active,
    variant = 'standard',
}: {
    slot: SheetSlot;
    /** Union to preselect per person id when re-centring on a card. */
    spouses: Record<number, string>;
    active: ActiveAdd;
    variant?: SheetCardVariant;
}) {
    if (slot.kind === 'person') {
        return (
            <SheetPersonCard
                person={slot.person}
                spouse={spouses[slot.person.id]}
                variant={variant}
            />
        );
    }

    const compact = variant === 'compact';

    if (slot.kind === 'unknown') {
        return slot.parent_of === undefined ? (
            <UnknownPersonCard label={slot.label} compact={compact} />
        ) : (
            <AddPersonSlot
                target={{ kind: 'parent', slot: slot.parent_of }}
                active={active}
                compact={compact}
            />
        );
    }

    return null;
}

function RowLabel({
    children,
    visible = false,
}: {
    children: string;
    visible?: boolean;
}) {
    return (
        <h3 className={visible ? 'text-tree-meta text-xs' : 'sr-only'}>
            {children}
        </h3>
    );
}

/**
 * One arm of a pair connector: from the centre of its card to the junction,
 * with the outer corner rounded. A slot that is not a person is a placeholder,
 * so its arm is dashed in the ordinary line.
 */
function Arm({
    side,
    slot,
    lineage,
    edgeRem,
}: {
    side: 'left' | 'right';
    slot: SheetSlot;
    lineage: boolean;
    /** Distance from the connector's outer edge to the centre of the card, in rem. */
    edgeRem: number;
}) {
    const isPerson = slot.kind === 'person';
    const tone = isPerson && lineage ? LINEAGE : LINE;
    const isLeft = side === 'left';
    const edge = `${edgeRem}rem`;

    return (
        <div
            aria-hidden="true"
            className={clsx(
                'absolute top-0 h-4',
                isLeft ? 'rounded-bl-xl' : 'rounded-br-xl',
                tone.colour,
                tone.bottom,
                isLeft ? tone.left : tone.right,
                !isPerson && 'border-dashed',
            )}
            style={
                isLeft
                    ? { left: edge, right: '50%' }
                    : { left: '50%', right: edge }
            }
        />
    );
}

/**
 * Bracket joining a pair of cards to the person below them: two arms meet at
 * the junction in the middle, and a stem drops from it. The marriage node, or
 * the "Połącz jako małżeństwo" action, sits on the junction.
 */
function PairConnector({
    slots,
    className,
    action,
    lineage,
    marriage,
    cardCentreRem,
}: {
    slots: SheetSlot[];
    className: string;
    action?: React.ReactNode;
    /** The line leads to the focus person, so it is drawn as the ancestor line. */
    lineage: boolean;
    marriage: PairMarriage | null;
    /** Distance from the connector's outer edge to the centre of its first card, in rem. */
    cardCentreRem: number;
}) {
    const visible = slots.some((slot) => slot.kind !== 'empty');
    const stemSolid = slots.some((slot) => slot.kind === 'person');
    const stemTone = stemSolid && lineage ? LINEAGE : LINE;

    return (
        <div
            className={clsx('relative', className)}
            style={{
                height: `${action ? CONNECTOR_ACTION_REM : CONNECTOR_REM}rem`,
            }}
        >
            {visible && (
                <>
                    <Arm
                        side="left"
                        slot={slots[0] ?? { kind: 'empty' }}
                        lineage={lineage}
                        edgeRem={cardCentreRem}
                    />
                    <Arm
                        side="right"
                        slot={slots[1] ?? { kind: 'empty' }}
                        lineage={lineage}
                        edgeRem={cardCentreRem}
                    />
                    <div
                        className={clsx(
                            'absolute top-4 bottom-0 left-1/2',
                            stemTone.colour,
                            stemTone.left,
                            !stemSolid && 'border-dashed',
                        )}
                        aria-hidden="true"
                    />
                </>
            )}
            {marriage !== null ? (
                <MarriageNode
                    marriage={marriage}
                    tone={lineage ? LINEAGE : LINE}
                />
            ) : (
                action && (
                    <div className="bg-background absolute top-4 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-md">
                        {action}
                    </div>
                )
            )}
        </div>
    );
}

/**
 * The marriage node on the junction of a pair: the rings with the year, or the
 * rings alone in a circle when the marriage has no year.
 */
function MarriageNode({
    marriage,
    tone,
}: {
    marriage: PairMarriage;
    tone: LineTone;
}) {
    return (
        <span
            aria-hidden="true"
            className={clsx(
                'bg-background absolute top-4 left-1/2 flex -translate-x-1/2 -translate-y-1/2 items-center justify-center gap-1 rounded-full',
                tone.frame,
                marriage.year ? 'h-5.5 px-2' : 'size-6',
            )}
        >
            <MarriageRingsIcon className={tone.text} />
            {marriage.year && (
                <span className="text-foreground text-xs font-medium tabular-nums">
                    {marriage.year}
                </span>
            )}
        </span>
    );
}

/** The "Połącz jako małżeństwo" button for a block's unmarried known parents. */
function parentPairAction(
    sheet: FamilySheetData,
    block: BlockKey,
): React.ReactNode {
    const [first, second] = sheet.parents[block];

    if (
        !sheet.unmarried_parents[block] ||
        first?.kind !== 'person' ||
        second?.kind !== 'person'
    ) {
        return undefined;
    }

    return <MarryCoupleDialog first={first.person} second={second.person} />;
}

/** Width in rem of a child cell: its card plus the gap, which sits inside the cell. */
function childCellRem(count: number, column: number): number {
    const columns = count <= WIDE_CHILDREN_MAX ? 2 : 1;

    return columns * column + (columns - 1) * COLUMN_GAP_REM + COLUMN_GAP_REM;
}

/** The add-child slot is one column wide, whatever the width of the children cards. */
function slotCellRem(column: number): number {
    return column + COLUMN_GAP_REM;
}

/** Up to this many children the add-child slot stands right of the row; from one more it stands under the last child. */
const SLOT_BESIDE_MAX_CHILDREN = 3;

/**
 * One threshold, so the slot keeps its place across similar family sizes. From
 * four children the row (wide cards up to four, narrow from five) reaches the
 * width of the grandparent row, and a slot beside it would widen the sheet.
 */
function slotStandsBeside(count: number): boolean {
    return count <= SLOT_BESIDE_MAX_CHILDREN;
}

type ChildrenRowLayout = {
    cellRem: number;
    rowRem: number;
    slotRem: number;
    slotBeside: boolean;
};

/** Geometry of the children row, computed once for the row and for the sheet padding. */
function childrenRowLayout(count: number, column: number): ChildrenRowLayout {
    const cellRem = childCellRem(count, column);

    return {
        cellRem,
        rowRem: count * cellRem,
        slotRem: slotCellRem(column),
        slotBeside: slotStandsBeside(count),
    };
}

/** Empty-state of the children row: the add-child slot under the couple, labelled "Dzieci (0)", with the note for screen readers. */
function NoChildren({
    addChild,
    heightRem,
}: {
    addChild: React.ReactNode;
    heightRem: number;
}) {
    return (
        <>
            <p className="sr-only">Brak dzieci w tym związku.</p>
            <div className="col-span-2 col-start-4 flex flex-col items-center gap-1">
                <RowLabel visible>Dzieci (0)</RowLabel>
                <div className="w-full" style={rowStyle(heightRem)}>
                    {addChild}
                </div>
            </div>
        </>
    );
}

/**
 * The branch of one child cell under the children bar. The first of two or more
 * children curves from the centre to the right; the last one curves from the
 * left to the centre when the add-child slot stands under the row. The others
 * run the full width of the bar. A single child takes the drop and the right
 * half of the bar, which reaches the slot standing beside it.
 */
function ChildBranch({
    index,
    count,
    slotBeside,
}: {
    index: number;
    count: number;
    slotBeside: boolean;
}) {
    if (count === 1) {
        return (
            <>
                <div
                    className={clsx(
                        'absolute top-0 left-1/2 h-4',
                        LINE.colour,
                        LINE.left,
                    )}
                    aria-hidden="true"
                />
                <div
                    className={clsx(
                        'absolute top-0 right-0 left-1/2',
                        LINE.colour,
                        LINE.top,
                    )}
                    aria-hidden="true"
                />
            </>
        );
    }

    if (index === 0) {
        return (
            <div
                className={clsx(
                    'absolute top-0 right-0 left-1/2 h-4 rounded-tl-xl',
                    LINE.colour,
                    LINE.top,
                    LINE.left,
                )}
                aria-hidden="true"
            />
        );
    }

    if (index === count - 1 && !slotBeside) {
        return (
            <div
                className={clsx(
                    'absolute top-0 right-1/2 left-0 h-4 rounded-tr-xl',
                    LINE.colour,
                    LINE.top,
                    LINE.right,
                )}
                aria-hidden="true"
            />
        );
    }

    return (
        <>
            <div
                className={clsx(
                    'absolute inset-x-0 top-0',
                    LINE.colour,
                    LINE.top,
                )}
                aria-hidden="true"
            />
            <div
                className={clsx(
                    'absolute top-0 left-1/2 h-4',
                    LINE.colour,
                    LINE.left,
                )}
                aria-hidden="true"
            />
        </>
    );
}

/**
 * All children in one row, centred on the couple axis, under one bar that the
 * trunk from the couple reaches. The bar is made of half segments inside the
 * cells, so cells have no gap (the card spacing is cell padding) and the
 * segments touch. The add-child slot stands right of the last child, outside
 * the bar, and does not move the row; in a row too wide for that it stands
 * under the last child.
 */
function ChildrenRow({
    children,
    layout,
    addChild,
    cardHeightRem,
}: Pick<FamilySheetData, 'children'> & {
    layout: ChildrenRowLayout;
    /** Minimum height in rem of the child cards. */
    cardHeightRem: number;
    addChild: React.ReactNode;
}) {
    const compact = children.length > WIDE_CHILDREN_MAX;
    const cellStyle = {
        ...CELL_PADDING_STYLE,
        width: `${layout.cellRem}rem`,
        paddingTop: `${CHILD_CELL_TOP_REM}rem`,
        paddingBottom: `${CHILD_CELL_BOTTOM_REM}rem`,
        ...rowStyle(cardHeightRem + CHILD_CELL_PADDING_REM),
    };
    const slotStyle = { ...CELL_PADDING_STYLE, width: `${layout.slotRem}rem` };

    return (
        <div className="relative flex w-max flex-col">
            <div className="grid grid-cols-2">
                <div className="col-start-2">
                    <RowLabel visible>{`Dzieci (${children.length})`}</RowLabel>
                </div>
            </div>
            <div className="relative flex">
                {children.map((child, i) => (
                    <div key={child.id} className="relative" style={cellStyle}>
                        <ChildBranch
                            index={i}
                            count={children.length}
                            slotBeside={layout.slotBeside}
                        />
                        <SheetPersonCard
                            person={child}
                            variant={compact ? 'compact' : 'standard'}
                        />
                    </div>
                ))}
                {layout.slotBeside && (
                    <div
                        className="absolute top-0 left-full pt-4 pb-2"
                        style={slotStyle}
                    >
                        <div
                            className={clsx(
                                'absolute top-0 right-1/2 left-0 h-4 rounded-tr-xl border-dashed',
                                LINE.colour,
                                LINE.top,
                                LINE.right,
                            )}
                            aria-hidden="true"
                        />
                        {addChild}
                    </div>
                )}
            </div>
            {!layout.slotBeside && (
                <div className="self-end pb-2" style={slotStyle}>
                    {addChild}
                </div>
            )}
        </div>
    );
}

export function FamilySheet({
    sheet,
    activeAdd,
}: {
    sheet: FamilySheetData;
    /** The `add` query value of the open panel, or null. */
    activeAdd: string | null;
}) {
    const order: BlockKey[] =
        sheet.focus_side === 'right'
            ? ['partner', 'focus']
            : ['focus', 'partner'];
    const { spouses } = centreHints(sheet);
    const selectedUnion = sheet.unions.find(
        (union) => union.key === sheet.selected_union,
    );
    const marryCentreCouple =
        selectedUnion?.is_marriage === false &&
        sheet.partner.kind === 'person' ? (
            <MarryCoupleDialog
                first={sheet.focus}
                second={sheet.partner.person}
            />
        ) : null;

    /** A pair that is known and unmarried is dashed; a partner who is not a person is dashed too. */
    const unmarriedPair =
        sheet.partner.kind === 'person' && selectedUnion?.is_marriage === false;
    const partnerLineDashed = unmarriedPair || sheet.partner.kind !== 'person';
    const stemDashed = sheet.children.length === 0;

    const focusCoupleRef = useRef<HTMLDivElement>(null);
    const partnerCoupleRef = useRef<HTMLDivElement>(null);
    const [coupleRefs] = useState(() => [focusCoupleRef, partnerCoupleRef]);
    const activeSlotRef = useRef<HTMLButtonElement>(null);
    const active: ActiveAdd = { value: activeAdd, revealRef: activeSlotRef };

    const addChild = (
        <AddPersonSlot
            target={{ kind: 'child', target: sheet.add_child }}
            active={active}
        />
    );
    return (
        <ZoomCanvas
            key={`${sheet.focus.id}:${sheet.selected_union ?? ''}`}
            anchorRef={coupleRefs}
            fallbackAnchorRef={focusCoupleRef}
            revealRef={activeSlotRef}
            revealKey={activeAdd}
            columns={SHEET_COLUMNS}
            paddingRem={SHEET_PADDING_REM}
            columnMinRem={SHEET_COLUMN_MIN_REM}
            fillHeight
        >
            {(columnRem, fillRem) => {
                const childrenRow = childrenRowLayout(
                    sheet.children.length,
                    columnRem,
                );
                const sheetRem = Math.max(
                    gridRem(columnRem),
                    childrenRow.rowRem,
                );
                const parentsAction =
                    parentPairAction(sheet, 'focus') !== undefined ||
                    parentPairAction(sheet, 'partner') !== undefined;
                const rowHeights = sheetRowHeights(
                    Math.max(
                        0,
                        fillRem -
                            sheetFixedRem({
                                parentsAction,
                                marryCentre: marryCentreCouple !== null,
                                childCount: sheet.children.length,
                            }),
                    ),
                    sheet.children.length,
                );
                const slotOverflowRem = childrenRow.slotBeside
                    ? Math.max(
                          0,
                          childrenRow.rowRem / 2 +
                              childrenRow.slotRem -
                              sheetRem / 2,
                      )
                    : 0;

                return (
                    <div
                        className="flex w-max flex-col items-center p-2"
                        style={{
                            paddingRight: `calc(0.5rem + ${slotOverflowRem}rem)`,
                        }}
                    >
                        <div className="grid" style={gridStyle(columnRem)}>
                            <RowLabel>Dziadkowie</RowLabel>
                            {order.map((block) =>
                                sheet.grandparents[block].map((slot, i) => (
                                    <div
                                        key={`${block}-grandparent-${i}`}
                                        className="col-span-1"
                                        style={rowStyle(
                                            rowHeights.grandparents,
                                        )}
                                    >
                                        <Slot
                                            slot={slot}
                                            spouses={spouses}
                                            active={active}
                                            variant="compact"
                                        />
                                    </div>
                                )),
                            )}

                            {order.map((block) =>
                                [0, 2].map((start) => (
                                    <PairConnector
                                        key={`${block}-gp-connector-${start}`}
                                        slots={sheet.grandparents[block].slice(
                                            start,
                                            start + 2,
                                        )}
                                        className="col-span-2"
                                        lineage={block === 'focus'}
                                        marriage={
                                            sheet.grandparent_marriages[block][
                                                start / 2
                                            ]
                                        }
                                        cardCentreRem={columnRem / 2}
                                    />
                                )),
                            )}

                            <RowLabel>Rodzice</RowLabel>
                            {order.map((block) =>
                                sheet.parents[block].map((slot, i) => (
                                    <div
                                        key={`${block}-parent-${i}`}
                                        className="col-span-2"
                                        style={rowStyle(rowHeights.parents)}
                                    >
                                        <Slot
                                            slot={slot}
                                            spouses={spouses}
                                            active={active}
                                        />
                                    </div>
                                )),
                            )}

                            {order.map((block) => (
                                <PairConnector
                                    key={`${block}-parent-connector`}
                                    slots={sheet.parents[block]}
                                    className="col-span-4"
                                    action={parentPairAction(sheet, block)}
                                    lineage={block === 'focus'}
                                    marriage={sheet.parent_marriages[block]}
                                    cardCentreRem={
                                        columnRem + COLUMN_GAP_REM / 2
                                    }
                                />
                            ))}

                            <RowLabel>Związek</RowLabel>
                            <div
                                className="relative col-span-8 grid"
                                style={gridStyle(columnRem)}
                            >
                                {order.map((block, index) => (
                                    <div
                                        key={`${block}-couple-line`}
                                        className={clsx(
                                            'border-t-line border-tree-line absolute top-1/2',
                                            index === 0
                                                ? 'right-1/2 left-1/4'
                                                : 'right-1/4 left-1/2',
                                            (block === 'focus'
                                                ? unmarriedPair
                                                : partnerLineDashed) &&
                                                'border-dashed',
                                        )}
                                        aria-hidden="true"
                                    />
                                ))}
                                <div
                                    className={clsx(
                                        'absolute top-1/2 bottom-0 left-1/2',
                                        LINE.colour,
                                        LINE.left,
                                        stemDashed && 'border-dashed',
                                    )}
                                    aria-hidden="true"
                                />
                                {order.map((block) => (
                                    <div
                                        key={`${block}-couple`}
                                        ref={
                                            block === 'focus'
                                                ? focusCoupleRef
                                                : partnerCoupleRef
                                        }
                                        className="relative col-span-4 mx-auto w-full"
                                        style={{
                                            maxWidth: `${coupleCardRem(columnRem)}rem`,
                                            ...rowStyle(rowHeights.pair),
                                        }}
                                    >
                                        {block === 'focus' ? (
                                            <SheetPersonCard
                                                person={sheet.focus}
                                                isFocus
                                                variant="pair"
                                            />
                                        ) : sheet.partner.kind === 'empty' ? (
                                            <AddPersonSlot
                                                target={{
                                                    kind: 'spouse',
                                                    focus: sheet.focus,
                                                    target: sheet.add_spouse,
                                                }}
                                                active={active}
                                            />
                                        ) : (
                                            <Slot
                                                slot={sheet.partner}
                                                spouses={spouses}
                                                active={active}
                                                variant="pair"
                                            />
                                        )}
                                    </div>
                                ))}
                                <div className="absolute top-1/2 left-1/2 z-10 -translate-x-1/2 -translate-y-1/2">
                                    <UnionNode
                                        focus={sheet.focus}
                                        unions={sheet.unions}
                                        selected={sheet.selected_union}
                                        addSpouse={sheet.add_spouse}
                                        addActive={
                                            sheet.partner.kind !== 'empty' &&
                                            activeAdd === 'spouse'
                                        }
                                        revealRef={activeSlotRef}
                                    />
                                </div>
                            </div>

                            <div
                                className="relative col-span-8"
                                style={{
                                    height: `${marryCentreCouple ? TRUNK_ACTION_REM : TRUNK_REM}rem`,
                                }}
                            >
                                <div
                                    className={clsx(
                                        'absolute inset-y-0 left-1/2',
                                        LINE.colour,
                                        LINE.left,
                                        stemDashed && 'border-dashed',
                                    )}
                                    aria-hidden="true"
                                />
                                {marryCentreCouple && (
                                    <div className="bg-background absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-md">
                                        {marryCentreCouple}
                                    </div>
                                )}
                            </div>

                            {sheet.children.length === 0 && (
                                <NoChildren
                                    addChild={addChild}
                                    heightRem={rowHeights.children}
                                />
                            )}
                        </div>
                        {sheet.children.length > 0 && (
                            <ChildrenRow
                                children={sheet.children}
                                layout={childrenRow}
                                cardHeightRem={rowHeights.children}
                                addChild={addChild}
                            />
                        )}
                    </div>
                );
            }}
        </ZoomCanvas>
    );
}
