# 14: Voice clips are made on first use

**What to build:** The owner no longer has to run `php artisan audio:generate` before Syllable help can speak. A word's two clips (syllables read slowly with pauses, and the whole word) are made the first time a Child needs them, through the text-to-speech interface from ticket 07, saved where ticket 07 saves them, and reused by every Child after that. The owner chose this over making all clips in advance: no command to run, no rate-limit problems from voicing about 900 clips at once, and only words Children actually struggle with cost anything. The trade-off the owner accepted: the first Child to need a word may wait a few seconds, and hears that clip before anyone has checked it.

**Blocked by:** 07, 13

**Status:** done

- [x] When a word enters the retry stage (its first misread), its missing clips start being made at once, so they're usually ready by the time a second misread opens Syllable help. This must not slow the sentence response the Child is waiting on.
- [x] If Syllable help opens and a clip is still missing, the reader gets it without a page reload: "Tinglash" appears once the clip exists. It plays the syllables clip, then the word clip, as before.
- [x] A word is voiced at most once, even when several Children or requests need it at the same moment.
- [x] If the voice service fails or times out, the Child's reading flow is unaffected. "Tinglash" stays hidden for that word, and a later request tries again.
- [x] Clips exist only for words of Stories in the app, and only an active Child reading that Story can trigger them. Any new endpoint follows the existing Reading policy and has a sensible rate limit.
- [x] Rate limits are expected: the owner's first Gemini key hit `429` at 3 requests per minute. A `429` (or other rate-limit or quota answer) never surfaces to the Child. The clip is retried later with backoff, honouring `Retry-After` when present, and "Tinglash" appears once it exists.
- [x] One voice request per word instead of two: a single request speaks the syllables with pauses and then the whole word, and both clips the reader needs come from that one answer. Splitting a single WAV is fine, or the reader may play one combined clip. Keep the ticket 07 player behaviour (syllables, then word) as the Child hears it.
- [x] `php artisan audio:generate {word}` still regenerates one word's clips after the owner listens to it. Bulk `audio:generate` stays available but is optional, and `docs/deployment.md` and `resources/stories/README.md` say so.
- [x] Works in production as configured in `compose.production.yaml`: check the queue connection there. If the design relies on a queue, it must actually run in production (e.g. a worker service, as ITPlaza's guide describes), or the design must not need one.
- [x] HTTP and console tests (Pest, fake voice driver via `voice()`, `Storage::fake('public')`) cover: clips made on the first misread, no second voicing of an existing clip, clips appearing for Syllable help, a voice failure leaving the flow unaffected, and access control.
