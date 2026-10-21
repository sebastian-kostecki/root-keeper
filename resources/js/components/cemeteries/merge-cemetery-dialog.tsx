import { router } from '@inertiajs/react';
import { useState } from 'react';
import CemeteryMergeController from '@/actions/App/Http/Controllers/CemeteryMergeController';
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
import { cemeteryUsageNominative } from '@/lib/places-usage';
import type { Cemetery } from '@/types';

type Props = {
    /** The duplicate: it disappears from the register and its gravestones and burials move. */
    cemetery: Cemetery;
    /** The register's cemeteries; the duplicate is left out of the choice. */
    cemeteries: Cemetery[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

/** Picks the cemetery that stays and merges the duplicate into it. Not a form: the panel's form keeps its own state. */
export function MergeCemeteryDialog({
    cemetery,
    cemeteries,
    open,
    onOpenChange,
}: Props) {
    const [targetId, setTargetId] = useState<number | null>(null);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const candidates = cemeteries.filter(
        (candidate) => candidate.id !== cemetery.id,
    );
    const sources = cemetery.sources_count ?? 0;
    const burials = cemetery.burials_count ?? 0;

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
            CemeteryMergeController(cemetery.id).url,
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
                <DialogTitle>Scal „{cemetery.label}” z…</DialogTitle>
                <DialogDescription>
                    „{cemetery.label}” zniknie ze spisu. Jego nagrobki i
                    pochowania ({cemeteryUsageNominative(sources, burials)})
                    przejdą na wybrany cmentarz. Puste pola wybranego uzupełnią
                    się danymi „{cemetery.label}”. Tej operacji nie można
                    cofnąć.
                </DialogDescription>

                <div className="grid gap-2">
                    <Label htmlFor="merge-cemetery-target">
                        Właściwy cmentarz
                    </Label>
                    <SuggestField
                        mode="record"
                        id="merge-cemetery-target"
                        name="target_id"
                        label="Właściwy cmentarz"
                        options={candidates}
                        labelOf={(option) => option.label}
                        value={targetId}
                        onChange={setTargetId}
                        placeholder="Wpisz nazwę cmentarza"
                        disabled={processing}
                        aria-invalid={errors.target_id ? true : undefined}
                    />
                    {candidates.length === 0 && (
                        <p className="text-muted-foreground text-sm">
                            W spisie nie ma innego cmentarza do scalenia.
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
                        Scal cmentarze
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
