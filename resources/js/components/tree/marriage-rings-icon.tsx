import { cn } from '@/lib/utils';

/**
 * The marriage sign (⚭), which lucide-react does not have. The colour is
 * inherited from the parent (`currentColor`).
 */
export function MarriageRingsIcon({ className }: { className?: string }) {
    return (
        <svg
            viewBox="0 0 16 12"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            aria-hidden="true"
            className={cn('h-3 w-4 shrink-0', className)}
        >
            <circle cx={5.5} cy={6} r={4.25} />
            <circle cx={10.5} cy={6} r={4.25} />
        </svg>
    );
}
