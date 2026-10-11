import PersonPhotoController from '@/actions/App/Http/Controllers/PersonPhotoController';

/**
 * Thumbnail URL of a person's photo. The version (`photo_version`) is the cache-buster:
 * the server caches the file for a day, so a replaced photo gets a new URL.
 * Null when the person has no photo.
 */
export function personPhotoUrl(
    personId: number,
    version: string | null,
): string | null {
    if (!version) {
        return null;
    }

    return PersonPhotoController.show.url(personId, {
        query: { v: version },
    });
}
