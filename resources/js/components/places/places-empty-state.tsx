import {
    ChurchIcon,
    LandmarkIcon,
    MapPinIcon,
    SearchXIcon,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import type { PlacesKind } from '@/lib/places-index';

export type OtherKindAction = {
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
    cmentarze: {
        icon: <LandmarkIcon />,
        title: 'Brak cmentarzy',
        description:
            'Dodaj pierwszy cmentarz: miejscowość, a opcjonalnie nazwę i zarządcę.',
    },
};

const noResultsDescription: Record<PlacesKind, (query: string) => string> = {
    miejscowosci: (query) =>
        `Nie znaleziono miejscowości pasujących do „${query}”.`,
    parafie: (query) => `Nie znaleziono parafii pasujących do „${query}”.`,
    cmentarze: (query) => `Nie znaleziono cmentarzy pasujących do „${query}”.`,
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
 * Nothing matches the search in this list. `others` offers each list where the search does hit,
 * shown first as „Pokaż w … (n)”.
 */
export function PlacesNoResults({
    kind,
    query,
    onClear,
    others,
}: {
    kind: PlacesKind;
    query: string;
    onClear: () => void;
    others: OtherKindAction[];
}) {
    return (
        <EmptyState
            icon={<SearchXIcon />}
            title="Brak wyników"
            description={noResultsDescription[kind](query)}
            action={
                <div className="flex flex-wrap justify-center gap-2">
                    {others.map((other) => (
                        <Button
                            key={other.label}
                            variant="outline"
                            onClick={other.onShow}
                        >
                            {other.label} ({other.count})
                        </Button>
                    ))}
                    <Button variant="outline" onClick={onClear}>
                        Wyczyść wyszukiwanie
                    </Button>
                </div>
            }
        />
    );
}
