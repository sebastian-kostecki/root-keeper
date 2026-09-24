import { ImageOff } from 'lucide-react';
import ScanController from '@/actions/App/Http/Controllers/ScanController';
import { cn } from '@/lib/utils';
import type { LinkedScan } from '@/types';

type Props = {
    scan: LinkedScan;
    className?: string;
};

export function ScanThumbnail({ scan, className }: Props) {
    return (
        <div
            className={cn(
                'bg-muted flex aspect-square items-center justify-center overflow-hidden',
                className,
            )}
        >
            {scan.has_thumbnail ? (
                <img
                    src={ScanController.thumbnail.url(scan.id)}
                    alt={scan.original_name}
                    loading="lazy"
                    className="h-full w-full object-cover"
                />
            ) : (
                <ImageOff className="text-muted-foreground size-10" />
            )}
        </div>
    );
}
