import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    CalendarDays,
    Flame,
    Info,
    Pencil,
    Plus,
    Star,
    Trash2,
} from 'lucide-react';
import type { ReactNode } from 'react';
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
import { cn } from '@/lib/utils';
import { create } from '@/routes/children';
import { home as parentHome } from '@/routes/parent';
import type { Child, ChildProgress } from '@/types';

export default function ParentHome({
    childProfiles,
    selectedChildId,
    progress,
}: {
    childProfiles: Child[];
    selectedChildId: number | null;
    progress: ChildProgress | null;
}) {
    const selectedChild = childProfiles.find(
        (child) => child.id === selectedChildId,
    );

    return (
        <>
            <Head title="Ota-ona paneli" />

            <div className="mx-auto w-full max-w-2xl space-y-6 p-4">
                <header>
                    <h1 className="font-heading text-2xl font-black">
                        Ota-ona paneli
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Farzandingiz bu hafta qanday o&apos;qidi
                    </p>
                </header>

                {selectedChild && progress && (
                    <section className="space-y-4" aria-label="Natijalar">
                        {childProfiles.length > 1 && (
                            <ChildSwitcher
                                childProfiles={childProfiles}
                                selectedChildId={selectedChild.id}
                            />
                        )}

                        {progress.hasRead ? (
                            <ProgressFigures progress={progress} />
                        ) : (
                            <div
                                className="rounded-3xl border-2 bg-card p-6 text-center"
                                data-test="progress-empty"
                            >
                                <ChildAvatar avatar={selectedChild.avatar} />
                                <p className="mt-3 font-heading text-lg font-extrabold">
                                    {selectedChild.name} hali o&apos;qimadi
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Birinchi hikoyadan keyin bu yerda haftalik
                                    natijalar paydo bo&apos;ladi.
                                </p>
                            </div>
                        )}
                    </section>
                )}

                <p
                    className="flex gap-3 rounded-2xl bg-teal-soft p-4 text-sm text-teal"
                    data-test="voice-notice"
                >
                    <Info className="mt-0.5 size-5 shrink-0" />
                    <span>
                        Ilovadagi ovoz sun&apos;iy intellekt (AI) yordamida
                        yaratilgan. Farzandingizning ovoz yozuvlari saqlanmaydi.
                    </span>
                </p>

                <section className="space-y-4" aria-label="Farzandlarim">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <Heading
                            variant="small"
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
                                            href={ChildController.edit(
                                                child.id,
                                            )}
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
                </section>
            </div>
        </>
    );
}

function ChildSwitcher({
    childProfiles,
    selectedChildId,
}: {
    childProfiles: Child[];
    selectedChildId: number;
}) {
    return (
        <nav aria-label="Farzandni tanlash" className="flex flex-wrap gap-2">
            {childProfiles.map((child) => {
                const isSelected = child.id === selectedChildId;

                return (
                    <Link
                        key={child.id}
                        href={parentHome({ query: { child: child.id } })}
                        preserveScroll
                        aria-current={isSelected ? 'page' : undefined}
                        className={cn(
                            'inline-flex min-h-11 items-center gap-2 rounded-full border-2 py-1 pr-4 pl-1 font-semibold',
                            isSelected
                                ? 'border-foreground bg-foreground text-background'
                                : 'bg-card',
                        )}
                        data-test="switch-child"
                    >
                        <ChildAvatar
                            avatar={child.avatar}
                            size="sm"
                            className="size-9 text-xl"
                        />
                        {child.name}
                    </Link>
                );
            })}
        </nav>
    );
}

const weekLabels = [
    '3 hafta oldin',
    '2 hafta oldin',
    "O'tgan hafta",
    'Bu hafta',
];

