export function UnknownPersonCard({ label }: { label: string }) {
    return (
        <div className="text-muted-foreground flex h-full min-h-16 items-center rounded-md border border-dashed p-2 text-sm">
            {label}
        </div>
    );
}
