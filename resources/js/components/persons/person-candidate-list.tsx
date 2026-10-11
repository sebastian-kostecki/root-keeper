import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { index as personsIndex } from '@/routes/persons';

type CandidateRow = {
    id: number;
    display_name: string;
    birth_date: string | null;
};

type Props<T extends CandidateRow> = {
    loading: boolean;
    loaded: boolean;
    list: T[] | undefined;
    filter: string;
    onFilterChange: (filter: string) => void;
    onSelect: (candidateId: number) => void;
    /** Rows with a reason render disabled; omit to offer every row. */
    getBlockedReason?: (candidate: T) => string | null;
    processingId: number | null;
    /** Replaces the default "no other people yet" text. */
    emptyState?: React.ReactNode;
    /** Rendered under the list, also when the list is empty. */
    footer?: React.ReactNode;
    /** Offers "Spróbuj ponownie" when the list failed to load. */
    onRetry?: () => void;
};

function matchesFilter(displayName: string, filter: string): boolean {
    const words = filter.trim().toLowerCase().split(/\s+/).filter(Boolean);
    const haystack = displayName.toLowerCase();

    return words.every((word) => haystack.includes(word));
}

/**
 * The filterable list of other people in the tree used by the tree's
 * person pickers: load-on-open skeleton, an empty state pointing at
 * "Dodaj osobę", a name filter, and disabled rows carrying a reason.
 */
export function PersonCandidateList<T extends CandidateRow>({
    loading,
    loaded,
    list,
    filter,
    onFilterChange,
    onSelect,
    getBlockedReason,
    processingId,
    emptyState,
    footer,
    onRetry,
}: Props<T>) {
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
            <div className="flex flex-col items-start gap-3">
                <p className="text-muted-foreground text-sm">
                    Nie udało się wczytać osób.
                </p>
                {onRetry && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onRetry}
                    >
                        Spróbuj ponownie
                    </Button>
                )}
            </div>
        );
    }

    if (list.length === 0) {
        return (
            <div className="space-y-3">
                {emptyState ?? (
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
                )}
                {footer}
            </div>
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
                    const blockedReason = getBlockedReason?.(candidate) ?? null;

                    return (
                        <button
                            key={candidate.id}
                            type="button"
                            disabled={
                                blockedReason !== null || processingId !== null
                            }
                            onClick={() => onSelect(candidate.id)}
                            className="hover:bg-accent focus-visible:ring-ring/50 flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent"
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
                {filtered.length === 0 && emptyState}
            </div>

            {footer}
        </div>
    );
}
