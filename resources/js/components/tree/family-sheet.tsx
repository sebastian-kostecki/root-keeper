import { useRef } from 'react';
import { AddChildDialog } from '@/components/tree/add-child-dialog';
import { AddSpouseDialog } from '@/components/tree/add-spouse-dialog';
import { MarryCoupleDialog } from '@/components/tree/marry-couple-dialog';
import { ParentSlotDialog } from '@/components/tree/parent-slot-dialog';
import { SheetPersonCard } from '@/components/tree/sheet-person-card';
import { UnionNode } from '@/components/tree/union-node';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import { ZoomCanvas } from '@/components/zoom-canvas';
import type {
    FamilySheet as FamilySheetData,
    Gender,
    PersonCandidate,
    Religion,
    SheetSlot,
} from '@/types';

type BlockKey = 'focus' | 'partner';

/** One sheet column and the gap between columns, in rem (a column is a grandparent card). */
const COLUMN_REM = 10;
const COLUMN_GAP_REM = 0.75;

/** Width of the eight-column grandparent row: the base width of the sheet. */
const GRID_REM = 8 * COLUMN_REM + 7 * COLUMN_GAP_REM;

/** Children cards are two columns wide up to this many children, one column beyond. */
const WIDE_CHILDREN_MAX = 4;

function slotSpouse(pair: SheetSlot[], index: number): string | undefined {
    const other = pair[index === 0 ? 1 : 0];

    if (other?.kind === 'person') {
        return String(other.person.id);
    }

    return other?.kind === 'unknown' ? 'unknown' : undefined;
}

function Slot({
    slot,
    spouse,
    candidates,
    genders,
    religions,
}: {
    slot: SheetSlot;
    spouse?: string;
    candidates: PersonCandidate[] | undefined;
    genders: Gender[];
    religions: Religion[];
}) {
    if (slot.kind === 'person') {
        return <SheetPersonCard person={slot.person} spouse={spouse} />;
    }

    if (slot.kind === 'unknown') {
        return slot.parent_of === undefined ? (
            <UnknownPersonCard label={slot.label} />
        ) : (
            <ParentSlotDialog
                slot={slot.parent_of}
                candidates={candidates}
                genders={genders}
                religions={religions}
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
 * the bar, and does not move the row.
 */
function ChildrenRow({
    children,
    addChild,
}: Pick<FamilySheetData, 'children'> & { addChild: React.ReactNode }) {
    const cellRem = childCellRem(children.length);
    const cellStyle = { width: `${cellRem}rem` };

    return (
        <div className="relative flex w-max">
            <RowLabel>Dzieci</RowLabel>
            {children.map((child, i) => (
                <div
                    key={child.id}
                    className="relative px-[0.375rem] pt-3 pb-2"
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
            <div
                className="absolute top-0 left-full px-[0.375rem] pt-3 pb-2"
                style={cellStyle}
            >
                {addChild}
            </div>
        </div>
    );
}

export function FamilySheet({
    sheet,
    personCandidates,
    genders,
    religions,
}: {
    sheet: FamilySheetData;
    personCandidates: PersonCandidate[] | undefined;
    genders: Gender[];
    religions: Religion[];
}) {
    const order: BlockKey[] =
        sheet.focus_side === 'right'
            ? ['partner', 'focus']
            : ['focus', 'partner'];
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

    const coupleRef = useRef<HTMLDivElement>(null);

    const addChild = (
        <AddChildDialog
            target={sheet.add_child}
            candidates={personCandidates}
            genders={genders}
            religions={religions}
        />
    );
    const childrenRowRem =
        sheet.children.length * childCellRem(sheet.children.length);
    const sheetRem = Math.max(GRID_REM, childrenRowRem);
    const slotOverflowRem = Math.max(
        0,
        childrenRowRem / 2 + childCellRem(sheet.children.length) - sheetRem / 2,
    );

    return (
        <ZoomCanvas
            key={`${sheet.focus.id}:${sheet.selected_union ?? ''}`}
            anchorRef={coupleRef}
        >
            <div
                className="flex w-max flex-col items-center p-2"
                style={{
                    paddingRight: `calc(0.5rem + ${slotOverflowRem}rem)`,
                }}
            >
                <div className="grid grid-cols-[repeat(8,10rem)] gap-x-3 gap-y-1">
                    <RowLabel>Dziadkowie</RowLabel>
                    {order.map((block) =>
                        sheet.grandparents[block].map((slot, i) => (
                            <div
                                key={`${block}-grandparent-${i}`}
                                className="col-span-1"
                            >
                                <Slot
                                    slot={slot}
                                    candidates={personCandidates}
                                    genders={genders}
                                    religions={religions}
                                    spouse={slotSpouse(
                                        sheet.grandparents[block].slice(
                                            i < 2 ? 0 : 2,
                                            i < 2 ? 2 : 4,
                                        ),
                                        i % 2,
                                    )}
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
                                    candidates={personCandidates}
                                    genders={genders}
                                    religions={religions}
                                    spouse={slotSpouse(sheet.parents[block], i)}
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
                        ref={coupleRef}
                        className="relative col-span-8 grid grid-cols-8 gap-x-3"
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
                                className="relative col-span-4 mx-auto w-full max-w-72"
                            >
                                {block === 'focus' ? (
                                    <SheetPersonCard
                                        person={sheet.focus}
                                        isFocus
                                    />
                                ) : sheet.partner.kind === 'empty' ? (
                                    <AddSpouseDialog
                                        variant="slot"
                                        focus={sheet.focus}
                                        target={sheet.add_spouse}
                                        candidates={personCandidates}
                                        genders={genders}
                                        religions={religions}
                                    />
                                ) : (
                                    <Slot
                                        slot={sheet.partner}
                                        candidates={personCandidates}
                                        genders={genders}
                                        religions={religions}
                                        spouse={String(sheet.focus.id)}
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
                                candidates={personCandidates}
                                genders={genders}
                                religions={religions}
                            />
                        </div>
                    </div>

                    <div
                        className={`relative col-span-8 ${marryCentreCouple ? 'h-12' : 'h-[30px]'}`}
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
                        addChild={addChild}
                    />
                )}
            </div>
        </ZoomCanvas>
    );
}
