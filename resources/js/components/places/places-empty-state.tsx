import { ChurchIcon, MapPinIcon, SearchXIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import type { PlacesKind } from '@/lib/places-index';

type OtherKindAction = {
    label: string;
    count: number;
    onShow: () => void;
};

const emptyTreeCopy: Record<
    PlacesKind,
    { icon: ReactNode; title: string; description: string }
> = {
    miejscowosci: {
        icon: <MapPinIcon />,
        title: 'Brak miejscowości',
        description: 'Dodaj pierwszą miejscowość: nazwę i krótki opis.',
    },
    parafie: {
        icon: <ChurchIcon />,
        title: 'Brak parafii',
        description: 'Dodaj pierwszą parafię: nazwę, wyznanie i siedzibę.',
    },
};

const noResultsDescription: Record<PlacesKind, (query: string) => string> = {
    miejscowosci: (query) =>
        `Nie znaleziono miejscowości pasujących do „${query}”.`,
    parafie: (query) => `Nie znaleziono parafii pasujących do „${query}”.`,
};

/** The tree has no records of this kind yet; `action` is the „Dodaj …” button. */
export function PlacesEmptyTree({
    kind,
    action,
}: {
    kind: PlacesKind;
    action: ReactNode;
}) {
    return (
        <EmptyState
            icon={emptyTreeCopy[kind].icon}
            title={emptyTreeCopy[kind].title}
            description={emptyTreeCopy[kind].description}
            action={action}
        />
    );
}

/**
 * Nothing matches the search in this list. `other` offers the list where the search does hit,
 * shown first as „Pokaż w … (n)”.
 */
export function PlacesNoResults({
    kind,
    query,
    onClear,
    other,
}: {
    kind: PlacesKind;
    query: string;
    onClear: () => void;
    other?: OtherKindAction;
}) {
    return (
        <EmptyState
            icon={<SearchXIcon />}
            title="Brak wyników"
            description={noResultsDescription[kind](query)}
            action={
                <div className="flex flex-wrap justify-center gap-2">
                    {other !== undefined && (
                        <Button variant="outline" onClick={other.onShow}>
                            {other.label} ({other.count})
                        </Button>
                    )}
                    <Button variant="outline" onClick={onClear}>
                        Wyczyść wyszukiwanie
                    </Button>
                </div>
            }
        />
    );
}
