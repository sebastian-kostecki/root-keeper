import { router } from '@inertiajs/react';
import { useState } from 'react';
import LocalityMergeController from '@/actions/App/Http/Controllers/LocalityMergeController';
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
import { localityUsageNominative, noLocalityUsage } from '@/lib/places-usage';
import type { Locality } from '@/types';

type Props = {
    /** The duplicate: it disappears from the register and its references move. */
    locality: Locality;
    /** The register's localities; the duplicate is left out of the choice. */
    localities: Locality[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Picks the locality that stays and merges the duplicate into it. Not a form: the panel's form keeps its own state. */
export function MergeLocalityDialog({
    locality,
    localities,
    open,
    onOpenChange,
}: Props) {
    const [targetId, setTargetId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const candidates = localities.filter(
        (candidate) => candidate.id !== locality.id,
    );
    const usage = locality.usage ?? noLocalityUsage;

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
            LocalityMergeController(locality.id).url,
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
                <DialogTitle>Scal „{locality.name}” z…</DialogTitle>
                <DialogDescription>
                    „{locality.name}” zniknie ze spisu. Jej odwołania (
                    {localityUsageNominative(usage)}) przejdą na wybraną
                    miejscowość. Puste pola wybranej uzupełnią się danymi „
                    {locality.name}”. Tej operacji nie można cofnąć.
                </DialogDescription>

                <div className="grid gap-2">
                    <Label htmlFor="merge-locality-target">
                        Właściwa miejscowość
                    </Label>
                    <SuggestField
                        mode="record"
                        id="merge-locality-target"
                        name="target_id"
                        label="Właściwa miejscowość"
                        options={candidates}
                        labelOf={(option) => option.name}
                        value={targetId}
                        onChange={setTargetId}
                        placeholder="Wpisz nazwę miejscowości"
                        disabled={processing}
                        aria-invalid={errors.target_id ? true : undefined}
                    />
                    {candidates.length === 0 && (
                        <p className="text-muted-foreground text-sm">
                            W spisie nie ma innej miejscowości do scalenia.
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
