import { Link, router } from '@inertiajs/react';
import { EllipsisIcon } from 'lucide-react';
import { useState } from 'react';
import { DeletePersonDialog } from '@/components/persons/delete-person-dialog';
import { PersonAvatar } from '@/components/persons/person-avatar';
import {
    useGuardedClick,
    useSectionEditor,
} from '@/components/persons/section-editor';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSetRootPerson } from '@/hooks/use-set-root-person';
import { formatAge } from '@/lib/age';
import { formatLifespan } from '@/lib/lifespan';
import { show as treeShow } from '@/routes/tree';
import type { PersonPreview } from '@/types';

type Props = {
    person: PersonPreview;
    isRootPerson: boolean;
};

/** Lifespan with the age in brackets, as shown under the panel title; null without dates. */
export function personLifespan(person: PersonPreview): string | null {
    const baseLifespan = formatLifespan(person.birth?.date, person.death?.date);

    return baseLifespan && person.age
        ? `${baseLifespan} (${formatAge(person.age)})`
        : baseLifespan;
}

/** Identity row of the person panel: avatar and chips. Name and lifespan are the panel title and description. */
export function PersonModalHeader({ person, isRootPerson }: Props) {
    return (
        <div className="flex items-center gap-4 pb-4">
            <PersonAvatar
                name={person.display_name}
                gender={person.gender?.value ?? null}
            />

            {(person.gender || person.religion || isRootPerson) && (
                <div className="flex flex-wrap gap-1.5">
                    {person.gender && (
                        <Badge variant="secondary">{person.gender.label}</Badge>
                    )}
                    {person.religion && (
                        <Badge variant="secondary">
                            {person.religion.label}
                        </Badge>
                    )}
                    {isRootPerson && <Badge>Probant</Badge>}
                </div>
            )}
        </div>
    );
}

/** Header actions of the person panel: "Pokaż na drzewie" and the "⋯" menu. */
export function PersonPanelActions({ person, isRootPerson }: Props) {
    const { setRootPerson, processing } = useSetRootPerson(person.id);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const { requestExit } = useSectionEditor();
    const guardClick = useGuardedClick();
    const treeHref = treeShow(person.id, { query: { person: person.id } });

    return (
        <div className="flex shrink-0 items-center gap-1.5">
            <Button
                variant="outline"
                size="sm"
                className="hidden sm:inline-flex"
                asChild
            >
                <Link
                    href={treeHref}
                    onClick={(event) =>
                        guardClick(event, () => router.visit(treeHref))
                    }
                >
                    Pokaż na drzewie
                </Link>
            </Button>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        aria-label="Więcej działań"
                    >
                        <EllipsisIcon />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem asChild>
                        <Link
                            href={treeHref}
                            onClick={(event) =>
                                guardClick(event, () => router.visit(treeHref))
                            }
                        >
                            Pokaż na drzewie
                        </Link>
                    </DropdownMenuItem>
                    {!isRootPerson && (
                        <DropdownMenuItem
                            disabled={processing}
                            onSelect={() => requestExit(setRootPerson)}
                        >
                            Ustaw jako probanta
                        </DropdownMenuItem>
                    )}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        onSelect={() =>
                            requestExit(() => setIsDeleteOpen(true))
                        }
                    >
                        Usuń osobę…
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <DeletePersonDialog
                person={person}
                isRootPerson={isRootPerson}
                open={isDeleteOpen}
                onOpenChange={setIsDeleteOpen}
            />
        </div>
    );
}
