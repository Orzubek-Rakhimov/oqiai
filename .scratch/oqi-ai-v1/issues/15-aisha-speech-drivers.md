# 15: VoiceLab (Aisha) speech drivers beside Gemini

**What to build:** Add VoiceLab / Aisha (aisha.group, voicelab.uz) as a third listening service and a second voice service, chosen in `.env` like the existing ones. The owner wants to compare Aisha with Gemini (and Chirp) by using the app, then make the winner the default. Gemini and Chirp stay available, so switching back is one line.

Why Aisha: it's built specifically for Uzbek, so it may hear Uzbek children and pronounce Uzbek syllables better. Its voice is very cheap (about 1 UZS per character). As a local provider, it may also help with Uzbekistan's personal-data localisation rules. The owner should check that separately.

**Blocked by:** 13, 14

**Status:** ready-for-agent

- [ ] **Listening driver** `aisha` (`SPEECH_TO_TEXT_DRIVER=aisha`) implements the speech-to-text interface against Aisha's current speech-to-text API. It returns the recognised words in order. Word timings are optional: Aisha documents only segment offsets, and the app needs only ordered words. The expected Story text is never sent as a hint. Language: Uzbek.
- [ ] **Voice driver** `aisha` (`TEXT_TO_SPEECH_DRIVER=aisha`) implements the text-to-speech interface against Aisha's current text-to-speech API, keeping ticket 14's one-clip-per-word behaviour (syllables with pauses, then the whole word).
  - Pauses between syllables: if Aisha has no pause markup, use the simplest approach that sounds right. Options are punctuation between syllables ("ku. tub. xo. na. ga."), or voicing parts separately and joining the WAV audio with silence. Joining must stay within one clip per word, and should cost as few requests as possible.
  - Slow speech via Aisha's speed setting. The voice and any mood are settings.
- [ ] **Recording formats:** browsers send webm/opus (Chrome, Android) or mp4/aac (iPhone Safari). Check which formats Aisha accepts. If it doesn't accept these and converting would need a new dependency or a system tool such as ffmpeg, **stop and report** before adding anything.
- [ ] **Settings:** `AISHA_API_KEY`, plus any model, voice, mood or speed settings, with defaults in `config/services.php`. Add them to `.env.example`, `.env.production.example` and the compose file, avoiding the empty `${X:-}` trap. `php artisan speech:check` refuses an `aisha` driver with no key, as it does for Gemini.
- [ ] **Errors:** every HTTP, timeout, decoding or quota failure maps to `SpeechToTextFailed` / `TextToSpeechFailed`. A rate limit maps to `TextToSpeechRateLimited` so ticket 14's backoff applies, honouring `Retry-After` when present.
- [ ] **Docs:** `docs/deployment.md` explains how to switch to Aisha and back, and lists what to ask Aisha (rate limits, terms for children's apps, data location).
- [ ] **Tests:** each driver's request and response mapping and its error paths, with `Http::fake()` (Pest). No test calls the real service.
- [ ] **Uncertainty:** everything taken from Aisha's docs is re-checked against their current API documentation (e.g. aisha.group/en/api-documentation/speech-to-text and /text-to-speech). Anything undocumented or contradictory is listed in the report rather than guessed silently.
