import { SearchIcon } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    ariaLabel: string;
    busy?: boolean;
    busyLabel?: string;
    maxLength?: number;
    className?: string;
};

/** Search input with the icon on the left; while `busy` the icon is replaced by a spinner. */
export function SearchField({
    value,
    onChange,
    placeholder,
    ariaLabel,
    busy = false,
    busyLabel = 'Wyszukiwanie',
    maxLength,
    className,
}: Props) {
    return (
        <div className={cn('relative flex-1', className)}>
            {busy ? (
                <Spinner
                    aria-hidden="true"
                    aria-label={busyLabel}
                    className="text-muted-foreground absolute top-1/2 left-3 -translate-y-1/2"
                />
            ) : (
                <SearchIcon
                    aria-hidden="true"
                    className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                />
            )}
            <Input
                type="search"
                placeholder={placeholder}
                aria-label={ariaLabel}
                maxLength={maxLength}
                className="pl-9"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}
