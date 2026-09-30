import type { ReactNode } from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';

export type SegmentedToggleItem<T extends string> = {
    value: T;
    label: string;
    icon?: ReactNode;
    count?: number;
    disabled?: boolean;
};

type Props<T extends string> = {
    value: T;
    onValueChange: (value: T) => void;
    ariaLabel: string;
    items: SegmentedToggleItem<T>[];
    iconOnlyBelowSm?: boolean;
    className?: string;
};

/** Outline single-choice toggle; clicking the active item does not clear it, so `onValueChange` never gets ''. */
export function SegmentedToggle<T extends string>({
    value,
    onValueChange,
    ariaLabel,
    items,
    iconOnlyBelowSm = false,
    className,
}: Props<T>) {
    return (
        <ToggleGroup
            type="single"
            variant="outline"
            aria-label={ariaLabel}
            value={value}
            onValueChange={(next) => {
                if (next !== '') {
                    onValueChange(next as T);
                }
            }}
            className={className}
        >
            {items.map((item) => (
                <ToggleGroupItem
                    key={item.value}
                    value={item.value}
                    disabled={item.disabled}
                    className="px-3"
                >
                    {item.icon}
                    <span className={cn(iconOnlyBelowSm && 'max-sm:sr-only')}>
                        {item.label}
                    </span>
                    {item.count !== undefined && (
                        <span className="text-xs font-normal tabular-nums">
                            {item.count}
                        </span>
                    )}
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
