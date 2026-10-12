/** Label and value of one fact in a card's read-only view. */
export function Field({
    label,
    value,
}: {
    label: string;
    value: string | null;
}) {
    return (
        <div>
            <p className="text-muted-foreground text-sm">{label}</p>
            <p className={value ? undefined : 'text-muted-foreground'}>
                {value ?? 'brak danych'}
            </p>
        </div>
    );
}
