import { AddChildDialog } from '@/components/tree/add-child-dialog';
import { AddSpouseDialog } from '@/components/tree/add-spouse-dialog';
import { ParentSlotDialog } from '@/components/tree/parent-slot-dialog';
import { SheetPersonCard } from '@/components/tree/sheet-person-card';
import { UnknownPersonCard } from '@/components/tree/unknown-person-card';
import type {
    FamilySheet as FamilySheetData,
    Gender,
    PersonCandidate,
    Religion,
    SheetSlot,
} from '@/types';

type BlockKey = 'focus' | 'partner';

const CHILDREN_PER_ROW = 4;

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
}: {
    slots: SheetSlot[];
    className: string;
}) {
    const visible = slots.some((slot) => slot.kind !== 'empty');

    return (
        <div className={`relative h-6 ${className}`} aria-hidden="true">
            {visible && (
                <>
                    <div className="absolute inset-x-[25%] top-0 h-3 rounded-b-sm border-x border-b" />
                    <div className="bg-border absolute top-3 bottom-0 left-1/2 w-px" />
                </>
            )}
        </div>
    );
}

function ChildrenRows({ children }: Pick<FamilySheetData, 'children'>) {
    const rows: FamilySheetData['children'][] = [];

    for (let i = 0; i < children.length; i += CHILDREN_PER_ROW) {
        rows.push(children.slice(i, i + CHILDREN_PER_ROW));
    }

    return (
        <>
            {rows.map((row) => {
                const inset = 12.5;
                const lineRight = inset + 25 * (CHILDREN_PER_ROW - row.length);

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
                                    marginRight: `${lineRight}%`,
                                }}
                                aria-hidden="true"
                            />
                        )}
                        {row.map((child) => (
                            <div key={child.id} className="col-span-2 pb-2">
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

    return (
        <div className="overflow-x-auto pb-2">
            <div className="grid min-w-[72rem] grid-cols-8 gap-x-2 gap-y-1">
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
                    />
                ))}

                <RowLabel>Związek</RowLabel>
                {order.map((block) => (
                    <div
                        key={`${block}-couple`}
                        className="col-span-4 mx-auto w-full max-w-72"
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

                <div className="relative col-span-8 h-6" aria-hidden="true">
                    {hasChildren && (
                        <div className="bg-border absolute inset-y-0 left-1/2 w-px" />
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
                <div className="col-span-2">
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
