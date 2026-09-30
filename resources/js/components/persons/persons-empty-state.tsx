import { SearchXIcon, UsersIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';

/** The tree has no people yet; `action` is the „Dodaj osobę” trigger. */
export function PersonsEmptyTree({ action }: { action: ReactNode }) {
    return (
        <EmptyState
            icon={<UsersIcon />}
            title="Nie dodano jeszcze żadnej osoby"
            description="Zacznij od dodania pierwszej osoby do drzewa."
            action={action}
        />
    );
}

export function PersonsNoResults({
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
            description={`Nie znaleziono osób pasujących do „${query}”.`}
            action={
                <Button variant="outline" onClick={onClear}>
                    Wyczyść wyszukiwanie
                </Button>
            }
        />
    );
}
