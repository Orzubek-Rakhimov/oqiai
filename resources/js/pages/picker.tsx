import { Head, Link } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import PickerController from '@/actions/App/Http/Controllers/PickerController';
import ChildAvatar from '@/components/child-avatar';
import { home as parentHome } from '@/routes/parent';
import type { Child } from '@/types';

export default function Picker({
    childProfiles,
}: {
    childProfiles: Pick<Child, 'id' | 'name' | 'avatar'>[];
}) {
    return (
        <>
            <Head title="Kim o'qiyapti?" />

            <div className="flex justify-end">
                <Link
                    href={parentHome()}
                    className="inline-flex min-h-11 items-center gap-2 rounded-full border-2 bg-card px-4 text-sm font-semibold"
                    data-test="parent-button"
                >
                    <UserRound className="size-4" />
                    Ota-ona
                </Link>
            </div>

            <h1 className="my-8 text-center text-4xl font-black">
                Kim o&apos;qiyapti?
            </h1>

            <ul className="grid grid-cols-2 gap-4">
                {childProfiles.map((child) => (
                    <li key={child.id}>
                        <Link
                            href={PickerController.choose(child.id)}
                            as="button"
                            className="flex w-full flex-col items-center gap-3 rounded-3xl border-2 bg-card p-5 transition-colors hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <ChildAvatar avatar={child.avatar} size="lg" />
                            <span className="font-heading text-xl font-extrabold">
                                {child.name}
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </>
    );
}
