import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { usePersonPreview } from '@/components/persons/person-preview-provider';
import { Button } from '@/components/ui/button';
import {
    cardFocus,
    cardInteractive,
    personCardSurface,
} from '@/components/tree/card-styles';
import { useInitials } from '@/hooks/use-initials';
import { useMarriagePanel } from '@/hooks/use-marriage-panel';
import { useVisiblePhoto } from '@/hooks/use-visible-photo';
import { formatAge } from '@/lib/age';
import { genderCard } from '@/lib/gender-styles';
import { personPhotoUrl } from '@/lib/person-photo';
import { cn } from '@/lib/utils';
import { show as treeShow } from '@/routes/tree';
import type { SheetPerson } from '@/types';

type Props = {
    person: SheetPerson;
    /** Union to preselect after re-centring on this person; omitted for none. */
    spouse?: string;
    isFocus?: boolean;
};

/** Strip fill without a photo: the gender colour at full strength, unlike the lighter card fill. */
const stripGender = {
    male: 'bg-gender-male',
    female: 'bg-gender-female',
    unknown: 'bg-gender-unknown',
} as const;

/** Photo strip on the left edge of a person card: the photo, or the initials on the gender colour. */
function PhotoStrip({ person }: { person: SheetPerson }) {
    const getInitials = useInitials();
    const photoUrl = personPhotoUrl(person.id, person.photo_version);
    const { visiblePhotoUrl, onError } = useVisiblePhoto(photoUrl);

    return (
        <span
            className={cn(
                'relative w-12 shrink-0 overflow-hidden @xs:w-18',
                stripGender[person.gender ?? 'unknown'],
            )}
        >
            {visiblePhotoUrl !== null ? (
                <span className="absolute inset-x-0 top-0 bottom-1 overflow-hidden">
                    <img
                        src={visiblePhotoUrl}
                        alt=""
                        loading="lazy"
                        onError={onError}
                        className="size-full object-cover object-center"
                    />
                </span>
            ) : (
                <span
                    aria-hidden="true"
                    className="text-background absolute inset-0 flex items-center justify-center text-lg font-semibold"
                >
                    {getInitials(person.card_name)}
                </span>
            )}
        </span>
    );
}

/**
 * Photo strip and data column of a person card. The data column stacks the
 * name, the dates and the age, aligned to the top left; `children` adds lines
 * below them. `reserveActionSpace` leaves room for the eye button in the first
 * line of the name. The name breaks between words only, to two lines with an
 * ellipsis; the photo strip is 3rem on cards narrower than 20rem and 4.5rem on
 * wider ones (the card is the size container, see `personCardSurface`).
 */
export function CardBody({
    person,
    children,
    reserveActionSpace = false,
}: {
    person: SheetPerson;
    children?: React.ReactNode;
    reserveActionSpace?: boolean;
}) {
    return (
        <>
            <PhotoStrip person={person} />
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
                {person.lifespan && person.age && (
                    <span className="text-tree-meta block text-sm whitespace-nowrap">
                        {`(${formatAge(person.age)})`}
                    </span>
                )}
                {children}
            </span>
        </>
    );
}

/**
 * A person on the tree. The card re-centres the tree on the person (an open
 * panel stays open); the eye button opens the person's panel beside the tree.
 */
export function SheetPersonCard({ person, spouse, isFocus = false }: Props) {
    const { currentId } = usePersonPreview();
    const { currentId: currentMarriageId } = useMarriagePanel();
    const body = cn(
        personCardSurface,
        'text-card-foreground shadow-xs',
        genderCard[person.gender ?? 'unknown'],
    );

    return (
        <div className="relative h-full">
            {isFocus ? (
                <div aria-current="true" className={cn(body, cardFocus)}>
                    <CardBody person={person} reserveActionSpace />
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
                    <CardBody person={person} reserveActionSpace />
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
