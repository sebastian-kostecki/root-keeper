import { ImageOff } from 'lucide-react';
import { useState } from 'react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { cn } from '@/lib/utils';
import type { LinkedSource } from '@/types';

type Props = {
    source: Pick<LinkedSource, 'id' | 'display_name' | 'has_thumbnail'>;
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
    /** The source whose image failed to load; a failed image shows the same icon as a source without one. */
    const [failedSourceId, setFailedSourceId] = useState<number | null>(null);
    const showImage = source.has_thumbnail && failedSourceId !== source.id;

    return (
        <div
            className={cn(
                'bg-muted flex aspect-square items-center justify-center overflow-hidden',
                className,
            )}
        >
            {showImage ? (
                <img
                    src={SourceController.thumbnail.url(source.id)}
                    alt={decorative ? '' : source.display_name}
                    loading="lazy"
                    className="h-full w-full object-cover"
                    onError={() => setFailedSourceId(source.id)}
                />
            ) : (
                <ImageOff
                    className={cn('text-muted-foreground', iconClassName)}
                />
            )}
        </div>
    );
}
