import { Link } from '@inertiajs/react';
import { BookOpen, House } from 'lucide-react';
import { cn } from '@/lib/utils';
import { home as childHome } from '@/routes/child';
import { index as storiesIndex } from '@/routes/stories';

const tabs = [
    { key: 'home', label: 'Bosh sahifa', href: childHome(), icon: House },
    {
        key: 'stories',
        label: 'Hikoyalar',
        href: storiesIndex(),
        icon: BookOpen,
    },
] as const;

/**
 * The bottom tab bar on the Child's screens: Home and the Story library.
 */
export default function ChildTabBar({
    current,
}: {
    current: (typeof tabs)[number]['key'];
}) {
    return (
        <nav
            aria-label="Bolalar menyusi"
            className="fixed inset-x-0 bottom-0 z-10 border-t-2 bg-card"
        >
            <ul className="mx-auto flex h-22 max-w-md items-start justify-around px-4 pt-3">
                {tabs.map((tab) => (
                    <li key={tab.key}>
                        <Link
                            href={tab.href}
                            aria-current={
                                tab.key === current ? 'page' : undefined
                            }
                            className={cn(
                                'flex min-h-11 min-w-20 flex-col items-center gap-1 rounded-2xl px-3 py-1 text-xs font-semibold text-muted-foreground',
                                tab.key === current && 'text-primary',
                            )}
                        >
                            <tab.icon className="size-6" />
                            {tab.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
