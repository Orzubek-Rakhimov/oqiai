# 03: Story files, the load command and the Story library

**What to build:** Stories live as human-readable files in the repository (title, Level 1–3, text in sentences, optional syllable overrides). A load command turns them into Stories, with sentences and words in the app, splitting every word into syllables by the Uzbek rule. A Child can open the library and see all Stories. See spec § Story files and loading.

**Blocked by:** 02

**Status:** done

- [x] The Story file format is documented in a short note next to the Story files, for the reviewing teacher and the team.
- [x] The load command creates new Stories, updates changed ones by slug, and is safe to run again.
- [x] Each word stores its displayed text, its normalised form (lower-case, no punctuation, apostrophe variants of o'/g' unified) and its syllables.
- [x] The syllable rule follows the spec: one vowel per syllable; o', g', sh, ch and ng are single letters; a consonant between vowels starts the next syllable; the last consonant of a cluster starts the next syllable.
- [x] Overrides in the file replace the rule's split for those words.
- [x] If a Story's words would change and Readings of it exist, the command refuses that Story with a clear message and leaves it unchanged.
- [x] The library shows every Story with its title, Level and minutes, and "Yangi" for Stories the Child hasn't read. It filters by Level. All Stories are open.
- [x] Console tests with small fixture Story files cover loading, re-running, overrides, syllable-rule cases and the refusal. HTTP tests cover the library data and filter.
