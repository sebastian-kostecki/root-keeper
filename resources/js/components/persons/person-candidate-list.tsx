import { Link } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { index as personsIndex } from '@/routes/persons';
import type { RelationCandidate } from '@/types';

type Props = {
    loading: boolean;
    loaded: boolean;
    list: RelationCandidate[] | undefined;
    filter: string;
    onFilterChange: (filter: string) => void;
    onSelect: (candidateId: number) => void;
    getBlockedReason: (candidate: RelationCandidate) => string | null;
    processingId: number | null;
};

function matchesFilter(displayName: string, filter: string): boolean {
    const words = filter.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const haystack = displayName.toLowerCase();

    return words.every((word) => haystack.includes(word));
}

/**
 * The filterable list of other people in the tree used by the relative and
 * marriage pickers: load-on-open skeleton, an empty state pointing at
 * "Dodaj osobę", a name filter, and disabled rows carrying a reason.
 */
export function PersonCandidateList({
    loading,
    loaded,
    list,
    filter,
    onFilterChange,
    onSelect,
    getBlockedReason,
    processingId,
}: Props) {
    if (loading) {
        return (
            <div className="space-y-2">
                {Array.from({ length: 4 }).map((_, index) => (
                    <Skeleton key={index} className="h-10 w-full" />
                ))}
            </div>
        );
    }

    if (!loaded || list === undefined) {
        return (
            <p className="text-muted-foreground text-sm">
                Nie udało się wczytać osób. Zamknij okno i spróbuj ponownie.
            </p>
        );
    }

    if (list.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                Nie masz jeszcze innych osób.{' '}
                <Link
                    href={personsIndex()}
                    className="text-primary underline-offset-4 hover:underline"
                >
                    Dodaj osobę
                </Link>
                .
            </p>
        );
    }

    const filtered = list.filter((candidate) =>
        matchesFilter(candidate.display_name, filter),
    );

    return (
        <div className="space-y-3">
            <Input
                type="search"
                placeholder="Szukaj po imieniu lub nazwisku"
                aria-label="Szukaj osoby"
                value={filter}
                onChange={(event) => onFilterChange(event.target.value)}
            />

            <div className="max-h-96 space-y-1 overflow-y-auto">
                {filtered.map((candidate) => {
                    const blockedReason = getBlockedReason(candidate);

                    return (
                        <button
                            key={candidate.id}
                            type="button"
                            disabled={
                                blockedReason !== null || processingId !== null
                            }
                            onClick={() => onSelect(candidate.id)}
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
    );
}
