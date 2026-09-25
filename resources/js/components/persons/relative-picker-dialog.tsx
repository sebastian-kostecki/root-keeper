import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import ParentChildRelationController from '@/actions/App/Http/Controllers/ParentChildRelationController';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import type { PersonPreview, RelationCandidate } from '@/types';

type Props = {
    kind: 'parent' | 'child';
    person: PersonPreview;
    candidates: RelationCandidate[] | undefined;
    trigger: React.ReactNode;
};

function matchesFilter(displayName: string, filter: string): boolean {
    const words = filter.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const haystack = displayName.toLowerCase();

    return words.every((word) => haystack.includes(word));
}

export function RelativePickerDialog({
    kind,
    person,
    candidates,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [error, setError] = useState<string | undefined>(undefined);
    const [filter, setFilter] = useState('');

    // The optional prop is dropped by any non-partial visit (e.g. the
    // redirect back after a validation error), so keep the last loaded list.
    const [list, setList] = useState(candidates);

    if (candidates !== undefined && candidates !== list) {
        setList(candidates);
    }

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setError(undefined);
            setFilter('');
            setLoaded(false);
            setLoading(true);
            router.reload({
                only: ['relationCandidates'],
                onSuccess: () => setLoaded(true),
                onFinish: () => setLoading(false),
            });
        }
    };

    const handleSelect = (candidateId: number) => {
        setProcessingId(candidateId);
        setError(undefined);

        const data =
            kind === 'parent'
                ? { parent_id: candidateId, child_id: person.id }
                : { parent_id: person.id, child_id: candidateId };

        router.post(ParentChildRelationController.store().url, data, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: (errors) =>
                setError(
                    errors.relation ?? errors.parent_id ?? errors.child_id,
                ),
            onFinish: () => setProcessingId(null),
        });
    };

    const filtered = list?.filter((candidate) =>
        matchesFilter(candidate.display_name, filter),
    );

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {kind === 'parent' ? 'Dodaj rodzica' : 'Dodaj dziecko'}
                </DialogTitle>
                <DialogDescription>
                    Wybierz osobę z Twojego drzewa, którą chcesz powiązać.
                </DialogDescription>

                {loading ? (
                    <div className="space-y-2">
                        {Array.from({ length: 4 }).map((_, index) => (
                            <Skeleton key={index} className="h-10 w-full" />
                        ))}
                    </div>
                ) : !loaded || list === undefined ? (
                    <p className="text-muted-foreground text-sm">
                        Nie udało się wczytać osób. Zamknij okno i spróbuj
                        ponownie.
                    </p>
                ) : list.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        Nie masz jeszcze innych osób.{' '}
                        <Link
                            href={PersonController.create()}
                            className="text-primary underline-offset-4 hover:underline"
                        >
                            Dodaj osobę
                        </Link>
                        .
                    </p>
                ) : (
                    <div className="space-y-3">
                        <Input
                            type="search"
                            placeholder="Szukaj po imieniu lub nazwisku"
                            aria-label="Szukaj osoby"
                            value={filter}
                            onChange={(event) => setFilter(event.target.value)}
                        />

                        <div className="max-h-96 space-y-1 overflow-y-auto">
                            {filtered?.map((candidate) => {
                                const blockedReason =
                                    kind === 'parent'
                                        ? candidate.as_parent_blocked_reason
                                        : candidate.as_child_blocked_reason;

                                return (
                                    <button
                                        key={candidate.id}
                                        type="button"
                                        disabled={
                                            blockedReason !== null ||
                                            processingId !== null
                                        }
                                        onClick={() =>
                                            handleSelect(candidate.id)
                                        }
                                        className="hover:bg-accent flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent"
                                    >
                                        <span>
                                            {candidate.display_name}
                                            {candidate.birth_date && (
                                                <span className="text-muted-foreground ml-2">
                                                    {candidate.birth_date}
                                                </span>
                                            )}
                                        </span>
                                        {blockedReason && (
                                            <span className="text-muted-foreground text-xs">
                                                {blockedReason}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                <InputError message={error} />

                <div className="flex justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        Zamknij
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
