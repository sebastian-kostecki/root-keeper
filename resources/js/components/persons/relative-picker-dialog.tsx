import { router } from '@inertiajs/react';
import { useState } from 'react';
import ParentChildRelationController from '@/actions/App/Http/Controllers/ParentChildRelationController';
import InputError from '@/components/input-error';
import { PersonCandidateList } from '@/components/persons/person-candidate-list';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { PersonPreview, RelationCandidate } from '@/types';

type Props = {
    kind: 'parent' | 'child';
    person: PersonPreview;
    candidates: RelationCandidate[] | undefined;
    trigger: React.ReactNode;
};

export function RelativePickerDialog({
    kind,
    person,
    candidates,
    trigger,
}: Props) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [processingId, setProcessingId] = useState<number | null>(null);
    const [error, setError] = useState<string | undefined>(undefined);
    const [filter, setFilter] = useState('');

    // The optional prop is dropped by any non-partial visit (e.g. the
    // redirect back after a validation error), so keep the last loaded list.
    const [list, setList] = useState(candidates);

    if (candidates !== undefined && candidates !== list) {
        setList(candidates);
    }

    const handleOpenChange = (nextOpen: boolean) => {
        setOpen(nextOpen);

        if (nextOpen) {
            setError(undefined);
            setFilter('');
            setLoaded(false);
            setLoading(true);
            router.reload({
                only: ['relationCandidates'],
                onSuccess: () => setLoaded(true),
                onFinish: () => setLoading(false),
            });
        }
    };

    const handleSelect = (candidateId: number) => {
        setProcessingId(candidateId);
        setError(undefined);

        const data =
            kind === 'parent'
                ? { parent_id: candidateId, child_id: person.id }
                : { parent_id: person.id, child_id: candidateId };

        router.post(ParentChildRelationController.store().url, data, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
            onError: (errors) =>
                setError(
                    errors.relation ?? errors.parent_id ?? errors.child_id,
                ),
            onFinish: () => setProcessingId(null),
        });
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {kind === 'parent' ? 'Dodaj rodzica' : 'Dodaj dziecko'}
                </DialogTitle>
                <DialogDescription>
                    Wybierz osobę z Twojego drzewa, którą chcesz powiązać.
                </DialogDescription>

                <PersonCandidateList
                    loading={loading}
                    loaded={loaded}
                    list={list}
                    filter={filter}
                    onFilterChange={setFilter}
                    onSelect={handleSelect}
                    getBlockedReason={(candidate) =>
                        kind === 'parent'
                            ? candidate.as_parent_blocked_reason
                            : candidate.as_child_blocked_reason
                    }
                    processingId={processingId}
                />

                <InputError message={error} />

                <div className="flex justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => setOpen(false)}
                    >
                        Zamknij
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
