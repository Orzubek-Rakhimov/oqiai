# O'qi AI

A voice reading tutor for Uzbek primary-school children: the child reads a story aloud, the tutor listens and helps with the words they get wrong, and a parent follows their progress.

## People

**Parent** (Ota-ona):
The adult who signs up, owns one or more Child profiles, and sees their reading progress. The only person with a login; on a shared phone they reach their screen from the "Who is reading?" picker.
_Avoid_: User, account, guardian

**Child** (Bola):
A child aged roughly 6–10 (grades 1–4) who reads aloud. Exists only as a profile inside a Parent's account and never logs in.
_Avoid_: Student, pupil, kid, user

**Teacher** (O'qituvchi):
A school teacher who follows a class of Children. Out of scope for v1.
_Avoid_: Educator, instructor

## Reading

**Story** (Hikoya):
A short text written in Uzbek Latin script for a Child to read aloud, reviewed by a person before release.
_Avoid_: Book, text, lesson

**Level** (Daraja):
A difficulty tier that every Story belongs to.
_Avoid_: Grade, stage, difficulty

**Grade** (Sinf):
The Child's school year (1–4). It decides which Level is suggested first, not which Stories are allowed.
_Avoid_: Class, year, level

**Reading** (O'qish):
One attempt by a Child to read one Story from start to finish. It can be paused and resumed. Only a finished Reading earns Stars or counts toward the Streak.
_Avoid_: Session, attempt, lesson, practice

**Syllable help** (Bo'g'inlab o'qish):
The step where the tutor breaks a word into syllables and reads them with the Child, after the Child has misread that word twice.
_Avoid_: Correction screen, hint, mistake screen

## Progress

**Accuracy** (Aniqlik):
The share of a Story's words that the Child read correctly on the first try during a Reading.
_Avoid_: Score, correctness, percentage

**Stars** (Yulduz):
A 1–3 rating that a finished Reading earns from its Accuracy. A Child's star total is the sum of their best Stars per Story.
_Avoid_: Points, coins, XP

**Reading speed** (O'qish tezligi):
The number of words a Child reads correctly per minute of reading, not counting time spent in Syllable help.
_Avoid_: WPM, fluency, tempo

**Hard word** (Qiyin so'z):
A word the Child needed a retry or Syllable help for in the last 7 days.
_Avoid_: Mistake, error word, problem word

**Streak** (Ketma-ket kunlar):
The number of consecutive days, in Tashkent time, on which the Child finished at least one Reading.
_Avoid_: Daily goal, chain
