# 13: Connect the real speech-to-text and voice services

**What to build:** Add real drivers behind the interfaces from 04 and 07, chosen by configuration:
- **Listening:** `gemini-3.5-transcribe` (Gemini API, Interactions endpoint).
- **Voice:** `gemini-3.8-flash-tts`, with `gemini-3.8-flash-lite-tts` as the cheaper fallback.

The owner chose these from hands-on use, and reports that Google confirmed they may be used in this children's app. Gemini's published terms otherwise forbid services aimed at under-18s, so keep Google's written confirmation on file. The experiment confirms the models work on children's voices.

**Blocked by:** 04, 07, and the speech-to-text and voice experiment (not a ticket here). The experiment checks:
- `gemini-3.5-transcribe` in verbatim mode on recordings of children, counting how often a misread word passes as correct. The fallback is Google Cloud Speech-to-Text `chirp_3` (uz-UZ Preview).
- `gemini-3.8-flash-tts` on single Uzbek syllables and slow whole words, listening for syllables read as letter names. Fallbacks are Azure Madina and Aisha Gulnoza.

**Status:** ready-for-agent

- [ ] The speech-to-text driver calls `gemini-3.5-transcribe` with `language_codes: ["uz-UZ"]` in **verbatim** mode, and maps the transcript (plus `word_info` timings, if used) to the interface's recognised words in order.
- [ ] The driver never uses `smart` mode (it smooths over self-corrections and accepts a source text), and never puts Story words in `custom_vocabulary`. Both would hide misreadings. The model takes no prompt, so the Story text never reaches it.
- [ ] The text-to-speech driver calls `gemini-3.8-flash-tts`. Pace goes in the `speech_metadata.style` annotation (e.g. "speaking slowly and clearly, warm, for a young child"); pauses between syllables use inline `<short pause>` / `<long pause>` tags in the text. The voice is configurable. The returned WAV (24 kHz mono) is saved as the clip; transcoding to a smaller format is optional.
- [ ] Only the paid tier is used. The free tier may use content to improve Google's products and may have it read by human reviewers, so it must never receive children's audio. The guide in docs/deployment.md says so.
- [ ] A second speech-to-text driver, `chirp`, calls Google Cloud Speech-to-Text V2 with model `chirp_3`, `uz-UZ` and word time offsets, without phrase hints. The owner tests both by switching `SPEECH_TO_TEXT_DRIVER` between `gemini` and `chirp` in `.env`. Its sign-in uses a Google Cloud service account (key file path from the environment) or whatever the current docs require, with no new dependencies. The access token is cached until shortly before it expires.
- [ ] Model names are settings too, so variants can be tried without code changes: `GEMINI_TRANSCRIBE_MODEL` (default `gemini-3.5-transcribe`), `CHIRP_MODEL` (default `chirp_3`), `GEMINI_TTS_MODEL` (default `gemini-3.8-flash-tts`), `GEMINI_TTS_VOICE`.
- [ ] Drivers are chosen by configuration. The API key comes from the environment and is listed, empty, in `.env.production.example`. That file and the compose file set `SPEECH_TO_TEXT_DRIVER` (and the voice driver) to the real Gemini drivers. Note from ticket 12: an empty `${X:-}` placeholder in compose overrides the `env()` default, so don't pass driver names that way.
- [ ] Production refuses to start (in the container start-up checks) when the speech-to-text or voice driver is `fake`. The fake scores every sentence as perfect, so a misconfigured server must fail loudly rather than mislead Parents.
- [ ] Driver errors and timeouts surface as the unscored-failure path from 04, never as a crash.
- [ ] Tests cover each driver's request and response mapping with faked HTTP. No test calls a real service.
- [ ] Before closing, the model IDs and request shapes are re-checked against Google's current docs (aistudio.google.com/docs/transcribe and /docs/speech-generation), and the voice clips for the launch Stories are generated, listened to, and bad words regenerated.
