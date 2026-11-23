import { Head, Link } from '@inertiajs/react';
import { Star } from 'lucide-react';
import StarRating from '@/components/star-rating';
import { home as childHome } from '@/routes/child';
import { store as storeReading } from '@/routes/readings';
import { index as storiesIndex } from '@/routes/stories';
import type { ReadingResult } from '@/types';

type Props = {
    story: { id: number; title: string };
    result: ReadingResult;
    nextStory: { id: number; title: string } | null;
};

const praise = {
    1: "Yaxshi harakat! Yana o'qib ko'r.",
    2: 'Juda yaxshi!',
    3: 'Ajoyib! Barakalla!',
} as const;

export default function Result({ story, result, nextStory }: Props) {
    const ring = 2 * Math.PI * 54;

    return (
        <>
            <Head title="Natija" />

            <div className="flex flex-1 flex-col items-center text-center">
                <p className="mt-2 text-sm font-semibold text-muted-foreground">
                    {story.title}
                </p>
                <h1 className="font-heading text-3xl font-black">
                    {praise[result.stars]}
                </h1>

                <StarRating
                    stars={result.stars}
                    className="mt-5 gap-2"
                    starClassName="size-12"
                />

                <div
                    className="relative mt-6 size-44"
                    role="img"
                    aria-label={`Aniqlik: ${result.accuracy}%`}
                >
                    <svg viewBox="0 0 120 120" className="size-full -rotate-90">
                        <circle
                            cx="60"
                            cy="60"
                            r="54"
                            fill="none"
                            strokeWidth="12"
                            className="stroke-muted"
                        />
                        <circle
                            cx="60"
                            cy="60"
                            r="54"
                            fill="none"
                            strokeWidth="12"
                            strokeLinecap="round"
                            strokeDasharray={ring}
                            strokeDashoffset={
                                ring * (1 - result.accuracy / 100)
                            }
                            className="stroke-teal"
                        />
                    </svg>
                    <div className="absolute inset-0 flex flex-col items-center justify-center">
                        <span
                            className="font-heading text-4xl font-black tabular-nums"
                            data-test="accuracy"
                        >
                            {result.accuracy}%
                        </span>
                        <span className="text-sm font-semibold text-muted-foreground">
                            Aniqlik
                        </span>
                    </div>
                </div>

                <dl className="mt-6 grid w-full grid-cols-3 gap-3">
                    <StatCard
                        label="So'z / daqiqa"
                        value={result.readingSpeed}
                    />
                    <StatCard
                        label="Yordam kerak bo'ldi"
                        value={result.wordsToPractise.length}
                    />
                    <StatCard
                        label="Yulduz"
                        value={
                            <span className="inline-flex items-center gap-1">
                                {result.stars}
                                <Star className="size-5 fill-star text-star" />
                            </span>
                        }
                    />
                </dl>

                {result.wordsToPractise.length > 0 && (
                    <section className="mt-6 w-full rounded-3xl border-2 bg-card p-4 text-left">
                        <h2 className="font-heading text-lg font-extrabold">
                            Mashq qilinadigan so&apos;zlar
                        </h2>
                        <ul
                            className="mt-3 flex flex-wrap gap-2"
                            data-test="words-to-practise"
                        >
                            {result.wordsToPractise.map((word) => (
                                <li
                                    key={word}
                                    className="rounded-[20px] bg-[#FDF0EA] px-3 py-1.5 font-semibold text-destructive"
                                >
                                    {word}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <div className="mt-auto flex w-full flex-col gap-3 pt-6 pb-4">
                    {nextStory ? (
                        <Link
                            href={storeReading(nextStory.id)}
                            as="button"
                            className="h-15 w-full rounded-full bg-primary text-lg font-bold text-primary-foreground hover:bg-[#8F2F18]"
                            data-test="next-story"
                        >
                            Keyingi hikoya
                        </Link>
                    ) : (
                        <Link
                            href={storiesIndex()}
                            className="inline-flex h-15 w-full items-center justify-center rounded-full bg-primary text-lg font-bold text-primary-foreground hover:bg-[#8F2F18]"
                            data-test="next-story"
                        >
                            Boshqa hikoya tanlash
                        </Link>
                    )}
                    <Link
                        href={childHome()}
                        className="inline-flex h-15 w-full items-center justify-center rounded-full border-2 bg-card text-lg font-bold"
                        data-test="back-home"
                    >
                        Bosh sahifaga qaytish
                    </Link>
                </div>
            </div>
        </>
    );
}

function StatCard({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex flex-col-reverse items-center gap-1 rounded-[18px] border-2 bg-card px-2 py-3">
            <dt className="text-xs leading-tight font-semibold text-muted-foreground">
                {label}
            </dt>
            <dd className="font-heading text-2xl font-black tabular-nums">
                {value}
            </dd>
        </div>
    );
}
