import { router } from '@inertiajs/react';
import { useState } from 'react';
import TreeRootPersonController from '@/actions/App/Http/Controllers/TreeRootPersonController';

/** Makes a person the tree's proband; shared by the button and the modal menu. */
export function useSetRootPerson(personId: number) {
    const [processing, setProcessing] = useState(false);

    const setRootPerson = () => {
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

    return { setRootPerson, processing };
}
