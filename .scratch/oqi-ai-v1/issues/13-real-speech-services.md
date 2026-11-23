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
- [ ] Drivers are chosen by configuration. The API key comes from the environment and is listed, empty, in `.env.production.example`.
- [ ] Driver errors and timeouts surface as the unscored-failure path from 04, never as a crash.
- [ ] Tests cover each driver's request and response mapping with faked HTTP. No test calls a real service.
- [ ] Before closing, the model IDs and request shapes are re-checked against Google's current docs (aistudio.google.com/docs/transcribe and /docs/speech-generation), and the voice clips for the launch Stories are generated, listened to, and bad words regenerated.
