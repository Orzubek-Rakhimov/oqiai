# 06: Retry and Syllable help

**What to build:** After a sentence, each misread word gets one more try, read on its own. Correct on the retry → the Child moves on. Misread again → Syllable help shows the word split into syllables ("ku – tub – xo – na – ga"), and the Child reads it once more (not scored), then the Story continues. Outcomes are recorded for Accuracy, Reading speed and Hard words. See spec § Reading flow (Retry, Syllable help).

**Blocked by:** 04

**Status:** done

- [x] Misread words in a sentence are retried one at a time, in order, each recorded and checked like a sentence.
- [x] Correct on retry is recorded as "correct on retry".
- [x] Misread on retry opens Syllable help with the word's stored syllables.
- [x] In Syllable help the Child reads the word once more. That attempt isn't scored, and the word is recorded as "needed Syllable help" whatever the result.
- [x] After the last misread word, the next sentence (or finishing) follows. The Story never gets stuck.
- [x] Time spent in Syllable help is not added to reading time. Retry recordings are added.
- [x] Syllable help shows a "Tinglash" button. It plays audio once 07 lands, and until then is hidden or disabled when no clip exists.
- [x] Unscored failures (nothing heard, service error) during retry ask the Child to try again, as in 04.
- [x] HTTP feature tests with the fake driver cover retry success, Syllable help, the unscored final read, reading time and how outcomes affect Accuracy.
