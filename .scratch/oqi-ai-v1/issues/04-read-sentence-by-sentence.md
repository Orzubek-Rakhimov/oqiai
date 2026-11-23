# 04: Read a Story sentence by sentence (fake speech-to-text)

**What to build:** A Child opens a Story and reads it aloud one sentence at a time. They tap the microphone button; the browser records until silence and sends the sentence. The server asks the speech-to-text interface what was said, discards the audio (ADR-0002), matches the words against the sentence, and returns a mark for each word (green for correct, red for misread). A fake driver stands in for the real service. See spec § Reading flow, § Speech-to-text interface.

**Blocked by:** 03

**Status:** done

- [x] A speech-to-text interface exists: audio + expected text + language → recognised words in order (with optional timing and confidence). The driver is chosen by configuration.
- [x] A fake driver returns scripted words. Tests and local demos use it.
- [x] Opening a Story starts a Reading, or resumes the Child's unfinished Reading of that Story at the next sentence.
- [x] The reading screen shows the title, the current sentence in large type, a progress bar, the microphone button, the "Tinglayapman…" state, and pause.
- [x] Recording stops on silence and the clip is sent. The audio is never written to permanent storage.
- [x] Matching is order-preserving and tolerates skipped and extra words. It ignores capitals and punctuation, and treats all o'/g' apostrophe variants as equal.
- [x] Words correct on the first try are recorded as such. Misread words are marked red. (Retry and Syllable help come in 06.)
- [x] If nothing was recognised or the service fails, nothing is recorded and the Child is asked to read the sentence again.
- [x] Recording length is added to the Reading's reading time.
- [x] HTTP feature tests with the fake driver cover start, resume, marks, the matching cases and unscored failures.
