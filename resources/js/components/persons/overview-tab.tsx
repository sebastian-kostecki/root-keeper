import { Link, router } from '@inertiajs/react';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { EventSourceStatus } from '@/components/persons/event-source-status';
import { Field } from '@/components/persons/fact-field';
import {
    BirthFields,
    DeathFields,
    MarriageFields,
    PersonalFields,
} from '@/components/persons/person-section-fields';
import { ResidencesFields } from '@/components/persons/residences-fields';
import { SectionCard } from '@/components/persons/section-card';
import {
    useGuardedClick,
    useSectionEditor,
} from '@/components/persons/section-editor';
import type { SectionId } from '@/components/persons/section-editor';
import { SourcePickerDialog } from '@/components/sources/source-picker-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useSectionSave } from '@/hooks/use-section-save';
import { focusRing } from '@/lib/focus-ring';
import {
    marriageFieldDefaults,
    marriageSaveRequest,
    mergeSectionIntoPayload,
} from '@/lib/person-payload';
import type { PersonSection, SectionValues } from '@/lib/person-payload';
import { formatPartialDate } from '@/lib/partial-date';
import { cn } from '@/lib/utils';
import { formatYearRange } from '@/lib/year-range';
import type {
    Marriage,
    PersonFormEvent,
    PersonGravestone,
    PersonPreviewPayload,
    Source,
    SourcedPersonEvent,
} from '@/types';

type Props = {
    preview: PersonPreviewPayload;
    availableSources?: Source[];
};

const PERSONAL_KEYS = [
    'first_name',
    'last_name',
    'maiden_name',
    'gender',
    'religion',
];

/** Whether a server error key is rendered by the fields of the given section. */
function belongsToSection(id: SectionId, key: string): boolean {
    if (id === 'personal') {
        return PERSONAL_KEYS.includes(key);
    }

    if (id === 'residences') {
        return key === 'residences' || key.startsWith('residences.');
    }

    if (id === 'birth') {
        return key.startsWith('birth.');
    }

    if (id === 'death') {
        return (
            key === 'is_deceased' ||
            key.startsWith('death.') ||
            key.startsWith('burial.')
        );
    }

    return key.startsWith('marriage.');
}

function SectionHeading({ title }: { title: string }) {
    return (
        <div className="flex min-h-7 items-center justify-between gap-2">
            <h3 className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                {title}
            </h3>
        </div>
    );
}

type FactViewProps = {
    event: SourcedPersonEvent;
    parts: PersonFormEvent | Marriage['date_parts'] | null;
    availableSources: Source[] | undefined;
    /** Shown as „Miejsce pochowania” (the death section only). */
    burialPlace?: string | null;
    /** Shown as „Forma ślubu” (the marriage cards only). */
    marriageForm?: string | null;
    /** Gravestones linked from the death card. */
    gravestones?: PersonGravestone[];
};

