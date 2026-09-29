import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { Button } from '@/components/ui/button';
import {
    cardFocus,
    cardInteractive,
    cardSurface,
} from '@/components/tree/card-styles';
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
            <span className="text-card-foreground text-sm leading-tight font-semibold [overflow-wrap:anywhere] break-words">
                {person.gender === 'male' && (
                    <span className="sr-only">mężczyzna</span>
                )}
                {person.gender === 'female' && (
                    <span className="sr-only">kobieta</span>
                )}
                {person.display_name}
            </span>
            {person.lifespan && (
                <span className="text-tree-meta mt-1 block text-xs">
                    {person.lifespan}
                </span>
            )}
        </>
    );
}

export function SheetPersonCard({ person, spouse, isFocus = false }: Props) {
    const body = cn(
        cardSurface,
        'text-card-foreground shadow-xs',
        genderCard[person.gender ?? 'unknown'],
    );

    return (
        <div className="relative h-full">
            {isFocus ? (
                <div className={cn(body, cardFocus)}>
                    <CardBody person={person} />
                </div>
            ) : (
                <Link
                    href={treeShow(person.id, {
                        query: spouse === undefined ? undefined : { spouse },
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
