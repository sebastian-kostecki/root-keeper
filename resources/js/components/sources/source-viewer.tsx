import { TransformComponent, TransformWrapper } from 'react-zoom-pan-pinch';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Props = {
    src: string;
    alt: string;
    className?: string;
};

/** Fills the height of its parent; give the parent a definite height. Zoom starts fitted for every `src`. */
export function SourceViewer({ src, alt, className }: Props) {
    return (
        <TransformWrapper
            key={src}
            initialScale={1}
            minScale={0.5}
            maxScale={32}
            centerOnInit
        >
            {({ zoomIn, zoomOut, resetTransform }) => (
                <div className={cn('flex h-full flex-col gap-2', className)}>
                    <div className="flex shrink-0 gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => zoomIn()}
                        >
                            Powiększ
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => zoomOut()}
                        >
                            Pomniejsz
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => resetTransform()}
                        >
                            Dopasuj
                        </Button>
                    </div>

                    <div className="bg-muted min-h-0 w-full flex-1 overflow-hidden rounded-md">
                        <TransformComponent
                            wrapperStyle={{ width: '100%', height: '100%' }}
                            contentStyle={{ width: '100%', height: '100%' }}
                        >
                            <img
                                src={src}
                                alt={alt}
                                className="h-full w-full object-contain"
                            />
                        </TransformComponent>
                    </div>
                </div>
            )}
        </TransformWrapper>
    );
}
