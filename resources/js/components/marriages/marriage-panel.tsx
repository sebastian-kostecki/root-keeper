import { usePage } from '@inertiajs/react';
import { useId } from 'react';
import type { ReactNode } from 'react';
import { EventSourceStatus } from '@/components/persons/event-source-status';
import { Field } from '@/components/persons/fact-field';
import { MarriageFields } from '@/components/persons/person-section-fields';
import { PersonPreviewLink } from '@/components/persons/person-preview-link';
import { RemoveMarriageDialog } from '@/components/persons/remove-marriage-dialog';
import { SectionCard } from '@/components/persons/section-card';
import {
    UnsavedChangesBar,
    useSectionEditor,
} from '@/components/persons/section-editor';
import type { SectionId } from '@/components/persons/section-editor';
import { SourceGroup } from '@/components/persons/source-group';
import { SidePanel } from '@/components/side-panel';
import { Button } from '@/components/ui/button';
import { useMarriagePanel } from '@/hooks/use-marriage-panel';
import { useSectionSave } from '@/hooks/use-section-save';
import { genderCard } from '@/lib/gender-styles';
import { formatLifespan } from '@/lib/lifespan';
import {
    marriageFieldDefaults,
    marriageSaveRequest,
} from '@/lib/person-payload';
import type { SectionValues } from '@/lib/person-payload';
import { formatPartialDate } from '@/lib/partial-date';
import { cn } from '@/lib/utils';
import type { PersonSummary } from '@/types';

/** Only the marriage's own fields (`marriage.*`) belong to the card. */
function belongsToMarriage(_section: SectionId, key: string): boolean {
    return key.startsWith('marriage.');
}

function PanelRegion({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    const headingId = useId();

    return (
        <section aria-labelledby={headingId} className="space-y-3">
            <h3
                id={headingId}
                className="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
            >
                {title}
            </h3>
            {children}
        </section>
    );
}

function PersonLink({ person }: { person: PersonSummary }) {
    const lifespan = formatLifespan(person.birth_date, person.death_date);

    return (
        <li
            className={cn(
                'rounded-xl border',
                genderCard[person.gender ?? 'unknown'],
            )}
        >
            <PersonPreviewLink
                personId={person.id}
                className="focus-visible:ring-ring flex min-w-0 items-center gap-3 rounded-xl px-3 py-2.5 focus-visible:ring-[3px] focus-visible:outline-none"
            >
                <span className="min-w-0 flex-1">
                    <span className="block font-medium [overflow-wrap:anywhere]">
                        {person.display_name}
                    </span>
                    {lifespan && (
                        <span className="text-tree-meta block text-xs">
                            {lifespan}
                        </span>
                    )}
                </span>
            </PersonPreviewLink>
        </li>
    );
}

/**
 * Side panel of one marriage, hosted by the layout beside the page; open whenever
 * the page carries a `marriagePanel`.
 */
export function MarriagePanel() {
    const { marriagePanel, availableSources } = usePage().props;
    const { close } = useMarriagePanel();
    const { requestExit } = useSectionEditor();
    const { savingId, errorsFor, strayErrorFor, save } =
        useSectionSave(belongsToMarriage);

    if (marriagePanel === null) {
        return null;
    }

    const { marriage, spouses, children } = marriagePanel;
    const [firstSpouse, secondSpouse] = spouses;
    const cardId: SectionId = `marriage-${marriage.id}`;
    const date = formatPartialDate(marriage.date_parts, 'long');

    const saveMarriage = (values: SectionValues): Promise<boolean> => {
        const [url, payload] = marriageSaveRequest(marriage.id, values);

        return save(cardId, url, payload);
    };

    return (
        <SidePanel
            variant="standard"
            title={`${firstSpouse.display_name} i ${secondSpouse.display_name}`}
            description={
                marriage.date
                    ? `Małżeństwo · ślub ${marriage.date}`
                    : 'Małżeństwo, data nieznana'
            }
            contentKey={marriage.id}
            onRequestClose={() => requestExit(close)}
            actions={
                <RemoveMarriageDialog
                    marriage={marriage}
                    personName={firstSpouse.display_name}
                    spouseName={secondSpouse.display_name}
                    trigger={
                        <Button type="button" variant="outline" size="sm">
                            Usuń małżeństwo
                        </Button>
                    }
                />
            }
        >
            <div className="space-y-6">
                <PanelRegion title="Małżonkowie">
                    <ul className="space-y-2">
                        {spouses.map((spouse) => (
                            <PersonLink key={spouse.id} person={spouse} />
                        ))}
                    </ul>
                </PanelRegion>

                <div className="space-y-3">
                    <SectionCard
                        id={cardId}
                        title="Ślub"
                        badge={
                            <EventSourceStatus
                                kinds={marriage.confirmed_kinds}
                            />
                        }
                        processing={savingId === cardId}
                        error={strayErrorFor(cardId)}
                        onSubmit={saveMarriage}
                        view={
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field
                                    label="Data"
                                    value={date === '' ? null : date}
                                />
                                <Field label="Miejsce" value={marriage.place} />
                            </div>
                        }
                        edit={
                            <MarriageFields
                                errors={errorsFor(cardId)}
                                defaultValue={marriageFieldDefaults(marriage)}
                            />
                        }
                    />
                </div>

                <SourceGroup
                    label="Źródła ślubu"
                    event={marriage}
                    availableSources={availableSources}
                />

                <PanelRegion title="Dzieci">
                    {children.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            Brak wspólnych dzieci
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {children.map((child) => (
                                <PersonLink key={child.id} person={child} />
                            ))}
                        </ul>
                    )}
                </PanelRegion>
            </div>

            <UnsavedChangesBar />
        </SidePanel>
    );
}
