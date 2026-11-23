# 15: VoiceLab (Aisha) speech drivers beside Gemini

**What to build:** Add VoiceLab / Aisha (aisha.group, voicelab.uz) as a third listening service and a second voice service, chosen in `.env` like the existing ones. The owner wants to compare Aisha with Gemini (and Chirp) by using the app, then make the winner the default. Gemini and Chirp stay available, so switching back is one line.

Why Aisha: it's built specifically for Uzbek, so it may hear Uzbek children and pronounce Uzbek syllables better. Its voice is very cheap (about 1 UZS per character). As a local provider, it may also help with Uzbekistan's personal-data localisation rules. The owner should check that separately.

**Blocked by:** 13, 14

**Status:** done (WAV recorder still to be tried by the owner on real phones)

- [x] **Listening driver** `aisha` (`SPEECH_TO_TEXT_DRIVER=aisha`) implements the speech-to-text interface against Aisha's current speech-to-text API. It returns the recognised words in order. Word timings are optional: Aisha documents only segment offsets, and the app needs only ordered words. The expected Story text is never sent as a hint. Language: Uzbek.
- [x] **Voice driver** `aisha` (`TEXT_TO_SPEECH_DRIVER=aisha`) implements the text-to-speech interface against Aisha's current text-to-speech API, keeping ticket 14's one-clip-per-word behaviour (syllables with pauses, then the whole word).
  - Pauses between syllables: if Aisha has no pause markup, use the simplest approach that sounds right. Options are punctuation between syllables ("ku. tub. xo. na. ga."), or voicing parts separately and joining the WAV audio with silence. Joining must stay within one clip per word, and should cost as few requests as possible.
  - Slow speech via Aisha's speed setting. The voice and any mood are settings.
- [x] **Recording formats (decided by the owner after a first look at Aisha's docs):** Aisha's sync speech-to-text endpoint (`POST /api/v1/stt/post/`) accepts only mp3, wav, ogg and m4a, so Chrome's and Android's webm recordings would be refused. **The browser recorder now records WAV for every service:** capture PCM with the Web Audio API and write 16 kHz mono 16-bit WAV in plain TypeScript, with no new dependency. This applies to reading sentences, retries and the microphone check (`use-sentence-recorder.ts` and anything sharing it). Silence detection, the 15 s maximum, the "nothing heard" handling and the blocked-microphone messages keep working. The upload stays under the 5 MB cap; a 15 s clip is about 480 KB. The server's audio validation keeps accepting the existing formats too. The Gemini and Chirp drivers send `audio/wav` correctly.
- [x] **Aisha details from its docs** (re-check before relying on them):
  - Listening: sync `POST https://back.aisha.group/api/v1/stt/post/` with `X-Api-Key`. The response has `transcript`; `has_offset` gives segment offsets with an undocumented shape.
  - Voice: `POST https://back.aisha.group/api/v1/tts/post/`, multipart (`transcript`, `language=uz`, `model=Gulnoza`, `mood`, `speed` 0.5–2.0), up to 1000 characters. It returns `201 {"audio_path": "/media/…wav"}`, a relative path that needs a second GET to download.
  - No pause markup, so syllables are joined with punctuation ("ku. tub. xo. na. ga.").
  - Errors: 400, 401, 402 (balance used up: treat it as a quota failure), 403, 503. No 429 or `Retry-After` is documented, so handle a 429 if it ever comes, as a rate limit.
- [x] **Settings:** `AISHA_API_KEY`, plus any model, voice, mood or speed settings, with defaults in `config/services.php`. Add them to `.env.example`, `.env.production.example` and the compose file, avoiding the empty `${X:-}` trap. `php artisan speech:check` refuses an `aisha` driver with no key, as it does for Gemini.
- [x] **Errors:** every HTTP, timeout, decoding or quota failure maps to `SpeechToTextFailed` / `TextToSpeechFailed`. A rate limit maps to `TextToSpeechRateLimited` so ticket 14's backoff applies, honouring `Retry-After` when present.
- [x] **Docs:** `docs/deployment.md` explains how to switch to Aisha and back, and lists what to ask Aisha (rate limits, terms for children's apps, data location).
- [x] **Tests:** each driver's request and response mapping and its error paths, with `Http::fake()` (Pest). No test calls the real service.
- [x] **Uncertainty:** everything taken from Aisha's docs is re-checked against their current API documentation (e.g. aisha.group/en/api-documentation/speech-to-text and /text-to-speech). Anything undocumented or contradictory is listed in the report rather than guessed silently.
