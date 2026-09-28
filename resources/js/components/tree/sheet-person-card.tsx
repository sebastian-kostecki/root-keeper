import { Link } from '@inertiajs/react';
import { Eye, Mars, Venus } from 'lucide-react';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { Button } from '@/components/ui/button';
import { cardInteractive, cardSurface } from '@/components/tree/card-styles';
import { cn } from '@/lib/utils';
import { show as treeShow } from '@/routes/tree';
import type { SheetPerson } from '@/types';

type Props = {
    person: SheetPerson;
    /** Union to preselect after re-centring on this person; omitted for none. */
    spouse?: string;
    isFocus?: boolean;
};

const strip = {
    male: 'border-l-gender-male',
    female: 'border-l-gender-female',
    unknown: 'border-l-gender-unknown',
} as const;

function CardBody({ person }: { person: SheetPerson }) {
    return (
        <>
            <span className="flex items-start gap-1.5">
                {person.gender === 'male' && (
                    <Mars
                        role="img"
                        aria-label="mężczyzna"
                        className="text-gender-male mt-0.5 size-4 shrink-0"
                    />
                )}
                {person.gender === 'female' && (
                    <Venus
                        role="img"
                        aria-label="kobieta"
                        className="text-gender-female mt-0.5 size-4 shrink-0"
                    />
                )}
                <span className="text-sm leading-tight font-medium break-words">
                    {person.display_name}
                </span>
            </span>
            {person.lifespan && (
                <span className="text-muted-foreground mt-1 block text-xs">
                    {person.lifespan}
                </span>
            )}
        </>
    );
}

export function SheetPersonCard({ person, spouse, isFocus = false }: Props) {
    const body = cn(
        cardSurface,
        'bg-card text-card-foreground block border-l-4 pr-9 shadow-xs',
        strip[person.gender ?? 'unknown'],
    );

    return (
        <div className="relative h-full">
            {isFocus ? (
                <div className={cn(body, 'ring-primary ring-2')}>
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
                className="focus-visible:ring-ring absolute top-1 right-1 size-7"
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
