import { useVisiblePhoto } from '@/hooks/use-visible-photo';
import { genderSurface } from '@/lib/gender-styles';
import { personPhotoUrl } from '@/lib/person-photo';
import { cn } from '@/lib/utils';
import type { SheetPerson } from '@/types';

type Gender = NonNullable<SheetPerson['gender']> | 'unknown';

/** Silhouette colour per gender: the full-strength gender token on the lighter surface. */
const silhouetteTone: Record<Gender, string> = {
    male: 'text-gender-male',
    female: 'text-gender-female',
    unknown: 'text-gender-unknown',
};

/**
 * Generic portrait shown without a photo: a bust for a man, a bust with long
 * hair for a woman, a plain bust when the gender is not known.
 */
function Silhouette({ gender }: { gender: Gender }) {
    return (
        <svg
            aria-hidden="true"
            data-testid="card-avatar-placeholder"
            viewBox="0 0 24 24"
            fill="currentColor"
            className={cn('size-full', silhouetteTone[gender])}
        >
            {gender === 'female' && (
                <path d="M6.2 10a5.8 5.8 0 0 1 11.6 0v6.5c0 1-.8 1.8-1.8 1.8H8c-1 0-1.8-.8-1.8-1.8z" />
            )}
            <circle
                cx="12"
                cy="9.5"
                r="3.8"
                className={gender === 'female' ? 'opacity-80' : undefined}
            />
            <path
                d={
                    gender === 'female'
                        ? 'M5.5 24c0-4.6 2.9-7.2 6.5-7.2s6.5 2.6 6.5 7.2z'
                        : 'M3.5 24c0-5 3.8-7.6 8.5-7.6s8.5 2.6 8.5 7.6z'
                }
            />
        </svg>
    );
}

export type CardAvatarSize = 'card' | 'ancestor' | 'pair';

/**
 * Avatar diameter per card: the tree card is 2.5rem on cards narrower than
 * 20rem and 3.5rem on wider ones (it needs the `@container` of the card), the
 * ancestor card a fixed 3.5rem, the pair card a fixed 4rem.
 */
const avatarSize: Record<CardAvatarSize, string> = {
    card: 'size-10 @xs:size-14',
    ancestor: 'size-14',
    pair: 'size-16',
};

/**
 * Round avatar at the top left of a person card: the photo, or the generic
 * portrait of the person's gender. Decorative: the gender and the name are read
 * from the card's own text.
 */
export function CardAvatar({
    person,
    size = 'card',
}: {
    person: SheetPerson;
    size?: CardAvatarSize;
}) {
    const gender: Gender = person.gender ?? 'unknown';
    const photoUrl = personPhotoUrl(person.id, person.photo_version);
    const { visiblePhotoUrl, onError } = useVisiblePhoto(photoUrl);

    return (
        <span
            className={cn(
                'relative mt-3.5 ml-3 shrink-0 self-start overflow-hidden rounded-full border',
                avatarSize[size],
                genderSurface[gender],
            )}
        >
            {visiblePhotoUrl !== null ? (
                <img
                    src={visiblePhotoUrl}
                    alt=""
                    loading="lazy"
                    onError={onError}
                    className="absolute inset-0 size-full object-cover object-center"
                />
            ) : (
                <Silhouette gender={gender} />
            )}
        </span>
    );
}
