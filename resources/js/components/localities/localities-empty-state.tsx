import { MapPinIcon, SearchXIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';

/** The tree has no localities yet; `action` is the „Dodaj miejscowość” button. */
export function LocalitiesEmptyTree({ action }: { action: ReactNode }) {
    return (
        <EmptyState
            icon={<MapPinIcon />}
            title="Spis jest pusty"
            description="Dodaj pierwszą miejscowość: nazwę i krótki opis."
            action={action}
        />
    );
}

/** Nothing matches the search. */
export function LocalitiesNoResults({
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
            description={`Nie znaleziono miejscowości pasujących do „${query}”.`}
            action={
                <Button variant="outline" onClick={onClear}>
                    Wyczyść wyszukiwanie
                </Button>
            }
        />
    );
}
