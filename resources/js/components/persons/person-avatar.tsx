import { useInitials } from '@/hooks/use-initials';
import { genderSurface } from '@/lib/gender-styles';
import { cn } from '@/lib/utils';

type Props = {
    name: string;
    gender: 'male' | 'female' | null;
    size?: 'sm' | 'md' | 'lg';
    /** Set false where the avatar sits inside a link that already carries the name. */
    announceGender?: boolean;
    className?: string;
};

const sizes = {
    sm: 'size-9 text-sm',
    md: 'size-14 text-lg',
    lg: 'size-20 text-2xl',
} as const;

const genderLabels = {
    male: 'Mężczyzna',
    female: 'Kobieta',
} as const;

/** Gender-coloured initials avatar; the initials are decorative, the gender is read out. */
export function PersonAvatar({
    name,
    gender,
    size = 'md',
    announceGender = true,
    className,
}: Props) {
    const getInitials = useInitials();

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full border font-semibold',
                sizes[size],
                genderSurface[gender ?? 'unknown'],
                className,
            )}
        >
            <span aria-hidden="true">{getInitials(name)}</span>
            {gender && announceGender && (
                <span className="sr-only">{genderLabels[gender]}</span>
            )}
        </span>
    );
}
