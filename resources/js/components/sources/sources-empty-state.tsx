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
            title="Nie dodano jeszcze żadnego źródła"
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

/** The chosen kind has no match while other kinds have. */
export function SourcesEmptyKind({
    kind,
    query,
    onShowAll,
}: {
    kind: Exclude<SourcesKindFilter, 'wszystkie'>;
    query: string;
    onShowAll: () => void;
}) {
    const noun = kindNouns[kind];

    return (
        <EmptyState
            icon={<SearchXIcon />}
            title="Brak wyników"
            description={
                query === ''
                    ? `Nie masz jeszcze ${noun}.`
                    : `Wśród ${noun} nic nie pasuje do „${query}”.`
            }
            action={
                <Button variant="outline" onClick={onShowAll}>
                    Pokaż wszystkie rodzaje
                </Button>
            }
        />
    );
}
