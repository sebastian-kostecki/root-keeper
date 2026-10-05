import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import MarriageController from '@/actions/App/Http/Controllers/MarriageController';
import PersonController from '@/actions/App/Http/Controllers/PersonController';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { EventSourceStatus } from '@/components/persons/event-source-status';
import {
    BirthFields,
    DeathFields,
    MarriageFields,
    PersonalFields,
} from '@/components/persons/person-section-fields';
import { SectionCard } from '@/components/persons/section-card';
import {
    useGuardedClick,
    useSectionEditor,
} from '@/components/persons/section-editor';
import type { SectionId } from '@/components/persons/section-editor';
import { SourcePickerDialog } from '@/components/sources/source-picker-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { mergeSectionIntoPayload } from '@/lib/person-payload';
import type { PersonSection, SectionValues } from '@/lib/person-payload';
import { eventPayloadFromValues } from '@/lib/person-payload';
import { formatPartialDate } from '@/lib/partial-date';
import type {
    Marriage,
    PersonFormEvent,
    PersonPreviewPayload,
    Source,
    SourcedPersonEvent,
} from '@/types';

type Props = {
    preview: PersonPreviewPayload;
    availableSources?: Source[];
};

type Errors = Record<string, string>;

const PERSONAL_KEYS = [
    'first_name',
    'last_name',
    'maiden_name',
    'gender',
    'religion',
    'residence',
];

/** Whether a server error key is rendered by the fields of the given section. */
function belongsToSection(id: SectionId, key: string): boolean {
    if (id === 'personal') {
        return PERSONAL_KEYS.includes(key);
    }

    if (id === 'birth') {
        return key.startsWith('birth.');
    }

    if (id === 'death') {
        return key === 'is_deceased' || key.startsWith('death.');
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

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <p className="text-muted-foreground text-sm">{label}</p>
            <p className={value ? undefined : 'text-muted-foreground'}>
                {value ?? 'brak danych'}
            </p>
        </div>
    );
}

type FactViewProps = {
    event: SourcedPersonEvent;
    parts: PersonFormEvent | Marriage['date_parts'] | null;
    availableSources: Source[] | undefined;
};

function FactView({ event, parts, availableSources }: FactViewProps) {
    const date = parts ? formatPartialDate(parts, 'long') : '';
    const guardClick = useGuardedClick();

    return (
        <>
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Data" value={date === '' ? null : date} />
                <Field label="Miejsce" value={event.place} />
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
                                    className="text-primary focus-visible:ring-ring/50 min-w-0 truncate rounded-sm underline-offset-4 outline-none hover:underline focus-visible:ring-[3px]"
                                >
                                    {source.display_name}
                                </Link>
                                <Badge variant="secondary" className="shrink-0">
                                    {source.kind_short_label}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <SourcePickerDialog
                        eventId={event.id}
                        eventType={event.type}
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
        </>
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
    const [savingId, setSavingId] = useState<SectionId | null>(null);
    const [failure, setFailure] = useState<{
        id: SectionId;
        errors: Errors;
    } | null>(null);

    const hasBirth =
        person.birth !== null &&
        (person.birth.date !== null || person.birth.place !== null);

    /**
     * Saves one section. The payload is built from the `personForm` of this
     * render (the one on screen at submit time), never from a snapshot taken
     * when editing began.
     */
    const save = (id: SectionId, values: SectionValues): Promise<boolean> =>
        new Promise((resolve) => {
            const marriageId = id.startsWith('marriage-')
                ? Number(id.slice('marriage-'.length))
                : null;

            const [url, payload] =
                marriageId === null
                    ? ([
                          PersonController.update.url(personForm.id),
                          mergeSectionIntoPayload(
                              personForm,
                              id as PersonSection,
                              values,
                          ),
                      ] as const)
                    : ([
                          MarriageController.update.url(marriageId),
                          {
                              marriage: eventPayloadFromValues(
                                  'marriage',
                                  values,
                              ),
                          },
                      ] as const);

            router.put(url, payload, {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setSavingId(id),
                onSuccess: () => {
                    setFailure(null);
                    resolve(true);
                },
                onError: (errors) => {
                    setFailure({ id, errors });
                    resolve(false);
                },
                onFinish: () => {
                    setSavingId(null);
                    resolve(false);
                },
            });
        });

    const errorsFor = (id: SectionId): Errors =>
        failure !== null && failure.id === id && editor.editing === id
            ? failure.errors
            : {};

    /** First server error the section's fields do not show. */
    const strayErrorFor = (id: SectionId): string | undefined =>
        Object.entries(errorsFor(id)).find(
            ([key]) => !belongsToSection(id, key),
        )?.[1];

    const cardProps = (id: SectionId) => ({
        id,
        processing: savingId === id,
        error: strayErrorFor(id),
        onSubmit: (values: SectionValues) => save(id, values),
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
                            <Field
                                label="Zamieszkanie"
                                value={person.residence}
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
                                    />
                                }
                                edit={
                                    <MarriageFields
                                        errors={errorsFor(id)}
                                        defaultValue={{
                                            ...marriage.date_parts,
                                            place: marriage.place,
                                        }}
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
                                />
                            )
                        }
                        edit={
                            <DeathFields
                                errors={errorsFor('death')}
                                death={personForm.death}
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
            </div>
        </div>
    );
}
