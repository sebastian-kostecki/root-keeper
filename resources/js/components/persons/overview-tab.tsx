import { PencilIcon } from 'lucide-react';
import { EventSourceStatus } from '@/components/persons/event-source-status';
import { MarriageFormDialog } from '@/components/persons/marriage-form-dialog';
import { PersonFormDialog } from '@/components/persons/person-form-dialog';
import { ScanPickerDialog } from '@/components/scans/scan-picker-dialog';
import { Button } from '@/components/ui/button';
import { formatPartialDate } from '@/lib/partial-date';
import { pluralPhrase } from '@/lib/plural';
import type {
    Marriage,
    PersonFormEvent,
    PersonPreviewPayload,
    Scan,
    SourcedPersonEvent,
} from '@/types';

type Props = {
    preview: PersonPreviewPayload;
    availableScans?: Scan[];
};

function EditPersonPencil({
    preview,
    label,
}: {
    preview: PersonPreviewPayload;
    label: string;
}) {
    return (
        <PersonFormDialog
            mode="edit"
            person={preview.personForm}
            genders={preview.genders}
            religions={preview.religions}
            trigger={
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-7"
                    aria-label={label}
                    title={label}
                >
                    <PencilIcon />
                </Button>
            }
        />
    );
}

function SectionHeading({
    title,
    action,
}: {
    title: string;
    action?: React.ReactNode;
}) {
    return (
        <div className="flex min-h-7 items-center justify-between gap-2">
            <h3 className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">
                {title}
            </h3>
            {action}
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

type FactCardProps = {
    title: string;
    event: SourcedPersonEvent;
    parts: PersonFormEvent | Marriage['date_parts'] | null;
    availableScans: Scan[] | undefined;
    action?: React.ReactNode;
};

function FactCard({
    title,
    event,
    parts,
    availableScans,
    action,
}: FactCardProps) {
    const date = parts ? formatPartialDate(parts, 'long') : '';
    const scanCount = event.scans.length;

    return (
        <section className="bg-card space-y-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h4 className="font-medium">{title}</h4>
                <div className="flex items-center gap-1.5">
                    <EventSourceStatus isConfirmed={event.is_confirmed} />
                    {action}
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Data" value={date === '' ? null : date} />
                <Field label="Miejsce" value={event.place} />
            </div>

            <div className="border-t pt-3 text-sm">
                {scanCount > 0 ? (
                    <p className="text-muted-foreground">
                        {scanCount}{' '}
                        {pluralPhrase(scanCount, 'skan', 'skany', 'skanów')} ·{' '}
                        {event.scans[0].original_name}
                    </p>
                ) : (
                    <ScanPickerDialog
                        eventId={event.id}
                        linkedScanIds={[]}
                        availableScans={availableScans}
                        trigger={
                            <Button type="button" variant="outline" size="sm">
                                Powiąż skan
                            </Button>
                        }
                    />
                )}
            </div>
        </section>
    );
}

function EmptyFact({
    text,
    cta,
    preview,
}: {
    text: string;
    cta: string;
    preview: PersonPreviewPayload;
}) {
    return (
        <div className="space-y-3 rounded-lg border border-dashed p-4">
            <p className="text-muted-foreground text-sm">{text}</p>
            <PersonFormDialog
                mode="edit"
                person={preview.personForm}
                genders={preview.genders}
                religions={preview.religions}
                trigger={
                    <Button type="button" variant="outline" size="sm">
                        {cta}
                    </Button>
                }
            />
        </div>
    );
}

export function OverviewTab({ preview, availableScans }: Props) {
    const { person, personForm } = preview;

    const hasBirth =
        person.birth !== null &&
        (person.birth.date !== null || person.birth.place !== null);

    return (
        <div className="space-y-6">
            <div className="space-y-3">
                <SectionHeading
                    title="Dane osobowe"
                    action={
                        <EditPersonPencil
                            preview={preview}
                            label="Edytuj dane osobowe"
                        />
                    }
                />
                <div className="grid gap-3 sm:grid-cols-2">
                    <Field label="Imię" value={person.first_name} />
                    <Field label="Nazwisko" value={person.last_name} />
                    <Field label="Zamieszkanie" value={person.residence} />
                </div>
            </div>

            <div className="space-y-3">
                <SectionHeading title="Urodzenie" />
                {person.birth && hasBirth ? (
                    <FactCard
                        title="Urodzenie"
                        event={person.birth}
                        parts={personForm.birth}
                        availableScans={availableScans}
                        action={
                            <EditPersonPencil
                                preview={preview}
                                label="Edytuj urodzenie"
                            />
                        }
                    />
                ) : (
                    <EmptyFact
                        text="Nie znamy daty ani miejsca urodzenia."
                        cta="Uzupełnij urodzenie"
                        preview={preview}
                    />
                )}
            </div>

            {person.marriages.length > 0 && (
                <div className="space-y-3">
                    <SectionHeading title="Małżeństwa" />
                    {person.marriages.map((marriage) => (
                        <FactCard
                            key={marriage.id}
                            title={`Małżeństwo z ${marriage.spouse?.display_name ?? 'nieznanym małżonkiem'}`}
                            event={marriage}
                            parts={marriage.date_parts}
                            availableScans={availableScans}
                            action={
                                <MarriageFormDialog
                                    marriage={marriage}
                                    trigger={
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-7"
                                            aria-label="Edytuj małżeństwo"
                                            title="Edytuj małżeństwo"
                                        >
                                            <PencilIcon />
                                        </Button>
                                    }
                                />
                            }
                        />
                    ))}
                </div>
            )}

            <div className="space-y-3">
                <SectionHeading title="Zgon" />
                {person.death ? (
                    <FactCard
                        title="Zgon"
                        event={person.death}
                        parts={personForm.death}
                        availableScans={availableScans}
                        action={
                            <EditPersonPencil
                                preview={preview}
                                label="Edytuj zgon"
                            />
                        }
                    />
                ) : (
                    <EmptyFact
                        text="Osoba żyje albo nie wiemy, że zmarła."
                        cta="Oznacz zgon"
                        preview={preview}
                    />
                )}
            </div>
        </div>
    );
}
