import { useEffect, useRef, useState } from 'react';
import { encodeWav } from '@/lib/wav';

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

/**
 * An audio worklet that hands each block of microphone samples (the first
 * channel) to the page. Loaded from a blob, so it needs no file of its own.
 */
const CAPTURE_WORKLET = `
registerProcessor('sentence-capture', class extends AudioWorkletProcessor {
    process(inputs) {
        const channel = inputs[0] && inputs[0][0];

        if (channel) {
            this.port.postMessage(channel.slice(0));
        }

        return true;
    }
});
`;

async function loadCaptureWorklet(context: AudioContext): Promise<void> {
    const url = URL.createObjectURL(
        new Blob([CAPTURE_WORKLET], { type: 'text/javascript' }),
    );

    try {
        await context.audioWorklet.addModule(url);
    } finally {
        URL.revokeObjectURL(url);
    }
}

/**
 * Records one sentence from the microphone and stops by itself once the
 * Child goes quiet. The clip is a 16 kHz mono WAV, which every
 * speech-to-text service takes (Aisha takes no webm), written from the raw
 * samples rather than by MediaRecorder. It lives only in memory until it
 * is sent.
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
            typeof AudioWorkletNode === 'undefined' ||
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

        let capture: AudioWorkletNode;

        try {
            await loadCaptureWorklet(context);
            capture = new AudioWorkletNode(context, 'sentence-capture');
        } catch {
            stream.getTracks().forEach((track) => track.stop());
            void context.close();
            setState('unsupported');

            return;
        }

        const chunks: Float32Array[] = [];
        const source = context.createMediaStreamSource(stream);
        const analyser = context.createAnalyser();
        analyser.fftSize = 2048;
        source.connect(analyser);
        // The worklet only runs while it leads to the speakers, so it leads
        // there through a muted gain: nothing is heard.
        const mute = context.createGain();
        mute.gain.value = 0;
        source.connect(capture).connect(mute).connect(context.destination);
        const samples = new Float32Array(analyser.fftSize);

        const startedAt = performance.now();
        let heardSpeech = false;
        let lastSpeechAt = startedAt;
        let frame = 0;

        const finish = () => {
            capture.port.onmessage = null;
            source.disconnect();
            capture.disconnect();
            stream.getTracks().forEach((track) => track.stop());
            void context.close();
            setState('idle');

            const durationMs = Math.round(performance.now() - startedAt);

            onRecordedRef.current(
                heardSpeech && chunks.length > 0
                    ? {
                          audio: new File(
                              [encodeWav(chunks, context.sampleRate)],
                              'sentence.wav',
                              { type: 'audio/wav' },
                          ),
                          durationMs,
                      }
                    : null,
            );
        };

        const stop = () => {
            if (stopRef.current !== stop) {
                return;
            }

            stopRef.current = null;
            cancelAnimationFrame(frame);
            finish();
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

        capture.port.onmessage = (event: MessageEvent<Float32Array>) => {
            chunks.push(event.data);
        };

        stopRef.current = stop;
        setState('listening');
        frame = requestAnimationFrame(listen);
    }

    return {
        state,
        start,
        stop: () => stopRef.current?.(),
    };
}
