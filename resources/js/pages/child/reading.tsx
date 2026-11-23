import { Head, Link, router, useHttp } from '@inertiajs/react';
import { Loader2, Mic, Pause, Square } from 'lucide-react';
import { useState } from 'react';
import { WordPractice } from '@/components/word-practice';
import { useSentenceRecorder } from '@/hooks/use-sentence-recorder';
import type { Recording } from '@/hooks/use-sentence-recorder';
import { cn } from '@/lib/utils';
import { show as showReading } from '@/routes/readings';
import { store as storeSentence } from '@/routes/readings/sentences';
import { index as storiesIndex } from '@/routes/stories';
import type {
    PendingWord,
    ReaderSentence,
    ReadingProgress,
    ReadingStep,
    SentenceResult,
    WordMark,
} from '@/types';

type Props = {
    reading: { id: number };
    story: { id: number; title: string };
    progress: ReadingProgress;
    sentence: ReaderSentence | null;
    pendingWord: PendingWord | null;
};

type SentenceForm = {
    sentence: number;
    audio: File | null;
    duration_ms: number;
};

type Scored = Extract<SentenceResult, { status: 'scored' }>;

const unheardMessages = {
    nothing_heard: "Seni eshitmadim. Gapni yana bir bor o'qib ber!",
    service_failed: "Nimadir xato ketdi. Gapni qaytadan o'qib ko'r!",
    network: "Internet bilan muammo bo'ldi. Gapni qaytadan o'qib ko'r!",
} as const;

const microphoneMessages = {
    blocked:
        'Mikrofonga ruxsat berilmagan. Brauzer sozlamalarida mikrofonni yoqing.',
    'no-microphone': 'Mikrofon topilmadi.',
    unsupported: "Bu brauzer ovoz yozishni qo'llamaydi.",
} as const;

