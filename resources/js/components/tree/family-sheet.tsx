import { useRef } from 'react';
import { AddChildDialog } from '@/components/tree/add-child-dialog';
import { AddSpouseDialog } from '@/components/tree/add-spouse-dialog';
import { MarryCoupleDialog } from '@/components/tree/marry-couple-dialog';
import { ParentSlotDialog } from '@/components/tree/parent-slot-dialog';
import { SheetPersonCard } from '@/components/tree/sheet-person-card';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import { useCentreInScroll } from '@/hooks/use-centre-in-scroll';
import type {
    FamilySheet as FamilySheetData,
    Gender,
    PersonCandidate,
    Religion,
    SheetSlot,
} from '@/types';

type BlockKey = 'focus' | 'partner';

const CHILDREN_PER_ROW = 4;

/** Start column of a children row of n cards, centred on the couple axis. */
const CHILDREN_ROW_START: Record<number, string> = {
    1: 'col-start-4',
    2: 'col-start-3',
    3: 'col-start-2',
    4: 'col-start-1',
};

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
                label={slot.label}
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
    return (
        <h3 className="text-muted-foreground col-span-8 mt-2 text-xs font-medium tracking-wide uppercase first:mt-0">
            {children}
        </h3>
    );
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
                        className="absolute inset-x-[25%] top-0 h-3 rounded-b-sm border-x border-b"
                        aria-hidden="true"
                    />
                    <div
                        className="bg-border absolute top-3 bottom-0 left-1/2 w-px"
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

function ChildrenRows({ children }: Pick<FamilySheetData, 'children'>) {
    const rows: FamilySheetData['children'][] = [];

    for (let i = 0; i < children.length; i += CHILDREN_PER_ROW) {
        rows.push(children.slice(i, i + CHILDREN_PER_ROW));
    }

    return (
        <>
            {rows.map((row) => {
                const inset = (CHILDREN_PER_ROW + 1 - row.length) * 12.5;

                return (
                    <div
                        key={row[0].id}
                        className="col-span-8 grid grid-cols-8 gap-x-2"
                    >
                        {row.length > 1 && (
                            <div
                                className="col-span-8 h-3 rounded-t-sm border-x border-t"
                                style={{
                                    marginLeft: `${inset}%`,
                                    marginRight: `${inset}%`,
                                }}
                                aria-hidden="true"
                            />
                        )}
                        {row.map((child, i) => (
                            <div
                                key={child.id}
                                className={`col-span-2 pb-2 ${i === 0 ? CHILDREN_ROW_START[row.length] : ''}`}
                            >
                                <SheetPersonCard person={child} />
                            </div>
                        ))}
                    </div>
                );
            })}
        </>
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
    const hasChildren = sheet.children.length > 0;
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

    const scrollRef = useRef<HTMLDivElement>(null);
    const focusRef = useRef<HTMLDivElement>(null);
    const { canScrollLeft, canScrollRight } = useCentreInScroll(
        scrollRef,
        focusRef,
        `${sheet.focus.id}:${sheet.selected_union ?? ''}`,
    );
    const fadeLeft = canScrollLeft ? 'mask-l-from-90%' : '';
    const fadeRight = canScrollRight ? 'mask-r-from-90%' : '';

    return (
        <div
            ref={scrollRef}
            className={`overflow-x-auto pb-2 ${fadeLeft} ${fadeRight}`}
        >
            <div className="grid min-w-[68rem] grid-cols-8 gap-x-2 gap-y-1">
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
                <div className="relative col-span-8 grid grid-cols-8 gap-x-2">
                    {sheet.partner.kind !== 'empty' && (
                        <div
                            className="bg-border absolute top-1/2 right-[25%] left-[25%] h-px"
                            aria-hidden="true"
                        />
                    )}
                    {order.map((block) => (
                        <div
                            key={`${block}-couple`}
                            ref={block === 'focus' ? focusRef : undefined}
                            className="relative col-span-4 mx-auto w-full max-w-72"
                        >
                            {block === 'focus' ? (
                                <SheetPersonCard person={sheet.focus} isFocus />
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
                </div>

                <div
                    className={`relative col-span-8 ${marryCentreCouple ? 'h-12' : 'h-6'}`}
                >
                    {hasChildren && (
                        <div
                            className="bg-border absolute inset-y-0 left-1/2 w-px"
                            aria-hidden="true"
                        />
                    )}
                    {marryCentreCouple && (
                        <div className="bg-background absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 rounded-md">
                            {marryCentreCouple}
                        </div>
                    )}
                </div>

                <RowLabel>Dzieci</RowLabel>
                {hasChildren ? (
                    <ChildrenRows children={sheet.children} />
                ) : (
                    <p className="text-muted-foreground col-span-8 text-sm">
                        Brak dzieci w tym związku.
                    </p>
                )}
                <div className="col-span-2 col-start-4">
                    <AddChildDialog
                        target={sheet.add_child}
                        candidates={personCandidates}
                        genders={genders}
                        religions={religions}
                    />
                </div>
            </div>
        </div>
    );
}
