import { router } from '@inertiajs/react';
import { useState } from 'react';
import TreeRootPersonController from '@/actions/App/Http/Controllers/TreeRootPersonController';
import { Button } from '@/components/ui/button';

export function SetRootPersonButton({ personId }: { personId: number }) {
    const [processing, setProcessing] = useState(false);

    const handleClick = () => {
        setProcessing(true);

        router.put(
            TreeRootPersonController.update(personId).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Button
            variant="outline"
            size="sm"
            disabled={processing}
            onClick={handleClick}
        >
            Ustaw jako probanta
        </Button>
    );
}
