import { Form } from '@inertiajs/react';
import { useState } from 'react';
import MarriageController from '@/actions/App/Http/Controllers/MarriageController';
import InputError from '@/components/input-error';
import { PartialDateFields } from '@/components/persons/partial-date-fields';
import { PersonCandidateList } from '@/components/persons/person-candidate-list';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useRelationCandidates } from '@/hooks/use-relation-candidates';
import type { Marriage, PersonPreview, RelationCandidate } from '@/types';

type Props =
    | {
          mode: 'create';
          person: PersonPreview;
          candidates: RelationCandidate[] | undefined;
          trigger: React.ReactNode;
      }
    | {
          mode: 'edit';
          person: PersonPreview;
          marriage: Marriage;
          trigger: React.ReactNode;
      };

export function MarriageFormDialog(props: Props) {
    const { person, trigger } = props;

    const [open, setOpen] = useState(false);
    const [filter, setFilter] = useState('');
    const [selectedSpouse, setSelectedSpouse] =
        useState<RelationCandidate | null>(null);
    const { list, loading, loaded, load } = useRelationCandidates(
        props.mode === 'create' ? props.candidates : undefined,
    );

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setFilter('');

            if (props.mode === 'create') {
                setSelectedSpouse(null);
                load();
            }
        }
    };

    const showPicker = props.mode === 'create' && selectedSpouse === null;

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {props.mode === 'create'
                        ? 'Dodaj małżeństwo'
                        : 'Edytuj małżeństwo'}
                </DialogTitle>

                {showPicker ? (
                    <>
                        <DialogDescription>
                            Wybierz osobę z Twojego drzewa, którą chcesz
                            powiązać.
                        </DialogDescription>

                        <PersonCandidateList
                            loading={loading}
                            loaded={loaded}
                            list={list}
                            filter={filter}
                            onFilterChange={setFilter}
                            onSelect={(candidateId) => {
                                const candidate = list?.find(
                                    (item) => item.id === candidateId,
                                );

                                if (candidate) {
                                    setSelectedSpouse(candidate);
                                }
                            }}
                            getBlockedReason={(candidate) =>
                                candidate.as_spouse_blocked_reason
                            }
                            processingId={null}
                        />

                        <div className="flex justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Zamknij
                            </Button>
                        </div>
                    </>
                ) : (
                    <Form
                        {...(props.mode === 'create'
                            ? MarriageController.store.form()
                            : MarriageController.update.form(
                                  props.marriage.id,
                              ))}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogDescription>
                                    Podaj datę i miejsce ślubu.
                                </DialogDescription>

                                {props.mode === 'create' && selectedSpouse && (
                                    <>
                                        <input
                                            type="hidden"
                                            name="person_id"
                                            value={person.id}
                                        />
                                        <input
                                            type="hidden"
                                            name="spouse_id"
                                            value={selectedSpouse.id}
                                        />

                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm">
                                                {selectedSpouse.display_name}
                                            </p>
                                            <Button
                                                type="button"
                                                variant="link"
                                                size="sm"
                                                onClick={() =>
                                                    setSelectedSpouse(null)
                                                }
                                            >
                                                Zmień
                                            </Button>
                                        </div>

                                        <InputError
                                            message={errors.spouse_id}
                                        />
                                    </>
                                )}

                                {props.mode === 'edit' && (
                                    <p className="text-sm">
                                        {props.marriage.spouse?.display_name ??
                                            'Nieznany małżonek'}
                                    </p>
                                )}

                                <PartialDateFields
                                    prefix="marriage"
                                    errors={errors}
                                    defaultValue={
                                        props.mode === 'edit'
                                            ? {
                                                  ...props.marriage.date_parts,
                                                  place: props.marriage.place,
                                              }
                                            : undefined
                                    }
                                    defaultChecked={
                                        props.mode === 'edit'
                                            ? props.marriage.date_parts
                                                  .is_approximate
                                            : undefined
                                    }
                                />

                                <DialogFooter className="gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={() => setOpen(false)}
                                    >
                                        Anuluj
                                    </Button>

                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            (props.mode === 'create' &&
                                                !selectedSpouse)
                                        }
                                    >
                                        Zapisz
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}
