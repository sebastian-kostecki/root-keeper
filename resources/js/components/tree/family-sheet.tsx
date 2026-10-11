import type { RefObject } from 'react';
import { useRef, useState } from 'react';
import { useOptionalSectionEditor } from '@/components/persons/section-editor';
import { addTriggerLabels } from '@/components/tree/add-person-config';
import { MarryCoupleDialog } from '@/components/tree/marry-couple-dialog';
import { SheetPersonCard } from '@/components/tree/sheet-person-card';
import { UnionNode } from '@/components/tree/union-node';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import { ZoomCanvas } from '@/components/zoom-canvas';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { centreHints } from '@/lib/centre-hints';
import { addValueFor } from '@/lib/add-person-target';
import type { AddTarget } from '@/lib/add-person-target';
import type { FamilySheet as FamilySheetData, SheetSlot } from '@/types';

type BlockKey = 'focus' | 'partner';

/** One sheet column and the gap between columns, in rem (a column is a grandparent card). */
const COLUMN_REM = 12;
const COLUMN_GAP_REM = 0.75;

/** Width of the eight-column grandparent row: the base width of the sheet. */
const GRID_REM = 8 * COLUMN_REM + 7 * COLUMN_GAP_REM;

/** A couple card is as wide as a parent card: two columns and the gap between them. */
const COUPLE_CARD_REM = 2 * COLUMN_REM + COLUMN_GAP_REM;

/** The eight fixed columns, shared by the sheet grid and the couple row inside it. */
const GRID_STYLE = {
    gridTemplateColumns: `repeat(8, ${COLUMN_REM}rem)`,
    columnGap: `${COLUMN_GAP_REM}rem`,
};

/** Children-row cells carry the column gap as padding, so their bar segments touch. */
const CELL_PADDING_STYLE = { paddingInline: `${COLUMN_GAP_REM / 2}rem` };

/** Children cards are two columns wide up to this many children, one column beyond. */
const WIDE_CHILDREN_MAX = 4;

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
}: {
    target: AddTarget;
    active: ActiveAdd;
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
}: {
    slot: SheetSlot;
    /** Union to preselect per person id when re-centring on a card. */
    spouses: Record<number, string>;
    active: ActiveAdd;
}) {
    if (slot.kind === 'person') {
        return (
            <SheetPersonCard
                person={slot.person}
                spouse={spouses[slot.person.id]}
            />
        );
    }

    if (slot.kind === 'unknown') {
        return slot.parent_of === undefined ? (
            <UnknownPersonCard label={slot.label} />
        ) : (
            <AddPersonSlot
                target={{ kind: 'parent', slot: slot.parent_of }}
                active={active}
            />
        );
    }

    return null;
}

function RowLabel({ children }: { children: string }) {
    return <h3 className="sr-only">{children}</h3>;
}

