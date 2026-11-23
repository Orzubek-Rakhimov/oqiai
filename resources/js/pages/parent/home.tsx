import { Head, Link } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import ChildController from '@/actions/App/Http/Controllers/Parent/ChildController';
import ChildAvatar from '@/components/child-avatar';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { create } from '@/routes/children';
import { home as parentHome } from '@/routes/parent';
import type { Child } from '@/types';

export default function ParentHome({
    childProfiles,
}: {
    childProfiles: Child[];
}) {
    return (
        <>
            <Head title="Farzandlarim" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Farzandlarim"
                        description="Profillarni qo'shing, tahrirlang yoki o'chiring"
                    />

                    <Button asChild size="lg" className="rounded-full">
                        <Link href={create()} data-test="add-child-button">
                            <Plus />
                            Farzand qo&apos;shish
                        </Link>
                    </Button>
                </div>

                {childProfiles.length === 0 ? (
                    <p className="rounded-3xl border-2 bg-card p-6 text-center text-muted-foreground">
                        Hali farzand qo&apos;shilmagan.
                    </p>
                ) : (
                    <ul className="space-y-3">
                        {childProfiles.map((child) => (
                            <li
                                key={child.id}
                                className="flex items-center gap-4 rounded-3xl border-2 bg-card p-4"
                            >
                                <ChildAvatar avatar={child.avatar} />

                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-heading text-lg font-extrabold">
                                        {child.name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {child.grade}-sinf
                                    </p>
                                </div>

                                <Button
                                    asChild
                                    variant="outline"
                                    size="icon"
                                    className="size-11 rounded-full"
                                >
                                    <Link
                                        href={ChildController.edit(child.id)}
                                        aria-label={`${child.name}: tahrirlash`}
                                    >
                                        <Pencil />
                                    </Link>
                                </Button>

                                <DeleteChild child={child} />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function DeleteChild({ child }: { child: Child }) {
    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="icon"
                    className="size-11 rounded-full text-destructive"
                    aria-label={`${child.name}: o'chirish`}
                >
                    <Trash2 />
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    {child.name} profilini o&apos;chirmoqchimisiz?
                </DialogTitle>
                <DialogDescription>
                    Profil va uning barcha natijalari butunlay o&apos;chiriladi.
                    Buni qaytarib bo&apos;lmaydi.
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Bekor qilish</Button>
                    </DialogClose>
                    <Button variant="destructive" asChild>
                        <Link
                            href={ChildController.destroy(child.id)}
                            as="button"
                            data-test="confirm-delete-child-button"
                        >
                            O&apos;chirish
                        </Link>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

ParentHome.layout = {
    breadcrumbs: [
        {
            title: 'Farzandlarim',
            href: parentHome(),
        },
    ],
};
