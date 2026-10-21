/** Label and value of one fact in a card's read-only view. */
export function Field({
    label,
    value,
    emptyText = 'brak danych',
}: {
    label: string;
    value: string | null;
    emptyText?: string;
}) {
    return (
        <div>
            <p className="text-muted-foreground text-sm">{label}</p>
            <p className={value ? undefined : 'text-muted-foreground'}>
                {value ?? emptyText}
            </p>
        </div>
    );
}
