import { Head, Link, useHttp } from '@inertiajs/react';
import { CircleCheck, Loader2, Mic, MicOff, Square } from 'lucide-react';
import { useState } from 'react';
import MicrophoneCheckController from '@/actions/App/Http/Controllers/Parent/MicrophoneCheckController';
import ChildAvatar from '@/components/child-avatar';
import { Button } from '@/components/ui/button';
import { useSentenceRecorder } from '@/hooks/use-sentence-recorder';
import type { Recording } from '@/hooks/use-sentence-recorder';
import { cn } from '@/lib/utils';
import { picker } from '@/routes';
import type { AvatarKey, MicrophoneCheckResult } from '@/types';

type Props = {
    child: { id: number; name: string; avatar: AvatarKey };
    testSentence: string;
    checked: boolean;
};

type Problem =
    | 'blocked'
    | 'no-microphone'
    | 'unsupported'
    | 'nothing_heard'
    | 'service_failed'
    | 'network';

/** What went wrong, and what the Parent can do about it. */
const help: Record<Problem, { title: string; steps: string[] }> = {
    blocked: {
        title: 'Mikrofonga ruxsat berilmagan',
        steps: [
            'Manzil satridagi qulf belgisini bosing.',
            'Mikrofon uchun "Ruxsat berish" ni tanlang.',
            "Sahifani yangilab, qayta urinib ko'ring.",
        ],
    },
    'no-microphone': {
        title: 'Mikrofon topilmadi',
        steps: [
            'Quloqchin yoki mikrofon ulanganini tekshiring.',
            "Boshqa ilova mikrofonni band qilmaganiga ishonch hosil qiling va qayta urinib ko'ring.",
        ],
    },
    unsupported: {
        title: "Bu brauzer ovoz yozishni qo'llamaydi",
        steps: [
            "Ilovani Chrome yoki Safari brauzerining so'nggi versiyasida oching.",
        ],
    },
    nothing_heard: {
        title: 'Hech narsa eshitilmadi',
        steps: [
            'Telefonni farzandingizga yaqinroq tuting.',
            "Tinchroq joyda gapni baland va aniq o'qisin.",
            "Mikrofon tugmasini bosib, qayta urinib ko'ring.",
        ],
    },
    service_failed: {
        title: "Tekshirib bo'lmadi",
        steps: [
            "Bir ozdan so'ng qayta urinib ko'ring.",
            "Yoki hozircha o'tkazib yuboring: buni keyinroq farzandingiz profilidan qilishingiz mumkin.",
        ],
    },
    network: {
        title: "Internet bilan muammo bo'ldi",
        steps: ["Internet aloqasini tekshirib, qayta urinib ko'ring."],
    },
};

export default function MicrophoneCheck({
    child,
    testSentence,
    checked,
}: Props) {
    const [passed, setPassed] = useState(false);
    const [problem, setProblem] = useState<Problem | null>(null);

    const http = useHttp<{ audio: File | null }, MicrophoneCheckResult>({
        audio: null,
    });

    const recorder = useSentenceRecorder((recording) => {
        if (recording === null) {
            setProblem('nothing_heard');

            return;
        }

        send(recording);
    });

    const recorderProblem =
        recorder.state === 'blocked' ||
        recorder.state === 'no-microphone' ||
        recorder.state === 'unsupported'
            ? recorder.state
            : null;
    const shownProblem = recorderProblem ?? problem;

    function send(recording: Recording): void {
        http.transform(() => ({ audio: recording.audio }));

        http.post(MicrophoneCheckController.store.url(child.id), {
            onSuccess: (result) => {
                if (result.status === 'passed') {
                    setPassed(true);

                    return;
                }

                setProblem(result.reason);
            },
            onError: () => setProblem('service_failed'),
            onHttpException: () => {
                setProblem('service_failed');

                return false;
            },
            onNetworkError: () => {
                setProblem('network');

                return false;
            },
        }).catch(() => {
            // Each failure is shown through the handlers above.
        });
    }

    function listen(): void {
        setProblem(null);
        void recorder.start();
    }

    const listening = recorder.state === 'listening';
    const checking = http.processing;

    return (
        <>
            <Head title="Mikrofonni tekshirish" />

            <div className="mb-6 space-y-2">
                <h1 className="text-3xl font-extrabold">
                    Mikrofonni tekshiramiz
                </h1>
                <p className="text-muted-foreground">
                    Ustoz {child.name}ni eshita olishiga ishonch hosil qilaylik.
                    Brauzer mikrofon uchun ruxsat so'rasa, "Ruxsat berish" ni
                    bosing.
                </p>
            </div>

            {passed ? (
                <div
                    className="flex flex-col items-center gap-4 rounded-3xl border-2 bg-card p-6 text-center"
                    data-test="microphone-passed"
                >
                    <CircleCheck className="size-16 text-success" />
                    <h2 className="text-2xl font-extrabold">Ajoyib!</h2>
                    <p className="text-muted-foreground">
                        Ustoz {child.name}ni yaxshi eshitdi. Endi o'qishni
                        boshlash mumkin.
                    </p>
                    <Button
                        asChild
                        size="lg"
                        className="h-15 w-full rounded-full text-base"
                    >
                        <Link href={picker()}>Davom etish</Link>
                    </Button>
                </div>
            ) : (
                <>
                    <div className="flex flex-col items-center gap-5 rounded-3xl border-2 bg-card p-6 text-center">
                        <ChildAvatar avatar={child.avatar} size="lg" />
                        <p className="text-sm font-semibold text-muted-foreground">
                            {child.name}, mana bu gapni ovoz chiqarib o'qi:
                        </p>
                        <p className="font-heading text-3xl leading-tight font-extrabold">
                            {testSentence}
                        </p>

                        <button
                            type="button"
                            onClick={listening ? recorder.stop : listen}
                            disabled={checking || recorder.state === 'starting'}
                            aria-label={
                                listening
                                    ? "Yozishni to'xtatish"
                                    : 'Mikrofonni tekshirishni boshlash'
                            }
                            className={cn(
                                'inline-flex size-24 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg ring-8 ring-[#F5DED5] transition disabled:opacity-60',
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
                                  : "Bosing va gapni o'qing"}
                        </p>

                        {checked && shownProblem === null && (
                            <p className="text-sm text-muted-foreground">
                                Mikrofon avval tekshirilgan. Xohlasangiz, yana
                                bir bor tekshirib ko'ring.
                            </p>
                        )}
                    </div>

                    {shownProblem !== null && (
                        <div
                            role="alert"
                            className="mt-4 rounded-3xl border-2 border-destructive/30 bg-destructive/10 p-5"
                            data-test="microphone-help"
                        >
                            <h2 className="mb-2 flex items-center gap-2 font-extrabold text-destructive">
                                <MicOff className="size-5 shrink-0" />
                                {help[shownProblem].title}
                            </h2>
                            <ol className="list-decimal space-y-1 pl-5 text-sm">
                                {help[shownProblem].steps.map((step) => (
                                    <li key={step}>{step}</li>
                                ))}
                            </ol>
                        </div>
                    )}

                    <Link
                        href={picker()}
                        className="mt-4 inline-flex min-h-11 items-center justify-center self-center font-semibold text-muted-foreground"
                        data-test="skip-microphone-check"
                    >
                        Keyinroq
                    </Link>
                </>
            )}
        </>
    );
}
