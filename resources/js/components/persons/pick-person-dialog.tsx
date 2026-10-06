import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    FormDialogBody,
    FormDialogContent,
    FormDialogFooter,
    FormDialogHeader,
} from '@/components/form-dialog';
import { PersonCandidateList } from '@/components/persons/person-candidate-list';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { useOptionalList } from '@/hooks/use-relation-candidates';
import type { PersonCandidate } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** People already chosen; their rows are disabled. */
    excludedIds: number[];
    /** Called with the picked person; nothing is posted. */
    onPick: (person: { id: number; display_name: string }) => void;
};

/**
 * A small picker of one existing person of the tree, for forms that keep the
 * choice themselves. Candidates load on open from the `personCandidates` prop.
 */
export function PickPersonDialog({
    open,
    onOpenChange,
    excludedIds,
    onPick,
}: Props) {
    const shared = usePage<{ personCandidates?: PersonCandidate[] }>().props
        .personCandidates;
    const { list, loading, loaded, load } = useOptionalList(
        'personCandidates',
        shared,
    );
    const [filter, setFilter] = useState('');
    const [wasOpen, setWasOpen] = useState(false);

    // Runs on every transition to open, whichever side opened the dialog.
    if (open !== wasOpen) {
        setWasOpen(open);

        if (open) {
            setFilter('');
        }
    }

    useEffect(() => {
        if (open) {
            load();
        }
    }, [open]);

    const handleSelect = (candidateId: number) => {
        const candidate = list?.find((person) => person.id === candidateId);

        if (candidate !== undefined) {
            onPick({ id: candidate.id, display_name: candidate.display_name });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <FormDialogContent>
                <FormDialogHeader
                    title="Dodaj osobę pochowaną"
                    description="Wybierz osobę z drzewa."
                />

                <FormDialogBody>
                    <PersonCandidateList
                        loading={loading}
                        loaded={loaded}
                        list={list}
                        filter={filter}
                        onFilterChange={setFilter}
                        onSelect={handleSelect}
                        getBlockedReason={(candidate) =>
                            excludedIds.includes(candidate.id)
                                ? 'Już na liście'
                                : null
                        }
                        processingId={null}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                Brak pasujących osób w drzewie.
                            </p>
                        }
                    />
                </FormDialogBody>

                <FormDialogFooter>
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={() => onOpenChange(false)}
                    >
                        Zamknij
                    </Button>
                </FormDialogFooter>
            </FormDialogContent>
        </Dialog>
    );
}
