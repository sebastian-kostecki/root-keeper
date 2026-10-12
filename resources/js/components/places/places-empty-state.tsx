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
    { icon: ReactNode; description: string }
> = {
    miejscowosci: {
        icon: <MapPinIcon />,
        description: 'Dodaj pierwszą miejscowość: nazwę i krótki opis.',
    },
    parafie: {
        icon: <ChurchIcon />,
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
            title="Spis jest pusty"
            description={emptyTreeCopy[kind].description}
            action={action}
        />
    );
}

/** Nothing matches the search. `other` is reserved for the cross-kind hint (phase 3). */
export function PlacesNoResults({
    kind,
    query,
    onClear,
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
                <Button variant="outline" onClick={onClear}>
                    Wyczyść wyszukiwanie
                </Button>
            }
        />
    );
}
