import { FileSearchIcon, SearchXIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import type { SourcesKindFilter } from '@/lib/sources-index';

const kindNouns: Record<Exclude<SourcesKindFilter, 'wszystkie'>, string> = {
    akty: 'aktów',
    nagrobki: 'nagrobków',
    inne: 'innych źródeł',
};

/** The tree has no sources yet; `action` is the „Dodaj źródło” trigger. */
export function SourcesEmptyTree({ action }: { action: ReactNode }) {
    return (
        <EmptyState
            icon={<FileSearchIcon />}
            title="Brak źródeł"
            description="Zacznij od dodania aktu, zdjęcia nagrobka albo innego dokumentu."
            action={action}
        />
    );
}

/** Nothing matches the search in any kind. */
export function SourcesNoResults({
    query,
    onClear,
}: {
    query: string;
    onClear: () => void;
}) {
    return (
        <EmptyState
            icon={<SearchXIcon />}
            title="Brak wyników"
            description={`Nie znaleziono źródeł pasujących do „${query}”.`}
            action={
                <Button variant="outline" onClick={onClear}>
                    Wyczyść wyszukiwanie
                </Button>
            }
        />
    );
}

/** Nothing matches while a filter beyond the search is active; „Wyczyść filtry” clears them all. */
export function SourcesFilteredNoResults({
    query,
    onClearFilters,
}: {
    query: string;
    onClearFilters: () => void;
}) {
    return (
        <EmptyState
            icon={<SearchXIcon />}
            title="Brak wyników"
            description={
                query === ''
                    ? 'Żadne źródło nie pasuje do wybranych filtrów.'
                    : `Nie znaleziono źródeł pasujących do „${query}” i wybranych filtrów.`
            }
            action={
                <Button variant="outline" onClick={onClearFilters}>
                    Wyczyść filtry
                </Button>
            }
        />
    );
}

/**
 * The chosen kind has no match while other kinds have. With a place, year or archive filter active the
 * kind may be empty only because of that filter, so the text says so and „Wyczyść filtry” is offered too.
 */
export function SourcesEmptyKind({
    kind,
    query,
    hasOtherFilters,
    onShowAll,
    onClearFilters,
}: {
    kind: Exclude<SourcesKindFilter, 'wszystkie'>;
    query: string;
    hasOtherFilters: boolean;
    onShowAll: () => void;
    onClearFilters: () => void;
}) {
    const noun = kindNouns[kind];

    const description = hasOtherFilters
        ? query === ''
            ? `Wśród ${noun} nic nie pasuje do wybranych filtrów.`
            : `Wśród ${noun} nic nie pasuje do „${query}” i wybranych filtrów.`
        : query === ''
          ? `Nie masz jeszcze ${noun}.`
          : `Wśród ${noun} nic nie pasuje do „${query}”.`;

    return (
        <EmptyState
            icon={<SearchXIcon />}
            title="Brak wyników"
            description={description}
            action={
                <div className="flex flex-wrap justify-center gap-3">
                    <Button variant="outline" onClick={onShowAll}>
                        Pokaż wszystkie rodzaje
                    </Button>
                    {hasOtherFilters && (
                        <Button variant="outline" onClick={onClearFilters}>
                            Wyczyść filtry
                        </Button>
                    )}
                </div>
            }
        />
    );
}
