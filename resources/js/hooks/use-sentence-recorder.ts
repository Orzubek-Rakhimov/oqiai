import { useEffect, useRef, useState } from 'react';

export type Recording = {
    audio: File;
    durationMs: number;
};

export type RecorderState =
    | 'idle'
    | 'starting'
    | 'listening'
    | 'blocked'
    | 'no-microphone'
    | 'unsupported';

/** The level (RMS) above which the Child is taken to be speaking. */
const SPEECH_LEVEL = 0.02;
/** How long a quiet spell after speech ends the recording. */
const SILENCE_MS = 1300;
/** How long to wait for any speech before giving up. */
const NOTHING_HEARD_MS = 6000;
/** The longest a single sentence may be recorded. */
const MAX_MS = 15000;

const mimeTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'];

function fileNameFor(mimeType: string): string {
    return mimeType.includes('mp4') ? 'sentence.m4a' : 'sentence.webm';
}

/**
 * Records one sentence from the microphone and stops by itself once the
 * Child goes quiet. The clip lives only in memory until it is sent.
 */
export function useSentenceRecorder(
    onRecorded: (recording: Recording | null) => void,
) {
    const [state, setState] = useState<RecorderState>('idle');
    const stopRef = useRef<(() => void) | null>(null);
    const onRecordedRef = useRef(onRecorded);

    useEffect(() => {
        onRecordedRef.current = onRecorded;
    });

    useEffect(() => () => stopRef.current?.(), []);

    async function start(): Promise<void> {
        if (
            typeof MediaRecorder === 'undefined' ||
            !navigator.mediaDevices?.getUserMedia
        ) {
            setState('unsupported');

            return;
        }

        setState('starting');

        const context = new AudioContext();
        // iOS only lets audio start inside the tap that asked for it.
        void context.resume();

        let stream: MediaStream;

        try {
            stream = await navigator.mediaDevices.getUserMedia({
                audio: { echoCancellation: true, noiseSuppression: true },
            });
        } catch (error) {
            void context.close();
            setState(
                error instanceof DOMException && error.name === 'NotFoundError'
                    ? 'no-microphone'
                    : 'blocked',
            );

            return;
        }

        const mimeType = mimeTypes.find((type) =>
            MediaRecorder.isTypeSupported(type),
        );
        const recorder = new MediaRecorder(
            stream,
            mimeType ? { mimeType } : undefined,
        );
        const chunks: Blob[] = [];
        const analyser = context.createAnalyser();
        analyser.fftSize = 2048;
        context.createMediaStreamSource(stream).connect(analyser);
        const samples = new Float32Array(analyser.fftSize);

        const startedAt = performance.now();
        let heardSpeech = false;
        let lastSpeechAt = startedAt;
        let frame = 0;

        const stop = () => {
            stopRef.current = null;
            cancelAnimationFrame(frame);

            if (recorder.state !== 'inactive') {
                recorder.stop();
            }
        };

        const listen = () => {
            analyser.getFloatTimeDomainData(samples);
            const level = Math.sqrt(
                samples.reduce((sum, sample) => sum + sample * sample, 0) /
                    samples.length,
            );
            const now = performance.now();

            if (level > SPEECH_LEVEL) {
                heardSpeech = true;
                lastSpeechAt = now;
            }

            const quietAfterSpeech =
                heardSpeech && now - lastSpeechAt > SILENCE_MS;
            const nothingHeard =
                !heardSpeech && now - startedAt > NOTHING_HEARD_MS;

            if (quietAfterSpeech || nothingHeard || now - startedAt > MAX_MS) {
                stop();

                return;
            }

            frame = requestAnimationFrame(listen);
        };

        recorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                chunks.push(event.data);
            }
        };

        recorder.onstop = () => {
            stream.getTracks().forEach((track) => track.stop());
            void context.close();
            setState('idle');

            const durationMs = Math.round(performance.now() - startedAt);
            const type = recorder.mimeType || mimeType || 'audio/webm';

            onRecordedRef.current(
                heardSpeech && chunks.length > 0
                    ? {
                          audio: new File(chunks, fileNameFor(type), { type }),
                          durationMs,
                      }
                    : null,
            );
        };

        stopRef.current = stop;
        recorder.start();
        setState('listening');
        frame = requestAnimationFrame(listen);
    }

    return {
        state,
        start,
        stop: () => stopRef.current?.(),
    };
}
