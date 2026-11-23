# 13: Connect the real speech-to-text and voice services

**What to build:** Add real drivers behind the interfaces from 04 and 07, chosen by configuration. **Preferred services (the owner's choice, from hands-on use): Gemini 3.5 for transcription, and Gemini Flash 3.8 TTS for the voice.** One Google account covers both. The experiment confirms they work on children's voices. The other candidates are fallbacks only.

**Blocked by:** 04, 07, and the speech-to-text and voice experiment (not a ticket here). The experiment checks:
- Gemini 3.5 transcription on recordings of children, run both with and without the sentence's text in the prompt. Fallbacks are ElevenLabs Scribe and Azure Speech.
- Gemini Flash 3.8 TTS on single Uzbek syllables and slow whole words. Fallbacks are Azure Madina and Aisha Gulnoza.

**Status:** ready-for-agent

- [ ] A speech-to-text driver for Gemini returns the words the Child actually said, in order. Its prompt asks for a verbatim transcript and never asks it to correct toward the expected text. Whether the sentence's text is included as context follows the experiment's result.
- [ ] A text-to-speech driver for Gemini reads syllables slowly with pauses between them, steered by its prompt.
- [ ] Before this ticket closes, Uzbek support for both Gemini models is confirmed in Google's current documentation, and the models' current names are checked.
- [ ] Drivers are chosen by configuration, and keys come from the environment.
- [ ] Driver errors and timeouts surface as the unscored-failure path from 04, never as a crash.
- [ ] Tests cover each driver's request and response mapping with faked HTTP. No test calls a real service.
- [ ] The voice clips for the launch Stories are generated, listened to, and any bad words regenerated.
