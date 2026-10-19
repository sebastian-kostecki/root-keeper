import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { usePersonPreview } from '@/components/persons/person-preview-provider';
import { Button } from '@/components/ui/button';
import { CardAvatar } from '@/components/tree/card-avatar';
import {
    cardFocus,
    cardInteractive,
    compactCardSurface,
    personCardSurface,
} from '@/components/tree/card-styles';
import { useMarriagePanel } from '@/hooks/use-marriage-panel';
import { formatAge } from '@/lib/age';
import { genderCard } from '@/lib/gender-styles';
import { pairDetailLines } from '@/lib/pair-details';
import { cn } from '@/lib/utils';
import { show as treeShow } from '@/routes/tree';
import type { SheetPerson } from '@/types';

type Props = {
    person: SheetPerson;
    /** Union to preselect after re-centring on this person; omitted for none. */
    spouse?: string;
    isFocus?: boolean;
    /** `compact`: one column, no avatar, name on two lines. `pair`: wide, with the full details. */
    variant?: SheetCardVariant;
};

export type SheetCardVariant = 'standard' | 'compact' | 'pair';

/**
 * Avatar and data column of a person card. The data column stacks the
 * name, the dates and the age, aligned to the top left; `children` adds lines
 * below them. `reserveActionSpace` leaves room for the eye button in the first
 * line of the name. The name breaks between words only, to two lines with an
 * ellipsis.
 *
 * `variant="tree"` (default) shows the age and an avatar of 2.5rem on cards
 * narrower than 20rem and 3.5rem on wider ones (the card is the size
 * container, see `personCardSurface`). `variant="ancestor"` hides the age and
 * keeps the avatar at a fixed 3.5rem, for the ancestors chart's narrow column.
 */
export function CardBody({
    person,
    children,
    reserveActionSpace = false,
    variant = 'tree',
}: {
    person: SheetPerson;
    children?: React.ReactNode;
    reserveActionSpace?: boolean;
    variant?: 'tree' | 'ancestor';
}) {
    const isAncestor = variant === 'ancestor';

    return (
        <>
            <CardAvatar
                person={person}
                size={isAncestor ? 'ancestor' : 'card'}
            />
            <span className="flex min-w-0 flex-1 flex-col items-start justify-start px-3 py-3.5">
                <span
                    title={person.card_name}
                    className="font-display text-card-foreground line-clamp-2 w-full text-base leading-tight font-semibold text-ellipsis"
                >
                    {reserveActionSpace && (
                        <span
                            aria-hidden="true"
                            className="float-right size-5"
                        />
                    )}
                    {person.gender === 'male' && (
                        <span className="sr-only">mężczyzna</span>
                    )}
                    {person.gender === 'female' && (
                        <span className="sr-only">kobieta</span>
                    )}
                    {person.card_name}
                </span>
                {person.lifespan && (
                    <span className="text-tree-meta mt-1 block text-sm">
                        {person.lifespan}
                    </span>
                )}
                {!isAncestor && person.lifespan && person.age && (
                    <span className="text-tree-meta block text-sm whitespace-nowrap">
                        {`(${formatAge(person.age)})`}
                    </span>
                )}
                {children}
            </span>
        </>
    );
}

/** The gender word read before the name, for screen readers only. */
function GenderWord({ gender }: { gender: SheetPerson['gender'] }) {
    if (gender === 'male') {
        return <span className="sr-only">mężczyzna</span>;
    }

    return gender === 'female' ? (
        <span className="sr-only">kobieta</span>
    ) : null;
}

/**
 * Compact card content: given name and surname on two lines (each one line
 * with an ellipsis), the lifespan below. The visible lines are `aria-hidden`
 * and the full name is read from the `sr-only` text, so the accessible name
 * keeps the space between the parts.
 */
function CompactBody({ person }: { person: SheetPerson }) {
    return (
        <>
            <span
                title={person.card_name}
                className="font-display text-card-foreground block w-full min-w-0 text-sm leading-tight font-semibold"
            >
                <GenderWord gender={person.gender} />
                <span className="sr-only">{person.card_name}</span>
                <span aria-hidden="true" className="block truncate pr-5">
                    {person.card_given_name}
                </span>
                <span aria-hidden="true" className="block truncate">
                    {person.card_surname}
                </span>
            </span>
            {person.lifespan && (
                <span className="text-tree-meta mt-0.5 block w-full truncate text-xs">
                    {person.lifespan}
                </span>
            )}
        </>
    );
}

/**
 * Pair card content: a large avatar, the name, the detail lines (born,
 * died, buried) and the age. Without detail lines the lifespan stands in.
 */
function PairBody({ person }: { person: SheetPerson }) {
    const lines =
        person.details === undefined
            ? []
            : pairDetailLines(person.details, person.is_deceased);

    return (
        <>
            <CardAvatar person={person} size="pair" />
            <span className="flex min-w-0 flex-1 flex-col items-start justify-start px-3 py-3.5">
                <span
                    title={person.card_name}
                    className="font-display text-card-foreground line-clamp-2 w-full text-base leading-tight font-semibold text-ellipsis"
                >
                    <span aria-hidden="true" className="float-right size-5" />
                    <GenderWord gender={person.gender} />
                    {person.card_name}
                </span>
                {lines.length > 0
                    ? lines.map((line) => (
                          <span
                              key={line}
                              className="text-tree-meta mt-0.5 block text-sm"
                          >
                              {line}
                          </span>
                      ))
                    : person.lifespan && (
                          <span className="text-tree-meta mt-1 block text-sm">
                              {person.lifespan}
                          </span>
                      )}
                {person.age && (
                    <span className="text-tree-meta mt-0.5 block text-sm whitespace-nowrap">
                        {`(${formatAge(person.age)})`}
                    </span>
                )}
            </span>
        </>
    );
}

/**
 * A person on the tree. The card re-centres the tree on the person (an open
 * panel stays open); the eye button opens the person's panel beside the tree.
 */
export function SheetPersonCard({
    person,
    spouse,
    isFocus = false,
    variant = 'standard',
}: Props) {
    const { currentId } = usePersonPreview();
    const { currentId: currentMarriageId } = useMarriagePanel();
    const content =
        variant === 'compact' ? (
            <CompactBody person={person} />
        ) : variant === 'pair' ? (
            <PairBody person={person} />
        ) : (
            <CardBody person={person} reserveActionSpace />
        );
    const body = cn(
        variant === 'compact' ? compactCardSurface : personCardSurface,
        'text-card-foreground shadow-xs',
        genderCard[person.gender ?? 'unknown'],
    );

    return (
        <div className="relative h-full">
            {isFocus ? (
                <div aria-current="true" className={cn(body, cardFocus)}>
                    {content}
                </div>
            ) : (
                <Link
                    href={treeShow(person.id, {
                        query: {
                            ...(spouse === undefined ? {} : { spouse }),
                            ...(currentId === null
                                ? {}
                                : { person: currentId }),
                            ...(currentMarriageId === null
                                ? {}
                                : { marriage: currentMarriageId }),
                        },
                    })}
                    className={cn(body, cardInteractive)}
                >
                    {content}
                </Link>
            )}

            <Button
                variant="ghost"
                size="icon"
                className="text-tree-meta focus-visible:ring-ring absolute top-1 right-1 size-7"
                asChild
            >
                <PersonPreviewLink
                    personId={person.id}
                    aria-label={`Podgląd: ${person.display_name}`}
                    title="Podgląd"
                >
                    <Eye />
                </PersonPreviewLink>
            </Button>
        </div>
    );
}
