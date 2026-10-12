import { useState } from 'react';

/**
 * Hides a photo URL that failed to load, so the caller can show a fallback.
 * A different URL is tried again without a reload (Inertia keeps component state).
 */
export function useVisiblePhoto(photoUrl: string | null) {
    const [failedPhotoUrl, setFailedPhotoUrl] = useState<string | null>(null);
    const visiblePhotoUrl = photoUrl !== failedPhotoUrl ? photoUrl : null;

    return {
        visiblePhotoUrl,
        onError: () => setFailedPhotoUrl(photoUrl),
    };
}