/** Bracket joining a pair of cards to the person below them. */
function PairConnector({
    slots,
    className,
    action,
}: {
    slots: SheetSlot[];
    className: string;
    action?: React.ReactNode;
}) {
    const visible = slots.some((slot) => slot.kind !== 'empty');

    return (
        <div className={`relative ${action ? 'h-12' : 'h-6'} ${className}`}>
            {visible && (
                <>
                    <div
                        className="border-tree-line absolute inset-x-[25%] top-0 h-3 rounded-b-md border-x border-b"
                        aria-hidden="true"
                    />
                    <div
                        className="bg-tree-line absolute top-3 bottom-0 left-1/2 w-px"
                        aria-hidden="true"
                    />
                </>
            )}
            {action && (
                <div className="bg-background absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-md">
                    {action}
                </div>
            )}
        </div>
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
function childCellRem(count: number): number {
    const columns = count <= WIDE_CHILDREN_MAX ? 2 : 1;

    return (
        columns * COLUMN_REM + (columns - 1) * COLUMN_GAP_REM + COLUMN_GAP_REM
    );
}

/** The add-child slot is one column wide, whatever the width of the children cards. */
const SLOT_CELL_REM = COLUMN_REM + COLUMN_GAP_REM;

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
    slotBeside: boolean;
};

/** Geometry of the children row, computed once for the row and for the sheet padding. */
function childrenRowLayout(count: number): ChildrenRowLayout {
    const cellRem = childCellRem(count);

    return {
        cellRem,
        rowRem: count * cellRem,
        slotBeside: slotStandsBeside(count),
    };
}

/** Empty-state of the children row: a note and the add-child slot under the couple. */
function NoChildren({ addChild }: { addChild: React.ReactNode }) {
    return (
        <>
            <p className="text-muted-foreground col-span-8 text-center text-sm">
                Brak dzieci w tym związku.
            </p>
            <div className="col-span-2 col-start-4">{addChild}</div>
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
}: Pick<FamilySheetData, 'children'> & {
    layout: ChildrenRowLayout;
    addChild: React.ReactNode;
}) {
    const cellStyle = { ...CELL_PADDING_STYLE, width: `${layout.cellRem}rem` };
    const slotStyle = { ...CELL_PADDING_STYLE, width: `${SLOT_CELL_REM}rem` };

    return (
        <div className="relative flex w-max flex-col">
            <RowLabel>Dzieci</RowLabel>
            <div className="flex">
                {children.map((child, i) => (
                    <div
                        key={child.id}
                        className="relative pt-3 pb-2"
                        style={cellStyle}
                    >
                        <div
                            className="bg-tree-line absolute top-0 left-1/2 h-3 w-px"
                            aria-hidden="true"
                        />
                        {i > 0 && (
                            <div
                                className="bg-tree-line absolute top-0 left-0 h-px w-1/2"
                                aria-hidden="true"
                            />
                        )}
                        {i < children.length - 1 && (
                            <div
                                className="bg-tree-line absolute top-0 right-0 h-px w-1/2"
                                aria-hidden="true"
                            />
                        )}
                        <SheetPersonCard person={child} />
                    </div>
                ))}
            </div>
            {layout.slotBeside ? (
                <div
                    className="absolute top-0 left-full pt-3 pb-2"
                    style={slotStyle}
                >
                    {addChild}
                </div>
            ) : (
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
    const childrenRow = childrenRowLayout(sheet.children.length);
    const sheetRem = Math.max(GRID_REM, childrenRow.rowRem);
    const slotOverflowRem = childrenRow.slotBeside
        ? Math.max(0, childrenRow.rowRem / 2 + SLOT_CELL_REM - sheetRem / 2)
        : 0;

    return (
        <ZoomCanvas
            key={`${sheet.focus.id}:${sheet.selected_union ?? ''}`}
            anchorRef={coupleRefs}
            fallbackAnchorRef={focusCoupleRef}
            revealRef={activeSlotRef}
            revealKey={activeAdd}
        >
            <div
                className="flex w-max flex-col items-center p-2"
                style={{
                    paddingRight: `calc(0.5rem + ${slotOverflowRem}rem)`,
                }}
            >
                <div className="grid gap-y-1" style={GRID_STYLE}>
                    <RowLabel>Dziadkowie</RowLabel>
                    {order.map((block) =>
                        sheet.grandparents[block].map((slot, i) => (
                            <div
                                key={`${block}-grandparent-${i}`}
                                className="col-span-1"
                            >
                                <Slot
                                    slot={slot}
                                    spouses={spouses}
                                    active={active}
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
                            />
                        )),
                    )}

                    <RowLabel>Rodzice</RowLabel>
                    {order.map((block) =>
                        sheet.parents[block].map((slot, i) => (
                            <div
                                key={`${block}-parent-${i}`}
                                className="col-span-2"
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
                        />
                    ))}

                    <RowLabel>Związek</RowLabel>
                    <div
                        className="relative col-span-8 grid"
                        style={GRID_STYLE}
                    >
                        {sheet.partner.kind !== 'empty' && (
                            <div
                                className="bg-tree-line absolute top-1/2 right-[25%] left-[25%] h-px"
                                aria-hidden="true"
                            />
                        )}
                        {order.map((block) => (
                            <div
                                key={`${block}-couple`}
                                ref={
                                    block === 'focus'
                                        ? focusCoupleRef
                                        : partnerCoupleRef
                                }
                                className="relative col-span-4 mx-auto w-full"
                                style={{ maxWidth: `${COUPLE_CARD_REM}rem` }}
                            >
                                {block === 'focus' ? (
                                    <SheetPersonCard
                                        person={sheet.focus}
                                        isFocus
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
                        className={`relative col-span-8 ${marryCentreCouple ? 'h-12' : 'h-7.5'}`}
                    >
                        <div
                            className="bg-tree-line absolute inset-y-0 left-1/2 w-px"
                            aria-hidden="true"
                        />
                        {marryCentreCouple && (
                            <div className="bg-background absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-md">
                                {marryCentreCouple}
                            </div>
                        )}
                    </div>

                    {sheet.children.length === 0 && (
                        <>
                            <RowLabel>Dzieci</RowLabel>
                            <NoChildren addChild={addChild} />
                        </>
                    )}
                </div>
                {sheet.children.length > 0 && (
                    <ChildrenRow
                        children={sheet.children}
                        layout={childrenRow}
                        addChild={addChild}
                    />
                )}
            </div>
        </ZoomCanvas>
    );
}