function ProgressFigures({ progress }: { progress: ChildProgress }) {
    const fastestSpeed = Math.max(
        1,
        ...progress.readingSpeedByWeek.map((week) => week.readingSpeed ?? 0),
    );

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-2 gap-3">
                <Figure
                    icon={<CalendarDays className="size-5 text-teal" />}
                    label="Bu hafta o'qigan kunlari"
                    value={`${progress.daysPractised} / 7`}
                    test="days-practised"
                />
                <Figure
                    icon={<BookOpen className="size-5 text-teal" />}
                    label="Bu hafta tugatgan hikoyalari"
                    value={progress.storiesFinished}
                    test="stories-finished"
                />
                <Figure
                    icon={<Flame className="size-5 text-primary" />}
                    label="Ketma-ket kunlar"
                    value={progress.streak}
                    test="streak"
                />
                <Figure
                    icon={<Star className="size-5 fill-star text-star" />}
                    label="Jami yulduzlar"
                    value={progress.starsTotal}
                    test="stars-total"
                />
            </div>

            <div className="rounded-3xl border-2 bg-card p-5">
                <h2 className="font-heading text-lg font-extrabold">
                    O&apos;qish tezligi
                </h2>
                <p className="text-sm text-muted-foreground">
                    Daqiqasiga to&apos;g&apos;ri o&apos;qilgan so&apos;zlar,
                    haftalik o&apos;rtacha
                </p>
                <ol
                    className="mt-4 grid grid-cols-4 items-end gap-3"
                    data-test="reading-speed-trend"
                >
                    {progress.readingSpeedByWeek.map((week, index) => (
                        <li
                            key={week.weekStart}
                            className="flex flex-col items-center gap-2"
                        >
                            <span className="font-heading text-lg font-black tabular-nums">
                                {week.readingSpeed ?? '—'}
                            </span>
                            <div className="flex h-24 w-full items-end overflow-hidden rounded-xl bg-secondary">
                                <div
                                    className={cn(
                                        'w-full rounded-xl',
                                        index === weekLabels.length - 1
                                            ? 'bg-teal'
                                            : 'bg-teal/50',
                                    )}
                                    style={{
                                        height: `${((week.readingSpeed ?? 0) / fastestSpeed) * 100}%`,
                                    }}
                                />
                            </div>
                            <span className="text-center text-xs text-muted-foreground">
                                {weekLabels[index]}
                            </span>
                        </li>
                    ))}
                </ol>
            </div>

            <div className="rounded-3xl border-2 bg-card p-5">
                <h2 className="font-heading text-lg font-extrabold">
                    Qiyin so&apos;zlar
                </h2>
                <p className="text-sm text-muted-foreground">
                    Oxirgi 7 kunda qayta o&apos;qish yoki bo&apos;g&apos;inlab
                    o&apos;qish kerak bo&apos;lgan so&apos;zlar
                </p>
                {progress.hardWords.length === 0 ? (
                    <p className="mt-4 text-sm">
                        Oxirgi 7 kunda qiyin so&apos;z bo&apos;lmadi.
                    </p>
                ) : (
                    <ul
                        className="mt-4 flex flex-wrap gap-2"
                        data-test="hard-words"
                    >
                        {progress.hardWords.map((hardWord) => (
                            <li
                                key={hardWord.word}
                                className="inline-flex min-h-11 items-center gap-2 rounded-full bg-accent px-4 font-heading text-lg font-extrabold"
                            >
                                {hardWord.word}
                                <span className="font-sans text-sm font-semibold text-muted-foreground tabular-nums">
                                    {hardWord.count} marta
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}

function Figure({
    icon,
    label,
    value,
    test,
}: {
    icon: ReactNode;
    label: string;
    value: ReactNode;
    test: string;
}) {
    return (
        <div className="rounded-3xl border-2 bg-card p-4" data-test={test}>
            {icon}
            <p className="mt-2 font-heading text-3xl font-black tabular-nums">
                {value}
            </p>
            <p className="text-sm text-muted-foreground">{label}</p>
        </div>
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
            title: 'Ota-ona paneli',
            href: parentHome(),
        },
    ],
};
