import { SearchXIcon, UsersIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';

function EmptyState({
    icon,
    title,
    description,
    action,
}: {
    icon: ReactNode;
    title: string;
    description: string;
    action: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-14 text-center">
            <span
                aria-hidden="true"
                className="bg-muted text-muted-foreground flex size-12 items-center justify-center rounded-full [&>svg]:size-6"
            >
                {icon}
            </span>
            <div className="max-w-full space-y-1">
                <h2 className="font-semibold">{title}</h2>
                <p className="text-muted-foreground text-sm break-words">
                    {description}
                </p>
            </div>
            {action}
        </div>
    );
}

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
