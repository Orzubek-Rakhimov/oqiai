import { cn } from '@/lib/utils';
import type { AvatarKey } from '@/types';

export const avatarOptions: Record<
    AvatarKey,
    { emoji: string; label: string; className: string }
> = {
    fox: { emoji: '🦊', label: 'Tulkicha', className: 'bg-accent' },
    owl: { emoji: '🦉', label: 'Boyqush', className: 'bg-secondary' },
    bear: { emoji: '🐻', label: 'Ayiqcha', className: 'bg-teal-soft' },
    rabbit: { emoji: '🐰', label: 'Quyoncha', className: 'bg-accent' },
    cat: { emoji: '🐱', label: 'Mushukcha', className: 'bg-secondary' },
    lion: { emoji: '🦁', label: 'Sherbola', className: 'bg-teal-soft' },
};

const sizes = {
    sm: 'size-11 text-2xl',
    md: 'size-16 text-4xl',
    lg: 'size-24 text-6xl',
};

export default function ChildAvatar({
    avatar,
    size = 'md',
    className,
}: {
    avatar: AvatarKey;
    size?: keyof typeof sizes;
    className?: string;
}) {
    const option = avatarOptions[avatar];

    return (
        <span
            role="img"
            aria-label={option.label}
            className={cn(
                'inline-flex shrink-0 items-center justify-center rounded-full',
                option.className,
                sizes[size],
                className,
            )}
        >
            {option.emoji}
        </span>
    );
}
