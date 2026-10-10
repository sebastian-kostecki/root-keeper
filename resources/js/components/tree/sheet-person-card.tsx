import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { usePersonPreview } from '@/components/persons/person-preview-provider';
import { Button } from '@/components/ui/button';
import {
    cardFocus,
    cardInteractive,
    cardSurface,
} from '@/components/tree/card-styles';
import { useMarriagePanel } from '@/hooks/use-marriage-panel';
import { formatAge } from '@/lib/age';
import { genderCard } from '@/lib/gender-styles';
import { cn } from '@/lib/utils';
import { show as treeShow } from '@/routes/tree';
import type { SheetPerson } from '@/types';

type Props = {
    person: SheetPerson;
    /** Union to preselect after re-centring on this person; omitted for none. */
    spouse?: string;
    isFocus?: boolean;
};

function CardBody({ person }: { person: SheetPerson }) {
    return (
        <>
            <span className="text-card-foreground text-base leading-tight font-semibold [overflow-wrap:anywhere] break-words">
                {person.gender === 'male' && (
                    <span className="sr-only">mężczyzna</span>
                )}
                {person.gender === 'female' && (
                    <span className="sr-only">kobieta</span>
                )}
                {person.display_name}
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
        cardSurface,
        'text-card-foreground shadow-xs',
        genderCard[person.gender ?? 'unknown'],
    );

    return (
        <div className="relative h-full">
            {isFocus ? (
                <div aria-current="true" className={cn(body, cardFocus)}>
                    <CardBody person={person} />
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
                    <CardBody person={person} />
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
