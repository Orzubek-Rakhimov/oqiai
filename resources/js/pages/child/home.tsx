import { Head, Link } from '@inertiajs/react';
import { BookOpen, Flame, Play, Star, Users } from 'lucide-react';
import ChildAvatar from '@/components/child-avatar';
import ChildTabBar from '@/components/child-tab-bar';
import { picker } from '@/routes';
import { show as showReading, store as storeReading } from '@/routes/readings';
import { index as storiesIndex } from '@/routes/stories';
import type { Child, ContinueReading, SuggestedStory } from '@/types';

type Props = {
    child: Child;
    streak: number;
    starsTotal: number;
    suggestedStory: SuggestedStory | null;
    continueReading: ContinueReading | null;
};

export default function ChildHome({
    child,
    streak,
    starsTotal,
    suggestedStory,
    continueReading,
}: Props) {
    const percentRead =
        continueReading && continueReading.progress.total > 0
            ? Math.round(
                  (continueReading.progress.read /
                      continueReading.progress.total) *
                      100,
              )
            : 0;

    return (
        <>
            <Head title={child.name} />

            <div className="pb-28">
                <div className="flex items-center justify-between gap-2">
                    <div className="flex gap-2">
                        <p
                            className="inline-flex min-h-11 items-center gap-1.5 rounded-full border-2 bg-card px-3 font-heading text-lg font-black tabular-nums"
                            aria-label={`Ketma-ket kunlar: ${streak}`}
                            data-test="streak"
                        >
                            <Flame className="size-5 text-primary" />
                            {streak}
                        </p>
                        <p
                            className="inline-flex min-h-11 items-center gap-1.5 rounded-full border-2 bg-card px-3 font-heading text-lg font-black tabular-nums"
                            aria-label={`Jami yulduzlar: ${starsTotal}`}
                            data-test="stars-total"
                        >
                            <Star className="size-5 fill-star text-star" />
                            {starsTotal}
                        </p>
                    </div>
                    <Link
                        href={picker()}
                        className="inline-flex min-h-11 items-center gap-2 rounded-full border-2 bg-card px-4 text-sm font-semibold"
                        data-test="back-to-picker"
                    >
                        <Users className="size-4" />
                        Kim o&apos;qiyapti?
                    </Link>
                </div>

                <div className="mt-6 flex items-center gap-4 rounded-3xl bg-foreground p-5 text-background">
                    <ChildAvatar avatar={child.avatar} />
                    <div>
                        <p className="text-sm opacity-80">{child.grade}-sinf</p>
                        <h1 className="font-heading text-3xl font-black">
                            Salom, {child.name}!
                        </h1>
                        <p className="mt-1 text-sm opacity-80">
                            {streak > 0
                                ? `${streak} kun ketma-ket o'qiding. Davom et!`
                                : "Bugun bitta hikoya o'qib, yangi seriya boshla!"}
                        </p>
                    </div>
                </div>

                {continueReading && (
                    <section className="mt-6" aria-labelledby="continue">
                        <h2
                            id="continue"
                            className="font-heading text-xl font-black"
                        >
                            Davom ettirish
                        </h2>
                        <Link
                            href={showReading(continueReading.id)}
                            className="mt-3 flex items-center gap-4 rounded-3xl border-2 bg-card p-4"
                            data-test="continue-reading"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-heading text-lg font-extrabold">
                                    {continueReading.story.title}
                                </p>
                                <div
                                    className="mt-2 h-2.5 overflow-hidden rounded-full bg-teal-soft"
                                    role="progressbar"
                                    aria-label="O'qilgan qismi"
                                    aria-valuemin={0}
                                    aria-valuemax={
                                        continueReading.progress.total
                                    }
                                    aria-valuenow={
                                        continueReading.progress.read
                                    }
                                >
                                    <div
                                        className="h-full rounded-full bg-teal"
                                        style={{ width: `${percentRead}%` }}
                                    />
                                </div>
                                <p className="mt-1.5 text-xs text-muted-foreground tabular-nums">
                                    {continueReading.progress.read}/
                                    {continueReading.progress.total} gap
                                    o&apos;qildi
                                </p>
                            </div>
                            <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                                <Play className="size-5 fill-current" />
                            </span>
                        </Link>
                    </section>
                )}

                <section className="mt-6" aria-labelledby="suggested">
                    <h2
                        id="suggested"
                        className="font-heading text-xl font-black"
                    >
                        Bugungi hikoya
                    </h2>
                    {suggestedStory ? (
                        <div className="mt-3 overflow-hidden rounded-3xl border-2 bg-card">
                            <div
                                className="flex h-28 items-center justify-center bg-teal text-white/90"
                                aria-hidden
                            >
                                <BookOpen className="size-12" />
                            </div>
                            <div className="p-4">
                                <p className="font-heading text-xl font-extrabold">
                                    {suggestedStory.title}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {suggestedStory.level}-daraja ·{' '}
                                    {suggestedStory.minutes} daqiqa
                                </p>
                                <Link
                                    href={storeReading(suggestedStory.id)}
                                    as="button"
                                    className="mt-4 inline-flex h-15 w-full items-center justify-center rounded-full bg-primary font-heading text-lg font-black text-primary-foreground"
                                    data-test="open-suggested-story"
                                >
                                    O&apos;qishni boshlash
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <p className="mt-3 rounded-3xl border-2 bg-card p-4 text-muted-foreground">
                            Barcha hikoyalarni o&apos;qib bo&apos;lding.
                            Barakalla!
                        </p>
                    )}
                    <Link
                        href={storiesIndex()}
                        className="mt-3 inline-flex min-h-11 w-full items-center justify-center rounded-full border-2 bg-card px-4 font-semibold"
                        data-test="open-library"
                    >
                        Barcha hikoyalar
                    </Link>
                </section>
            </div>

            <ChildTabBar current="home" />
        </>
    );
}
