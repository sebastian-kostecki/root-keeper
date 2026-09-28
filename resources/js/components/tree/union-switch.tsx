import { router } from '@inertiajs/react';
import { useRef } from 'react';
import type * as React from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useCentreInScroll } from '@/hooks/use-centre-in-scroll';
import { show as treeShow } from '@/routes/tree';
import type { SheetUnion } from '@/types';

type Props = {
    focusId: number;
    unions: SheetUnion[];
    selected: string | null;
    action?: React.ReactNode;
};

export function UnionSwitch({ focusId, unions, selected, action }: Props) {
    const rowRef = useRef<HTMLDivElement>(null);
    const selectedRef = useRef<HTMLButtonElement>(null);

    useCentreInScroll(rowRef, selectedRef, selected ?? '');

    if (unions.length === 0) {
        return null;
    }

    const choose = (key: string) => {
        const union = unions.find((candidate) => candidate.key === key);

        if (!union) {
            return;
        }

        router.visit(
            treeShow(focusId, {
                query: {
                    spouse:
                        union.partner_id === null
                            ? 'unknown'
                            : String(union.partner_id),
                },
            }),
            { preserveScroll: true },
        );
    };

    return (
        <div
            ref={rowRef}
            className="flex flex-wrap items-stretch gap-2 max-md:flex-nowrap max-md:overflow-x-auto max-md:py-1"
        >
            <ToggleGroup
                type="single"
                variant="outline"
                value={selected ?? ''}
                onValueChange={(value) => value && choose(value)}
                aria-label="Związek"
                className="flex-wrap items-stretch justify-start gap-2 max-md:shrink-0 max-md:flex-nowrap"
            >
                {unions.map((union) => (
                    <ToggleGroupItem
                        key={union.key}
                        value={union.key}
                        ref={union.key === selected ? selectedRef : undefined}
                        className="data-[state=on]:border-primary data-[state=on]:ring-primary h-auto flex-col items-start rounded-md px-3 py-2 text-left whitespace-normal data-[state=on]:ring-1 data-[variant=outline]:border-l max-md:shrink-0"
                    >
                        <span>{union.label}</span>
                        <span className="text-muted-foreground text-xs font-normal">
                            Dzieci: {union.children_count}
                        </span>
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
            {action && (
                <div className="max-md:shrink-0 md:contents">{action}</div>
            )}
        </div>
    );
}
