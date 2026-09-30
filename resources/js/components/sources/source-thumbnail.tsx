import { ImageOff } from 'lucide-react';
import SourceController from '@/actions/App/Http/Controllers/SourceController';
import { cn } from '@/lib/utils';
import type { LinkedSource } from '@/types';

type Props = {
    source: LinkedSource;
    className?: string;
};

export function SourceThumbnail({ source, className }: Props) {
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
                    alt={source.display_name}
                    loading="lazy"
                    className="h-full w-full object-cover"
                />
            ) : (
                <ImageOff className="text-muted-foreground size-10" />
            )}
        </div>
    );
}
