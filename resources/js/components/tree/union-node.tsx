import { router } from '@inertiajs/react';
import { ChevronsUpDown, Plus } from 'lucide-react';
import type { RefObject } from 'react';
import { useOptionalSectionEditor } from '@/components/persons/section-editor';
import { cardInteractive, triggerActive } from '@/components/tree/card-styles';
import { ZOOM_NO_PAN_CLASS } from '@/components/zoom-canvas';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useMarriagePanel } from '@/hooks/use-marriage-panel';
import { useSidePanelNavigation } from '@/hooks/use-side-panel-navigation';
import { addValueFor } from '@/lib/add-person-target';
import { pluralPhrase } from '@/lib/plural';
import { cn } from '@/lib/utils';
import { show as treeShow } from '@/routes/tree';
import type { AddSpouseTarget, SheetPerson, SheetUnion } from '@/types';

type Props = {
    focus: SheetPerson;
    unions: SheetUnion[];
    selected: string | null;
    addSpouse: AddSpouseTarget;
    /** The open add panel is for a spouse and this node is its target (the partner exists). */
    addActive: boolean;
    /** Attached to the trigger only while `addActive`, so the sheet can reveal it. */
    revealRef: RefObject<HTMLButtonElement | null>;
};

/** Status of a union; empty for an unknown partner. */
function unionStatus(union: SheetUnion): string {
    if (union.partner_id === null) {
        return '';
    }

    if (union.is_marriage) {
        return union.marriage_year
            ? `ślub ${union.marriage_year}`
            : 'ślub, data nieznana';
    }

    return 'bez ślubu';
}

/**
 * The node on the couple line: shows the selected union and opens a menu to
 * switch unions or add a spouse.
 */
export function UnionNode({
    focus,
    unions,
    selected,
    addSpouse,
    addActive,
    revealRef,
}: Props) {
    const { open: openMarriagePanel } = useMarriagePanel();
    const { open: openAddPanel } = useSidePanelNavigation('add');
    const editor = useOptionalSectionEditor();

    if (unions.length === 0) {
        return null;
    }

    const selectedIndex = unions.findIndex((union) => union.key === selected);
    const selectedUnion = selectedIndex === -1 ? null : unions[selectedIndex];
    const status = selectedUnion ? unionStatus(selectedUnion) : '';
    const position = `${selectedIndex + 1} z ${unions.length}`;
    const showPosition = unions.length > 1 && selectedUnion !== null;

    const choose = (key: string) => {
        const union = unions.find((candidate) => candidate.key === key);

        if (!union) {
            return;
        }

        router.visit(
            treeShow(focus.id, {
                mergeQuery: {
                    spouse:
                        union.partner_id === null
                            ? 'unknown'
                            : String(union.partner_id),
                },
            }),
            { preserveScroll: true },
        );
    };

    const ariaLabel = `Związek ${position}: ${
        status || selectedUnion?.partner_name || 'nieznany partner'
    }. Zmień związek lub dodaj małżonka`;

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        ref={addActive ? revealRef : undefined}
                        type="button"
                        aria-label={ariaLabel}
                        className={cn(
                            'bg-background hover:bg-accent data-[state=open]:border-primary inline-flex h-8.5 items-center gap-2 rounded-lg border px-2.5 text-xs whitespace-nowrap shadow-xs',
                            cardInteractive,
                            ZOOM_NO_PAN_CLASS,
                            addActive && triggerActive,
                        )}
                    >
                        {showPosition && (
                            <span className="text-muted-foreground">
                                {position}
                            </span>
                        )}
                        {status ? (
                            <span className="font-semibold">{status}</span>
                        ) : (
                            !showPosition && (
                                <span className="font-semibold">Związki</span>
                            )
                        )}
                        <ChevronsUpDown
                            className="text-muted-foreground size-3.5"
                            aria-hidden="true"
                        />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="center" className="min-w-64">
                    <DropdownMenuLabel>
                        Związki — {focus.display_name}
                    </DropdownMenuLabel>
                    <DropdownMenuRadioGroup
                        value={selected ?? ''}
                        onValueChange={choose}
                    >
                        {unions.map((union) => {
                            const detail = [
                                unionStatus(union),
                                pluralPhrase(
                                    union.children_count,
                                    '1 dziecko',
                                    `${union.children_count} dzieci`,
                                    `${union.children_count} dzieci`,
                                ),
                            ]
                                .filter((part) => part !== '')
                                .join(' · ');

                            return (
                                <DropdownMenuRadioItem
                                    key={union.key}
                                    value={union.key}
                                >
                                    <span className="flex flex-col">
                                        <span>
                                            {union.partner_name ??
                                                'Nieznany partner'}
                                        </span>
                                        <span className="text-muted-foreground text-xs">
                                            {detail}
                                        </span>
                                    </span>
                                </DropdownMenuRadioItem>
                            );
                        })}
                    </DropdownMenuRadioGroup>
                    {selectedUnion?.is_marriage &&
                        selectedUnion.marriage_id !== null && (
                            <DropdownMenuItem
                                onSelect={() => {
                                    const marriageId =
                                        selectedUnion.marriage_id;

                                    if (marriageId === null) {
                                        return;
                                    }

                                    if (editor === null) {
                                        openMarriagePanel(marriageId);

                                        return;
                                    }

                                    editor.requestExit(() =>
                                        openMarriagePanel(marriageId),
                                    );
                                }}
                            >
                                Szczegóły małżeństwa
                            </DropdownMenuItem>
                        )}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        onSelect={() => {
                            const value = addValueFor({
                                kind: 'spouse',
                                focus,
                                target: addSpouse,
                            });

                            // Open after the menu has released the page.
                            requestAnimationFrame(() => {
                                if (editor === null) {
                                    openAddPanel(value);

                                    return;
                                }

                                editor.requestExit(() => openAddPanel(value));
                            });
                        }}
                    >
                        <Plus className="size-4" aria-hidden="true" />
                        Dodaj małżonka
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </>
    );
}
