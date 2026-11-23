import { useEffect, useState } from 'react';
import { show as showClip } from '@/routes/readings/clip';

/**
 * How often Syllable help asks for a clip that is still being made.
 */
const ASK_EVERY_MS = 4000;

/**
 * How many times it asks before giving up until the next Syllable help.
 */
const MOST_ASKS = 45;

/**
 * The voice clip of the Reading's pending word. A word's clip is made the
 * first time a Child needs it, so while it is missing this asks the server
 * every few seconds, and "Tinglash" can appear without a reload. A failed
 * ask is simply asked again; the Child is never told.
 */
export function useWordClip(
    readingId: number,
    initialClip: string | null,
): string | null {
    const [clip, setClip] = useState(initialClip);

    useEffect(() => {
        if (clip !== null) {
            return;
        }

        let asks = 0;
        let stopped = false;

        const timer = window.setInterval(() => {
            asks++;

            if (asks > MOST_ASKS) {
                window.clearInterval(timer);

                return;
            }

            fetch(showClip(readingId).url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((body: { clip: string | null } | null) => {
                    if (!stopped && body?.clip) {
                        window.clearInterval(timer);
                        setClip(body.clip);
                    }
                })
                .catch(() => {
                    // Asked again on the next tick.
                });
        }, ASK_EVERY_MS);

        return () => {
            stopped = true;
            window.clearInterval(timer);
        };
    }, [clip, readingId]);

    return clip;
}
