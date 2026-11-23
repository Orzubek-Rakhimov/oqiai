# Story files (Hikoya fayllari)

Each Story is one plain-text file in this folder, named `<slug>.txt` (for example `ali-va-mushuk.txt`). The file name is the Story's slug, so do not rename a file once Children have read it. Load the files with:

```
php artisan stories:load
```

The command creates new Stories, updates changed ones by slug and can be run again at any time. Files are UTF-8.

## Format

```
title: Dengiz bo'yida
level: 3
minutes: 4
---
Biz kutubxonaga bordik. Keyin dengiz bo'yida o'ynadik.

— Qara, to'lqin! — dedi singlim.
---
dengiz: den-giz
```

1. **Header**, before the first `---` line: `title` (the Story's name), `level` (1, 2 or 3) and `minutes` (estimated reading time, a whole number). All three are required.
2. **Text**, after the first `---` line. Paragraphs are separated by an empty line. A sentence ends with `.`, `!`, `?` or `…` followed by a capital letter (a dialogue dash or opening quote may come before it), so `— Qara, to'lqin! — dedi singlim.` stays one sentence. Each sentence is read aloud in one go.
3. **Syllable overrides** (optional), after a second `---` line: one word per line, then `:` and its syllables joined with `-`. Use them where the automatic split is wrong, for example `dengiz: den-giz` (the rule treats `ng` as one letter and would give `de-ngiz`). The syllables must spell the word.

Both `o'` / `g'` apostrophes (`'`, `‘`, `’`, `ʻ`) are accepted and treated as the same letter. Capitals and punctuation do not matter for matching what the Child says.

## Automatic syllable split

`o'`, `g'`, `sh`, `ch` and `ng` count as single letters; the vowels are a, e, i, o, u and o'. Every syllable has exactly one vowel. One consonant between two vowels starts the next syllable (`bo-la`); when several consonants stand between two vowels, only the last one starts the next syllable (`kutubxona` → `ku-tub-xo-na`). Consonants at the start or end of the word stay with their vowel (`do'st`). A tutuq belgisi stays with the syllable before it (`ma'-no`).

## Changing a Story that Children have read

Once a Story has Readings, its words cannot change: the command refuses that Story with a message and leaves it as it was. The title, level, minutes and syllable overrides can still be changed. To change the words, add the text as a new Story file with a new slug.
