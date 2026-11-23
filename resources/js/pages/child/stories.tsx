import { Head, Link } from '@inertiajs/react';
import { BookOpen, Star } from 'lucide-react';
import ChildTabBar from '@/components/child-tab-bar';
import StarRating from '@/components/star-rating';
import { cn } from '@/lib/utils';
import { store as storeReading } from '@/routes/readings';
import { index as storiesIndex } from '@/routes/stories';
import type { LibraryStory, StoryLevel } from '@/types';

const levelFilters: { level: StoryLevel | null; label: string }[] = [
    { level: null, label: 'Hammasi' },
    { level: 1, label: '1-daraja' },
    { level: 2, label: '2-daraja' },
    { level: 3, label: '3-daraja' },
];

const coverColours = ['#24665F', '#7A4E9E', '#B83E22', '#2F5F9E'];

export default function Stories({
    level,
    stories,
    starsTotal,
}: {
    level: StoryLevel | null;
    stories: LibraryStory[];
    starsTotal: number;
}) {
    return (
        <>
            <Head title="Hikoyalar" />

            <div className="pb-28">
                <div className="flex items-center justify-between gap-3">
                    <h1 className="font-heading text-3xl font-black">
                        Hikoyalar
                    </h1>
                    <p
                        className="inline-flex items-center gap-1.5 rounded-full border-2 bg-card px-3 py-1.5 font-heading text-lg font-black tabular-nums"
                        aria-label={`Jami yulduzlar: ${starsTotal}`}
                        data-test="stars-total"
                    >
                        <Star className="size-5 fill-star text-star" />
                        {starsTotal}
                    </p>
                </div>

                <nav
                    aria-label="Daraja bo'yicha saralash"
                    className="-mx-4 mt-5 flex gap-2 overflow-x-auto px-4 pb-1"
                >
                    {levelFilters.map((filter) => (
                        <Link
                            key={filter.label}
                            href={storiesIndex(
                                filter.level === null
                                    ? undefined
                                    : { query: { level: filter.level } },
                            )}
                            preserveScroll
                            aria-current={
                                filter.level === level ? 'page' : undefined
                            }
                            className={cn(
                                'inline-flex min-h-11 shrink-0 items-center rounded-[20px] border-2 bg-card px-4 text-sm font-semibold',
                                filter.level === level &&
                                    'border-foreground bg-foreground text-background',
                            )}
                        >
                            {filter.label}
                        </Link>
                    ))}
                </nav>

                {stories.length === 0 ? (
                    <p className="mt-10 text-center text-muted-foreground">
                        Bu darajada hozircha hikoya yo&apos;q.
                    </p>
                ) : (
                    <ul className="mt-5 grid grid-cols-2 gap-3">
                        {stories.map((story) => (
                            <li key={story.id} className="flex">
                                <Link
                                    href={storeReading(story.id)}
                                    as="button"
                                    className="flex w-full flex-col overflow-hidden rounded-3xl border-2 bg-card text-left"
                                    data-test="open-story"
                                >
                                    <div
                                        className="flex h-24 items-center justify-center text-white/90"
                                        style={{
                                            backgroundColor:
                                                coverColours[
                                                    story.id %
                                                        coverColours.length
                                                ],
                                        }}
                                        aria-hidden
                                    >
                                        <BookOpen className="size-10" />
                                    </div>
                                    <div className="flex flex-1 flex-col gap-1 p-3">
                                        <h2 className="font-heading text-base leading-tight font-extrabold">
                                            {story.title}
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            {story.level}-daraja ·{' '}
                                            {story.minutes} daqiqa
                                        </p>
                                        {story.bestStars !== null && (
                                            <StarRating
                                                stars={story.bestStars}
                                                className="mt-auto gap-0.5"
                                            />
                                        )}
                                        {story.isNew && (
                                            <span className="mt-auto self-start rounded-full bg-accent px-2.5 py-0.5 text-xs font-bold text-primary">
                                                Yangi
                                            </span>
                                        )}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>

            <ChildTabBar current="stories" />
        </>
    );
}
