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
import { DialogDescription, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useSetRootPerson } from '@/hooks/use-set-root-person';
import { formatLifespan } from '@/lib/lifespan';
import { show as treeShow } from '@/routes/tree';
import type { PersonPreview } from '@/types';

type Props = {
    person: PersonPreview;
    isRootPerson: boolean;
    /** Optional element rendered above the identity row, e.g. a "Wstecz" button. */
    leading?: React.ReactNode;
};

/** Identity header of the person modal: avatar, name, lifespan, chips and actions. */
export function PersonModalHeader({ person, isRootPerson, leading }: Props) {
    const { setRootPerson, processing } = useSetRootPerson(person.id);
    const [isDeleteOpen, setIsDeleteOpen] = useState(false);
    const { requestExit } = useSectionEditor();
    const guardClick = useGuardedClick();
    const treeHref = treeShow(person.id);

    const lifespan = formatLifespan(person.birth?.date, person.death?.date);

    return (
        <div className="flex flex-col gap-3 px-6 pt-6 pr-12 pb-4">
            {leading}

            <div className="flex items-start gap-4">
                <PersonAvatar
                    name={person.display_name}
                    gender={person.gender?.value ?? null}
                />

                <div className="min-w-0 flex-1 space-y-1">
                    <DialogTitle className="text-xl leading-tight [overflow-wrap:anywhere]">
                        {person.display_name}
                    </DialogTitle>
                    <DialogDescription
                        className={lifespan ? undefined : 'sr-only'}
                    >
                        {lifespan ?? 'Podgląd osoby'}
                    </DialogDescription>

                    {(person.gender || person.religion || isRootPerson) && (
                        <div className="flex flex-wrap gap-1.5 pt-1">
                            {person.gender && (
                                <Badge variant="secondary">
                                    {person.gender.label}
                                </Badge>
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
                                        guardClick(event, () =>
                                            router.visit(treeHref),
                                        )
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
                </div>
            </div>

            <DeletePersonDialog
                person={person}
                isRootPerson={isRootPerson}
                open={isDeleteOpen}
                onOpenChange={setIsDeleteOpen}
            />
        </div>
    );
}
