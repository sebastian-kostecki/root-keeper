import { ImageOff } from 'lucide-react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { cn } from '@/lib/utils';
import type { LinkedSource } from '@/types';

type Props = {
    source: LinkedSource;
    className?: string;
    iconClassName?: string;
    /** The name is printed next to the image: the image gets an empty alt. */
    decorative?: boolean;
};

export function SourceThumbnail({
    source,
    className,
    iconClassName = 'size-10',
    decorative = false,
}: Props) {
    return (
        <div
            className={cn(
                'bg-muted flex aspect-square items-center justify-center overflow-hidden',
                className,
            )}
        >
            {source.has_thumbnail ? (
                <img
                    src={SourceController.thumbnail.url(source.id)}
                    alt={decorative ? '' : source.display_name}
                    loading="lazy"
                    className="h-full w-full object-cover"
                />
            ) : (
                <ImageOff
                    className={cn('text-muted-foreground', iconClassName)}
                />
            )}
        </div>
    );
}
