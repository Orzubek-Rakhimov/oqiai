import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * Three stars with the earned ones filled in gold.
 */
export default function StarRating({
    stars,
    className,
    starClassName,
}: {
    stars: number;
    className?: string;
    starClassName?: string;
}) {
    return (
        <div
            role="img"
            aria-label={`${stars} ta yulduz`}
            className={cn('flex', className)}
            data-test="stars"
            data-stars={stars}
        >
            {[1, 2, 3].map((position) => (
                <Star
                    key={position}
                    className={cn(
                        'size-4',
                        starClassName,
                        position <= stars
                            ? 'fill-star text-star'
                            : 'fill-muted text-muted',
                    )}
                />
            ))}
        </div>
    );
}
