import { router, useHttp } from '@inertiajs/react';
import { Loader2, Mic, Square, Volume2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { useSentenceRecorder } from '@/hooks/use-sentence-recorder';
import type { Recording } from '@/hooks/use-sentence-recorder';
import { cn } from '@/lib/utils';
import { show as showReading } from '@/routes/readings';
import { store as storeRetry } from '@/routes/readings/retries';
import { store as storeSyllableHelp } from '@/routes/readings/syllable-help';
import type {
    PendingWord,
    ReadingStep,
    RetryResult,
    SyllableHelpResult,
} from '@/types';

type Props = {
    readingId: number;
    word: PendingWord;
    onDone: (step: ReadingStep) => void;
};

type RetryForm = {
    sentence: number;
    word: number;
    audio: File | null;
    duration_ms: number;
};

type SyllableHelpForm = {
    sentence: number;
    word: number;
};

const retryMessages = {
    nothing_heard: "Seni eshitmadim. So'zni yana bir bor o'qib ber!",
    service_failed: "Nimadir xato ketdi. So'zni qaytadan o'qib ko'r!",
    network: "Internet bilan muammo bo'ldi. So'zni qaytadan o'qib ko'r!",
} as const;

const microphoneMessages = {
    blocked:
        'Mikrofonga ruxsat berilmagan. Brauzer sozlamalarida mikrofonni yoqing.',
    'no-microphone': 'Mikrofon topilmadi.',
    unsupported: "Bu brauzer ovoz yozishni qo'llamaydi.",
} as const;

/**
 * One misread word: its single retry, or Syllable help when the retry was
 * misread too. Key it by word and stage so each step starts fresh.
 */
export function WordPractice({ readingId, word, onDone }: Props) {
    return word.stage === 'retry' ? (
        <WordRetry readingId={readingId} word={word} onDone={onDone} />
    ) : (
        <SyllableHelp readingId={readingId} word={word} onDone={onDone} />
    );
}

/**
 * Shows where the Reading is now after another tab moved it on.
 */
function reloadOnConflict(readingId: number, status: number): boolean {
    if (status === 409) {
        router.visit(showReading(readingId));

        return true;
    }

    return false;
}

function WordRetry({ readingId, word, onDone }: Props) {
    const [unheard, setUnheard] = useState<keyof typeof retryMessages | null>(
        null,
    );
    const [correctStep, setCorrectStep] = useState<ReadingStep | null>(null);

    const http = useHttp<RetryForm, RetryResult>({
        sentence: word.sentence,
        word: word.position,
        audio: null,
        duration_ms: 0,
    });

    const recorder = useSentenceRecorder((recording) => {
        if (recording === null) {
            setUnheard('nothing_heard');

            return;
        }

        send(recording);
    });

    function send(recording: Recording): void {
        http.transform(() => ({
            sentence: word.sentence,
            word: word.position,
            audio: recording.audio,
            duration_ms: recording.durationMs,
        }));

        http.post(storeRetry(readingId).url, {
            onSuccess: (result) => {
                if (result.status === 'unheard') {
                    setUnheard(result.reason);

                    return;
                }

                if (result.correct) {
                    setCorrectStep(result);
                } else {
                    onDone(result);
                }
            },
            onError: () => setUnheard('service_failed'),
            onHttpException: (response) => {
                if (!reloadOnConflict(readingId, response.status)) {
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

    const listening = recorder.state === 'listening';
    const checking = http.processing;

    return (
        <div className="flex flex-1 flex-col" data-test="word-retry">
            <p className="mt-6 text-center font-semibold text-muted-foreground">
                Bu so&apos;zni yana bir bor o&apos;qib ber
            </p>
            <div
                className={cn(
                    'mt-3 rounded-[26px] border-2 bg-card p-6 text-center',
                    listening && 'border-ring',
                )}
            >
                <p
                    className={cn(
                        'font-heading text-[40px] leading-tight font-black',
                        correctStep && 'text-success',
                    )}
                    data-test="retry-word"
                >
                    {word.text}
                </p>
            </div>

            <div className="mt-4 min-h-12 text-center" aria-live="polite">
                {unheard && (
                    <p className="rounded-2xl bg-accent px-4 py-3 font-semibold">
                        {retryMessages[unheard]}
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
                {correctStep && (
                    <p className="font-semibold text-success">
                        Barakalla! To&apos;g&apos;ri o&apos;qiding.
                    </p>
                )}
            </div>

            <div className="mt-auto flex flex-col items-center gap-3 pt-6 pb-4">
                {correctStep ? (
                    <button
                        type="button"
                        onClick={() => onDone(correctStep)}
                        className="h-15 w-full rounded-full bg-primary text-lg font-bold text-primary-foreground hover:bg-[#8F2F18]"
                        data-test="continue"
                    >
                        Davom etish
                    </button>
                ) : (
                    <>
                        <button
                            type="button"
                            onClick={listening ? recorder.stop : listen}
                            disabled={checking || recorder.state === 'starting'}
                            aria-label={
                                listening
                                    ? "Yozishni to'xtatish"
                                    : "So'zni o'qishni boshlash"
                            }
                            className={cn(
                                'relative inline-flex size-24 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg ring-8 ring-[#F5DED5] transition disabled:opacity-60',
                                listening && 'animate-pulse ring-[#EDC2B2]',
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
                                  : "Bosing va so'zni o'qing"}
                        </p>
                    </>
                )}
            </div>
        </div>
    );
}

/**
 * Syllable help: the word split into syllables, "Tinglash" to hear them
 * and then the whole word (only once the clips exist), and one more
 * unscored read by the Child before the Story continues.
 */
function SyllableHelp({ readingId, word, onDone }: Props) {
    const [heard, setHeard] = useState(false);
    const [playing, setPlaying] = useState(false);
    const [failed, setFailed] = useState(false);
    const audioRef = useRef<HTMLAudioElement | null>(null);

    const http = useHttp<SyllableHelpForm, SyllableHelpResult>({
        sentence: word.sentence,
        word: word.position,
    });

    const recorder = useSentenceRecorder(() => {
        // The final read is not scored, so the clip is never sent.
        finish();
    });

    const clips = [word.clips.syllables, word.clips.word].filter(
        (clip): clip is string => clip !== null,
    );

    function play(): void {
        if (clips.length === 0 || playing) {
            return;
        }

        const audio = audioRef.current ?? new Audio();
        audioRef.current = audio;
        let index = 0;

        const playNext = (): void => {
            if (index >= clips.length) {
                setPlaying(false);
                setHeard(true);

                return;
            }

            audio.src = clips[index++];
            audio.play().catch(() => setPlaying(false));
        };

        audio.onended = playNext;
        audio.onerror = () => setPlaying(false);
        setPlaying(true);
        playNext();
    }

    function finish(): void {
        setFailed(false);

        http.post(storeSyllableHelp(readingId).url, {
            onSuccess: (result) => onDone(result),
            onError: () => setFailed(true),
            onHttpException: (response) => {
                if (!reloadOnConflict(readingId, response.status)) {
                    setFailed(true);
                }

                return false;
            },
            onNetworkError: () => {
                setFailed(true);

                return false;
            },
        }).catch(() => {
            // Each failure is shown through the handlers above.
        });
    }

    const listening = recorder.state === 'listening';
    const microphoneUnavailable = recorder.state in microphoneMessages;

    return (
        <div className="flex flex-1 flex-col" data-test="syllable-help">
            <p className="mt-6 text-center font-semibold text-muted-foreground">
                Keling, bo&apos;g&apos;inlab o&apos;qiymiz
            </p>
            <div className="mt-4 flex flex-wrap items-center justify-center gap-2">
                {word.syllables.map((syllable, index) => (
                    <span key={index} className="flex items-center gap-2">
                        {index > 0 && (
                            <span
                                aria-hidden
                                className="font-heading text-2xl font-black text-muted-foreground"
                            >
                                –
                            </span>
                        )}
                        <span
                            className={cn(
                                'inline-flex h-13 min-w-13 items-center justify-center rounded-2xl px-4 font-heading text-[26px] font-extrabold transition-colors',
                                heard || listening
                                    ? 'bg-[#F0B43C] text-primary'
                                    : 'bg-[#3A7D74] text-white',
                            )}
                            data-test="syllable"
                        >
                            {syllable}
                        </span>
                    </span>
                ))}
            </div>
            <p className="mt-4 text-center font-heading text-2xl font-black">
                {word.text}
            </p>

            <div className="mt-4 min-h-12 text-center" aria-live="polite">
                {failed && (
                    <p className="rounded-2xl bg-accent px-4 py-3 font-semibold">
                        Internet bilan muammo bo&apos;ldi. Yana bir bor urinib
                        ko&apos;r!
                    </p>
                )}
                {listening && (
                    <p className="font-semibold text-muted-foreground">
                        Tinglayapman…
                    </p>
                )}
            </div>

            <div className="mt-auto flex flex-col gap-3 pt-6 pb-4">
                {clips.length > 0 && (
                    <button
                        type="button"
                        onClick={play}
                        disabled={playing || listening}
                        className="inline-flex h-15 w-full items-center justify-center gap-2 rounded-full border-2 bg-card text-lg font-bold disabled:opacity-60"
                        data-test="listen"
                    >
                        <Volume2 className="size-5" />
                        Tinglash
                    </button>
                )}
                <button
                    type="button"
                    onClick={
                        failed || microphoneUnavailable
                            ? finish
                            : listening
                              ? recorder.stop
                              : () => void recorder.start()
                    }
                    disabled={
                        playing ||
                        http.processing ||
                        recorder.state === 'starting'
                    }
                    className="inline-flex h-15 w-full items-center justify-center gap-2 rounded-full bg-primary text-lg font-bold text-primary-foreground hover:bg-[#8F2F18] disabled:opacity-60"
                    data-test="read-myself"
                >
                    {http.processing ? (
                        <Loader2 className="size-5 animate-spin" />
                    ) : listening ? (
                        <Square className="size-5 fill-current" />
                    ) : (
                        <Mic className="size-5" />
                    )}
                    {failed || microphoneUnavailable
                        ? 'Davom etish'
                        : listening
                          ? 'Tayyor'
                          : "Men o'qiyman"}
                </button>
            </div>
        </div>
    );
}
