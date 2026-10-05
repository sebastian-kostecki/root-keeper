import { ChurchIcon, SearchXIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';

/** The tree has no parishes yet; `action` is the „Dodaj parafię” button. */
export function ParishesEmptyTree({ action }: { action: ReactNode }) {
    return (
        <EmptyState
            icon={<ChurchIcon />}
            title="Spis jest pusty"
            description="Dodaj pierwszą parafię: nazwę, wyznanie i siedzibę."
            action={action}
        />
    );
}

/** Nothing matches the search. */
export function ParishesNoResults({
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
            description={`Nie znaleziono parafii pasujących do „${query}”.`}
            action={
                <Button variant="outline" onClick={onClear}>
                    Wyczyść wyszukiwanie
                </Button>
            }
        />
    );
}
