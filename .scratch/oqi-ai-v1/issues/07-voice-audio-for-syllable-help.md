# 07: Voice audio for Syllable help

**What to build:** Each Story word gets two saved clips: its syllables read slowly with pauses, and the whole word read slowly. A command generates them through a text-to-speech interface. "Tinglash" in Syllable help plays the syllables, then the whole word. See spec § Text-to-speech interface and audio.

**Blocked by:** 06

**Status:** ready-for-agent

- [ ] A text-to-speech interface exists: text + speaking options (slow rate, pauses) → audio. The driver is chosen by configuration. A fake driver is provided.
- [ ] The generation command creates any missing clips for all Story words and skips existing ones.
- [ ] The command can regenerate the clips for one named word.
- [ ] Clips are saved to public storage with stable names derived from the word's normalised form and syllables. The same word in different Stories shares clips.
- [ ] "Tinglash" plays the syllable clip, then the whole-word clip.
- [ ] Console tests with the fake driver cover generation, skipping and single-word regeneration. An HTTP test checks that Syllable help gets the clip URLs.
