import { router } from '@inertiajs/react';
import type * as React from 'react';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { show as treeShow } from '@/routes/tree';
import type { SheetUnion } from '@/types';

type Props = {
    focusId: number;
    unions: SheetUnion[];
    selected: string | null;
    action?: React.ReactNode;
};

export function UnionSwitch({ focusId, unions, selected, action }: Props) {
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
        <div className="flex flex-wrap items-stretch gap-2">
            <ToggleGroup
                type="single"
                variant="outline"
                value={selected ?? ''}
                onValueChange={(value) => value && choose(value)}
                aria-label="Związek"
                className="flex-wrap items-stretch justify-start gap-2"
            >
                {unions.map((union) => (
                    <ToggleGroupItem
                        key={union.key}
                        value={union.key}
                        className="data-[state=on]:border-primary data-[state=on]:ring-primary h-auto flex-col items-start rounded-md px-3 py-2 text-left whitespace-normal data-[state=on]:ring-1 data-[variant=outline]:border-l"
                    >
                        <span>{union.label}</span>
                        <span className="text-muted-foreground text-xs font-normal">
                            Dzieci: {union.children_count}
                        </span>
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
            {action}
        </div>
    );
}
