import { Head, Link } from '@inertiajs/react';
import { Users } from 'lucide-react';
import ChildAvatar from '@/components/child-avatar';
import { picker } from '@/routes';
import type { Child } from '@/types';

export default function ChildHome({ child }: { child: Child }) {
    return (
        <>
            <Head title={child.name} />

            <div className="flex justify-end">
                <Link
                    href={picker()}
                    className="inline-flex min-h-11 items-center gap-2 rounded-full border-2 bg-card px-4 text-sm font-semibold"
                    data-test="back-to-picker"
                >
                    <Users className="size-4" />
                    Kim o&apos;qiyapti?
                </Link>
            </div>

            <div className="mt-8 flex items-center gap-4 rounded-3xl bg-foreground p-5 text-background">
                <ChildAvatar avatar={child.avatar} />
                <div>
                    <p className="text-sm opacity-80">{child.grade}-sinf</p>
                    <h1 className="text-3xl font-black">
                        Salom, {child.name}!
                    </h1>
                </div>
            </div>
        </>
    );
}