function FactView({
    event,
    parts,
    availableSources,
    burialPlace,
    marriageForm,
    gravestones,
}: FactViewProps) {
    const date = parts ? formatPartialDate(parts, 'long') : '';
    const guardClick = useGuardedClick();

    return (
        <>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Data" value={date === '' ? null : date} />
                <Field label="Miejsce" value={event.place} />
                {burialPlace !== undefined && (
                    <Field label="Miejsce pochowania" value={burialPlace} />
                )}
                {marriageForm !== undefined && (
                    <Field
                        label="Forma ślubu"
                        value={marriageForm}
                        emptyText="nieznana"
                    />
                )}
            </div>

            <div className="border-t pt-3 text-sm">
                {event.sources.length > 0 ? (
                    <ul className="space-y-1.5">
                        {event.sources.map((source) => (
                            <li
                                key={source.id}
                                className="flex items-center gap-2"
                            >
                                <Link
                                    href={SourceController.index({
                                        query: { source: source.id },
                                    })}
                                    onClick={(clickEvent) =>
                                        guardClick(clickEvent, () =>
                                            router.visit(
                                                SourceController.index({
                                                    query: {
                                                        source: source.id,
                                                    },
                                                }),
                                            ),
                                        )
                                    }
                                    aria-label={`Otwórz źródło ${source.display_name}`}
                                    title={source.display_name}
                                    className={cn(
                                        'text-primary min-w-0 truncate rounded-sm underline-offset-4 hover:underline',
                                        focusRing,
                                    )}
                                >
                                    {source.display_name}
                                </Link>
                                <Badge variant="secondary" className="shrink-0">
                                    {source.kind_short_label}
                                </Badge>
                                {source.entry_number && (
                                    <span className="text-muted-foreground min-w-0 truncate text-xs">
                                        nr {source.entry_number}
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <SourcePickerDialog
                        eventId={event.id}
                        eventType={event.type}
                        eventYear={event.date_parts.year}
                        eventPlace={event.place}
                        linkedSourceIds={[]}
                        availableSources={availableSources}
                        trigger={
                            <Button type="button" variant="outline" size="sm">
                                Powiąż źródło
                            </Button>
                        }
                    />
                )}
            </div>

            {gravestones !== undefined && gravestones.length > 0 && (
                <div className="border-t pt-3">
                    <GravestoneLinks gravestones={gravestones} />
                </div>
            )}
        </>
    );
}

function GravestoneLinks({ gravestones }: { gravestones: PersonGravestone[] }) {
    const guardClick = useGuardedClick();

    if (gravestones.length === 0) {
        return null;
    }

    return (
        <ul className="space-y-1.5 text-sm">
            {gravestones.map((gravestone) => (
                <li key={gravestone.id}>
                    <span className="text-muted-foreground">Nagrobek: </span>
                    <Link
                        href={SourceController.index({
                            query: { source: gravestone.id },
                        })}
                        onClick={(clickEvent) =>
                            guardClick(clickEvent, () =>
                                router.visit(
                                    SourceController.index({
                                        query: { source: gravestone.id },
                                    }),
                                ),
                            )
                        }
                        className={cn(
                            'text-primary rounded-sm underline-offset-4 hover:underline',
                            focusRing,
                        )}
                    >
                        {gravestone.display_name}
                        {gravestone.cemetery_label &&
                            ` (${gravestone.cemetery_label})`}
                    </Link>
                </li>
            ))}
        </ul>
    );
}

function EmptyFact({
    text,
    cta,
    onStart,
}: {
    text: string;
    cta: string;
    onStart: () => void;
}) {
    return (
        <div className="space-y-3 rounded-lg border border-dashed p-4">
            <p className="text-muted-foreground text-sm">{text}</p>
            <Button type="button" variant="outline" size="sm" onClick={onStart}>
                {cta}
            </Button>
        </div>
    );
}

export function OverviewTab({ preview, availableSources }: Props) {
    const { person, personForm } = preview;
    const editor = useSectionEditor();
    const { savingId, errorsFor, strayErrorFor, save } =
        useSectionSave(belongsToSection);

    const residences = person.residences ?? [];
    const gravestones = person.gravestones ?? [];

    const hasBirth =
        person.birth !== null &&
        (person.birth.date !== null || person.birth.place !== null);

    /**
     * Saves one section. The payload is built from the `personForm` of this
     * render (the one on screen at submit time), never from a snapshot taken
     * when editing began.
     */
    const saveSection = (
        id: SectionId,
        values: SectionValues,
    ): Promise<boolean> => {
        const marriageId = id.startsWith('marriage-')
            ? Number(id.slice('marriage-'.length))
            : null;

        if (marriageId === null) {
            return save(
                id,
                PersonController.update.url(personForm.id),
                mergeSectionIntoPayload(
                    personForm,
                    id as PersonSection,
                    values,
                ),
            );
        }

        const [url, payload] = marriageSaveRequest(marriageId, values);

        return save(id, url, payload);
    };

    const cardProps = (id: SectionId) => ({
        id,
        processing: savingId === id,
        error: strayErrorFor(id),
        onSubmit: (values: SectionValues) => saveSection(id, values),
    });

    return (
        <div className="space-y-6">
            <div className="space-y-3">
                <SectionHeading title="Dane osobowe" />
                <SectionCard
                    {...cardProps('personal')}
                    title="Dane osobowe"
                    view={
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Imię" value={person.first_name} />
                            <Field label="Nazwisko" value={person.last_name} />
                            <Field
                                label="Nazwisko rodowe"
                                value={person.maiden_name}
                            />
                        </div>
                    }
                    edit={
                        <PersonalFields
                            religions={preview.religions}
                            errors={errorsFor('personal')}
                            person={personForm}
                        />
                    }
                />
            </div>

            <div className="space-y-3">
                <SectionHeading title="Zamieszkania" />
                {residences.length > 0 || editor.editing === 'residences' ? (
                    <SectionCard
                        {...cardProps('residences')}
                        title="Zamieszkania"
                        view={
                            <ul className="space-y-1.5">
                                {residences.map((residence) => (
                                    <li
                                        key={residence.id}
                                        className="flex flex-wrap items-baseline gap-x-2"
                                    >
                                        <span>{residence.locality.name}</span>
                                        <span className="text-muted-foreground text-sm">
                                            {formatYearRange(
                                                residence.year_from,
                                                residence.year_to,
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        }
                        edit={
                            <ResidencesFields
                                errors={errorsFor('residences')}
                                residences={personForm.residences}
                            />
                        }
                    />
                ) : (
                    <EmptyFact
                        text="Nie znamy miejsc zamieszkania."
                        cta="Dodaj zamieszkanie"
                        onStart={() => editor.start('residences')}
                    />
                )}
            </div>

            <div className="space-y-3">
                <SectionHeading title="Urodzenie" />
                {(person.birth && hasBirth) || editor.editing === 'birth' ? (
                    <SectionCard
                        {...cardProps('birth')}
                        title="Urodzenie"
                        badge={
                            person.birth && (
                                <EventSourceStatus
                                    kinds={person.birth.confirmed_kinds}
                                />
                            )
                        }
                        view={
                            person.birth && (
                                <FactView
                                    event={person.birth}
                                    parts={personForm.birth}
                                    availableSources={availableSources}
                                />
                            )
                        }
                        edit={
                            <BirthFields
                                errors={errorsFor('birth')}
                                birth={personForm.birth}
                            />
                        }
                    />
                ) : (
                    <EmptyFact
                        text="Nie znamy daty ani miejsca urodzenia."
                        cta="Uzupełnij urodzenie"
                        onStart={() => editor.start('birth')}
                    />
                )}
            </div>

            {person.marriages.length > 0 && (
                <div className="space-y-3">
                    <SectionHeading title="Małżeństwa" />
                    {person.marriages.map((marriage) => {
                        const id: SectionId = `marriage-${marriage.id}`;

                        return (
                            <SectionCard
                                key={marriage.id}
                                {...cardProps(id)}
                                title={`Małżeństwo z ${marriage.spouse?.display_name ?? 'nieznanym małżonkiem'}`}
                                badge={
                                    <EventSourceStatus
                                        kinds={marriage.confirmed_kinds}
                                    />
                                }
                                view={
                                    <FactView
                                        event={marriage}
                                        parts={marriage.date_parts}
                                        availableSources={availableSources}
                                        marriageForm={
                                            marriage.marriage_form_label
                                        }
                                    />
                                }
                                edit={
                                    <MarriageFields
                                        errors={errorsFor(id)}
                                        defaultValue={marriageFieldDefaults(
                                            marriage,
                                        )}
                                    />
                                }
                            />
                        );
                    })}
                </div>
            )}

            <div className="space-y-3">
                <SectionHeading title="Zgon" />
                {person.death || editor.editing === 'death' ? (
                    <SectionCard
                        {...cardProps('death')}
                        title="Zgon"
                        badge={
                            person.death && (
                                <EventSourceStatus
                                    kinds={person.death.confirmed_kinds}
                                />
                            )
                        }
                        view={
                            person.death && (
                                <FactView
                                    event={person.death}
                                    parts={personForm.death}
                                    availableSources={availableSources}
                                    burialPlace={person.burial_place}
                                    gravestones={gravestones}
                                />
                            )
                        }
                        edit={
                            <DeathFields
                                errors={errorsFor('death')}
                                death={personForm.death}
                                burial={personForm.burial}
                                burialSuggestion={personForm.burial_suggestion}
                                religions={preview.religions}
                                isDeceased={
                                    personForm.is_deceased ||
                                    editor.intent === 'mark-deceased'
                                }
                            />
                        }
                    />
                ) : (
                    <EmptyFact
                        text="Osoba żyje albo nie wiemy, że zmarła."
                        cta="Oznacz zgon"
                        onStart={() => editor.start('death', 'mark-deceased')}
                    />
                )}
                {!person.death && <GravestoneLinks gravestones={gravestones} />}
            </div>
        </div>
    );
}
