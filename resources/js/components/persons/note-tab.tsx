import PersonNoteController from '@/actions/App/Http/Controllers/PersonNoteController';
import InputError from '@/components/input-error';
import { SectionCard } from '@/components/persons/section-card';
import type { SectionId } from '@/components/persons/section-editor';
import { Textarea } from '@/components/ui/textarea';
import { useSectionSave } from '@/hooks/use-section-save';
import type { PersonNotePayload, SectionValues } from '@/lib/person-payload';
import type { PersonPreview } from '@/types';

type Props = {
    person: PersonPreview;
};

function belongsToSection(id: SectionId, key: string): boolean {
    return id === 'note' && key === 'note';
}

/** Tab of the person panel holding the one free-text note of the person. */
export function NoteTab({ person }: Props) {
    const { savingId, errorsFor, strayErrorFor, save } =
        useSectionSave(belongsToSection);

    const saveNote = (values: SectionValues): Promise<boolean> => {
        const payload: PersonNotePayload = { note: values.note };

        return save(
            'note',
            PersonNoteController.update.url(person.id),
            payload,
        );
    };

    return (
        <SectionCard
            id="note"
            title="Notatka"
            processing={savingId === 'note'}
            error={strayErrorFor('note')}
            onSubmit={saveNote}
            view={
                person.note ? (
                    <p className="text-sm whitespace-pre-wrap">{person.note}</p>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        Brak notatki.
                    </p>
                )
            }
            edit={
                <div className="space-y-2">
                    <Textarea
                        name="note"
                        maxLength={5000}
                        defaultValue={person.note ?? ''}
                        aria-label="Treść notatki"
                        aria-invalid={errorsFor('note').note !== undefined}
                        className="min-h-40"
                    />
                    <InputError message={errorsFor('note').note} />
                </div>
            }
        />
    );
}
