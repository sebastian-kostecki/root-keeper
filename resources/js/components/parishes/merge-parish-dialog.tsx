import { router } from '@inertiajs/react';
import { useState } from 'react';
import ParishMergeController from '@/actions/App/Http/Controllers/ParishMergeController';
import InputError from '@/components/input-error';
import { SuggestField } from '@/components/suggest-field';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { parishUsageNominative } from '@/lib/places-usage';
import type { Parish } from '@/types';

type Props = {
    /** The duplicate: it disappears from the register and its acts move. */
    parish: Parish;
    /** The register's parishes; the duplicate is left out of the choice. */
    parishes: Parish[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Picks the parish that stays and merges the duplicate into it. Not a form: the panel's form keeps its own state. */
export function MergeParishDialog({
    parish,
    parishes,
    open,
    onOpenChange,
}: Props) {
    const [targetId, setTargetId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const candidates = parishes.filter(
        (candidate) => candidate.id !== parish.id,
    );
    const count = parish.sources_count ?? 0;

    const handleOpenChange = (next: boolean) => {
        onOpenChange(next);
        setErrors({});
        setTargetId(null);
    };

    const handleConfirm = () => {
        if (targetId === null) {
            return;
        }

        setProcessing(true);

        router.post(
            ParishMergeController(parish.id).url,
            { target_id: targetId },
            {
                preserveScroll: true,
                onSuccess: () => handleOpenChange(false),
                onError: setErrors,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <DialogTitle>Scal „{parish.label}” z…</DialogTitle>
                <DialogDescription>
                    „{parish.label}” zniknie ze spisu. Jej akty (
                    {parishUsageNominative(count)}) przejdą na wybraną parafię.
                    Puste pola wybranej uzupełnią się danymi „{parish.label}”.
                    Tej operacji nie można cofnąć.
                </DialogDescription>

                <div className="grid gap-2">
                    <Label htmlFor="merge-parish-target">
                        Właściwa parafia
                    </Label>
                    <SuggestField
                        mode="record"
                        id="merge-parish-target"
                        name="target_id"
                        label="Właściwa parafia"
                        options={candidates}
                        labelOf={(option) => option.label}
                        value={targetId}
                        onChange={setTargetId}
                        placeholder="Wpisz nazwę parafii"
                        disabled={processing}
                        aria-invalid={errors.target_id ? true : undefined}
                    />
                    {candidates.length === 0 && (
                        <p className="text-muted-foreground text-sm">
                            W spisie nie ma innej parafii do scalenia.
                        </p>
                    )}
                    <InputError message={errors.target_id} />
                </div>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button type="button" variant="secondary">
                            Anuluj
                        </Button>
                    </DialogClose>

                    <Button
                        type="button"
                        disabled={targetId === null || processing}
                        onClick={handleConfirm}
                    >
                        {processing && <Spinner />}
                        Scal
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