export default function Reading({
    reading,
    story,
    progress,
    sentence,
    pendingWord,
}: Props) {
    const [current, setCurrent] = useState(sentence);
    const [practising, setPractising] = useState(pendingWord);
    const [currentProgress, setCurrentProgress] = useState(progress);
    const [scored, setScored] = useState<Scored | null>(null);
    const [unheard, setUnheard] = useState<keyof typeof unheardMessages | null>(
        null,
    );

    const http = useHttp<SentenceForm, SentenceResult>({
        sentence: sentence?.position ?? 0,
        audio: null,
        duration_ms: 0,
    });

    const recorder = useSentenceRecorder((recording) => {
        if (recording === null) {
            setUnheard('nothing_heard');

            return;
        }

        if (current !== null) {
            send(current.position, recording);
        }
    });

    function send(position: number, recording: Recording): void {
        http.transform(() => ({
            sentence: position,
            audio: recording.audio,
            duration_ms: recording.durationMs,
        }));

        http.post(storeSentence(reading.id).url, {
            onSuccess: (result) => {
                if (result.status === 'unheard') {
                    setUnheard(result.reason);

                    return;
                }

                setScored(result);
                setCurrentProgress(result.progress);
            },
            onError: () => setUnheard('service_failed'),
            onHttpException: (response) => {
                if (response.status === 409) {
                    // The Reading moved on elsewhere (another tab): show where it is now.
                    router.visit(showReading(reading.id));
                } else {
                    setUnheard('service_failed');
                }

                return false;
            },
            onNetworkError: () => {
                setUnheard('network');

                return false;
            },
        }).catch(() => {
            // Each failure is shown through the handlers above.
        });
    }

    function listen(): void {
        setUnheard(null);
        void recorder.start();
    }

    function nextSentence(): void {
        if (scored?.pendingWord) {
            setPractising(scored.pendingWord);
        } else if (scored?.nextSentence === null) {
            // The last sentence is read: the reading screen hands over to the result.
            router.visit(showReading(reading.id));

            return;
        } else {
            setCurrent(scored?.nextSentence ?? null);
        }

        setScored(null);
    }

    function afterWord(step: ReadingStep): void {
        setPractising(step.pendingWord);
        setCurrentProgress(step.progress);

        if (step.pendingWord === null && step.nextSentence === null) {
            // The last misread word is done: the reading screen hands over to the result.
            router.visit(showReading(reading.id));
        } else if (step.pendingWord === null) {
            setCurrent(step.nextSentence);
        }
    }

    const listening = recorder.state === 'listening';
    const checking = http.processing;
    const percent =
        currentProgress.total > 0
            ? Math.round((currentProgress.read / currentProgress.total) * 100)
            : 0;

    return (
        <>
            <Head title={story.title} />

            <div className="flex flex-1 flex-col">
                <div className="flex items-center gap-3">
                    <Link
                        href={storiesIndex()}
                        aria-label="To'xtatish"
                        className="inline-flex size-11 shrink-0 items-center justify-center rounded-full border-2 bg-card"
                        data-test="pause-reading"
                    >
                        <Pause className="size-5" />
                    </Link>
                    <div
                        role="progressbar"
                        aria-label="Hikoya bo'yicha jarayon"
                        aria-valuemin={0}
                        aria-valuemax={currentProgress.total}
                        aria-valuenow={currentProgress.read}
                        className="h-3 flex-1 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            className="h-full rounded-full bg-teal transition-[width] duration-500"
                            style={{ width: `${percent}%` }}
                        />
                    </div>
                    <span className="text-sm font-semibold text-muted-foreground tabular-nums">
                        {currentProgress.read}/{currentProgress.total}
                    </span>
                </div>

                <p className="mt-6 text-sm font-semibold text-muted-foreground">
                    O&apos;qish
                </p>
                <h1 className="font-heading text-2xl font-black">
                    {story.title}
                </h1>

                {practising ? (
                    <WordPractice
                        key={`${practising.sentence}-${practising.position}-${practising.stage}`}
                        readingId={reading.id}
                        word={practising}
                        onDone={afterWord}
                    />
                ) : current === null ? (
                    <ToResult readingId={reading.id} />
                ) : (
                    <>
                        <div
                            className={cn(
                                'mt-6 rounded-[26px] border-2 bg-card p-6',
                                listening && 'border-ring',
                            )}
                        >
                            <p
                                className="font-heading text-[32px] leading-tight font-extrabold"
                                data-test="sentence"
                            >
                                <SentenceWords
                                    words={current.words}
                                    marks={scored?.marks ?? null}
                                />
                            </p>
                        </div>

                        <div
                            className="mt-4 min-h-12 text-center"
                            aria-live="polite"
                        >
                            {unheard && (
                                <p className="rounded-2xl bg-accent px-4 py-3 font-semibold">
                                    {unheardMessages[unheard]}
                                </p>
                            )}
                            {recorder.state in microphoneMessages && (
                                <p className="rounded-2xl bg-accent px-4 py-3 font-semibold text-destructive">
                                    {
                                        microphoneMessages[
                                            recorder.state as keyof typeof microphoneMessages
                                        ]
                                    }
                                </p>
                            )}
                            {scored && (
                                <p className="font-semibold text-muted-foreground">
                                    {scored.marks.every((mark) => mark.correct)
                                        ? "Barakalla! Hammasi to'g'ri."
                                        : "Qizil so'zlarga e'tibor ber."}
                                </p>
                            )}
                        </div>

                        <div className="mt-auto flex flex-col items-center gap-3 pt-6 pb-4">
                            {scored ? (
                                <button
                                    type="button"
                                    onClick={nextSentence}
                                    className="h-15 w-full rounded-full bg-primary text-lg font-bold text-primary-foreground hover:bg-[#8F2F18]"
                                    data-test="next-sentence"
                                >
                                    {scored.pendingWord
                                        ? 'Davom etish'
                                        : scored.nextSentence
                                          ? 'Keyingi gap'
                                          : 'Tugatish'}
                                </button>
                            ) : (
                                <>
                                    <button
                                        type="button"
                                        onClick={
                                            listening ? recorder.stop : listen
                                        }
                                        disabled={
                                            checking ||
                                            recorder.state === 'starting'
                                        }
                                        aria-label={
                                            listening
                                                ? "Yozishni to'xtatish"
                                                : "O'qishni boshlash"
                                        }
                                        className={cn(
                                            'relative inline-flex size-24 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg ring-8 ring-[#F5DED5] transition disabled:opacity-60',
                                            listening &&
                                                'animate-pulse ring-[#EDC2B2]',
                                        )}
                                        data-test="microphone"
                                    >
                                        {checking ? (
                                            <Loader2 className="size-10 animate-spin" />
                                        ) : listening ? (
                                            <Square className="size-9 fill-current" />
                                        ) : (
                                            <Mic className="size-11" />
                                        )}
                                    </button>
                                    <p className="font-semibold text-muted-foreground">
                                        {checking
                                            ? 'Tekshiryapman…'
                                            : listening
                                              ? 'Tinglayapman…'
                                              : "Bosing va gapni o'qing"}
                                    </p>
                                </>
                            )}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

function SentenceWords({
    words,
    marks,
}: {
    words: string[];
    marks: WordMark[] | null;
}) {
    return words.map((word, index) => {
        const mark = marks?.[index];

        return (
            <span key={index}>
                {index > 0 && ' '}
                <span
                    className={cn(
                        mark?.correct === true && 'text-success',
                        mark?.correct === false &&
                            'rounded-lg bg-[#FDF0EA] px-1 text-destructive',
                    )}
                    data-mark={
                        mark
                            ? mark.correct
                                ? 'correct'
                                : 'misread'
                            : undefined
                    }
                >
                    {word}
                </span>
            </span>
        );
    });
}

/**
 * Shown if every sentence is read but the page has not moved on to the result yet.
 */
function ToResult({ readingId }: { readingId: number }) {
    return (
        <div className="mt-10 flex flex-col items-center gap-4 text-center">
            <p className="font-heading text-2xl font-black">
                Hikoya tugadi! Barakalla!
            </p>
            <Link
                href={showReading(readingId)}
                className="inline-flex h-15 w-full items-center justify-center rounded-full bg-primary text-lg font-bold text-primary-foreground"
            >
                Natijani ko&apos;rish
            </Link>
        </div>
    );
}
