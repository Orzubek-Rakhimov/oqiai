# O'qi AI v1: voice reading tutor for a Child and their Parent

Status: ready-for-agent

## Problem Statement

Uzbek parents of primary-school children (grades 1–4) cannot easily tell whether their Child reads well, and have no tool that listens while the Child reads aloud. A Child who stumbles on a long word ("kutubxonaga") at home has nobody to break it into syllables and read it with them. A Parent who wants to help has no clear view of what to practise or whether the Child is improving. Reading apps that listen exist for English, but not for Uzbek.

v1 is a test-and-showcase build. Its job is to prove, with 10–20 real families, that a Child will read Stories aloud to the tutor, that the help on misread words works, and that Parents find the progress view convincing.

## Solution

A phone-first web app (see ADR-0001) in Uzbek with two sides:

- **Child side.** The Child picks their profile, opens a Story and reads it aloud one sentence at a time. After each sentence the tutor marks every word as correct or misread. A misread word gets one more try, and if it is misread again, Syllable help breaks it into syllables, reads them aloud and lets the Child try once more before the Story continues. A finished Reading earns 1–3 Stars from its Accuracy, and finishing a Reading each day keeps the Streak going.
- **Parent side.** The Parent signs up with phone number and password and creates one or more Child profiles. For each Child they see days practised this week, Stories finished, Reading speed with its trend, and the Child's Hard words.

Children's voice recordings are never stored (see ADR-0002). Only the per-word results are kept.

The visual reference is the "O'qi AI — mobil ilova prototipi" design canvas (13 screens). It shows the layout, colours (warm cream, dark plum, terracotta accent) and type (Nunito headings, Inter body). Screens and features this spec cuts (badges, daily goal, Telegram report, advice box, Teacher screens, locked Stories) are not built even though the canvas shows them.

## User Stories

### Parent account

1. As a Parent, I want to sign up with my phone number, my name and a password, so that I can start without an email address.
2. As a Parent, I want to type my phone number in the usual local ways (with or without +998, spaces or dashes), so that sign-up does not reject a number I typed correctly.
3. As a Parent, I want to be told clearly if my phone number is already registered, so that I log in instead of making a second account.
4. As a Parent, I want to log in with my phone number and password, so that I can come back to my Children's progress.
5. As a Parent, I want sign-up to need no SMS code, so that I can start immediately during the test.
6. As a Parent, I want to log out, so that another family member can use the phone.
7. As a Parent, I want to change my name and password in settings, so that I can keep my account up to date.
8. As a Parent, I want every screen to be in Uzbek (Latin script), so that I and my Child understand it.

### Child profiles

9. As a Parent, I want to be asked to create my first Child profile right after sign-up, so that my Child can start reading quickly.
10. As a Parent, I want to give a Child profile a name, a Grade (1–4) and an avatar from a small set, so that my Child recognises their profile.
11. As a Parent, I want to add more than one Child, so that siblings can each have their own progress.
12. As a Parent, I want to edit a Child's name, Grade or avatar, so that I can fix a mistake or move them up a Grade.
13. As a Parent, I want to delete a Child profile, so that I can remove a profile created by mistake.
14. As a Parent, I want a Child profile to ask for nothing beyond name, Grade and avatar, so that I share as little about my Child as possible.
15. As a Parent, I want only my own Children to be visible and reachable from my account, so that no other family can see them.

### Microphone check

16. As a Parent, I want the app to ask for microphone permission while I set up my first Child, so that the first Story does not fail because the microphone is blocked.
17. As a Child, I want to read a short test sentence ("Salom! Mening ismim …" with my name) during setup, so that we know the tutor can hear me.
18. As a Parent, I want to be told clearly if the microphone is blocked or nothing was heard, with what to do next, so that I can fix it before my Child starts.
19. As a Parent, I want to skip the microphone check and do it later, so that setup never traps me.

### Who is reading?

20. As a Parent on a shared phone, I want the app to open on a "Who is reading?" picker showing each Child's avatar and name, so that the right Child reads under the right profile.
21. As a Child, I want to tap my avatar to go straight to my home screen, so that I can start without help.
22. As a Parent, I want a Parent button on the picker that opens my Parent screen without a PIN, so that I can check progress quickly.
23. As a Child, I want to get back to the picker from my home screen, so that I can hand the phone to my sibling.

### Child home and Story library

24. As a Child, I want my home screen to greet me by name and show my Streak, so that I feel welcomed and want to keep the Streak going.
25. As a Child, I want my home screen to suggest a Story at the Level that fits my Grade, so that I know what to read next.
26. As a Child, I want an unfinished Story shown on my home screen with how far I got, so that I can continue where I stopped.
27. As a Child, I want to see all Stories in a library, so that I can choose one I like.
28. As a Child, I want to filter the library by Level, so that I can find easier or harder Stories.
29. As a Child, I want each Story in the library to show its Level, its length in minutes, my best Stars, or "Yangi" if I have not read it, so that I can choose with confidence.
30. As a Child, I want every Story to be open whatever my Grade, so that I can try any Story.
31. As a Child, I want to see my Stars total, so that I feel my progress add up.

### Reading a Story

32. As a Child, I want to see the Story's title and the current sentence in large, easy-to-read type, so that I can read it comfortably.
33. As a Child, I want to tap one big microphone button to start reading a sentence, so that I know when the tutor is listening.
34. As a Child, I want the tutor to stop listening by itself when I stop talking, so that I don't have to press anything when I finish.
35. As a Child, I want to see a "Tinglayapman…" (listening) state while I read, so that I know the tutor hears me.
36. As a Child, I want each word of the sentence marked correct (green) or misread (red) after I read it, so that I see exactly which words I got right.
37. As a Child, I want a progress bar for how far I am through the Story, so that I know how much is left.
38. As a Child, I want to pause a Story and leave, so that I can stop when I need to.
39. As a Child, I want to resume a paused Story from the sentence where I stopped, so that I don't have to start over.
40. As a Child, I want words with the Uzbek apostrophe letters (o', g') and capital letters or punctuation to count as correct when I say them right, so that I am not marked wrong for how the text is typed.
41. As a Child, I want a friendly message if the tutor heard nothing or something went wrong, and the chance to read the sentence again, so that a glitch doesn't count against me.

### Retry and Syllable help

42. As a Child, when I misread a word, I want one more try at that word, so that a small slip doesn't stop me.
43. As a Child, when I read the word right on my second try, I want to move on, so that reading keeps flowing.
44. As a Child, when I misread the same word twice, I want Syllable help to show it split into syllables ("ku – tub – xo – na – ga"), so that I can see how it is built.
45. As a Child, in Syllable help, I want to tap "Tinglash" (listen) to hear each syllable and then the whole word read slowly, so that I know how it should sound.
46. As a Child, after Syllable help, I want to read the word once more myself and then continue the Story, so that I practise it right away and the Story never gets stuck.
47. As a Child, I want words that needed a retry or Syllable help to not count as correct for my Accuracy, so that my Stars reflect what I read on my own.
48. As a Child, I want time spent in Syllable help left out of my Reading speed, so that getting help doesn't make me look slow.

### Finishing a Reading

49. As a Child, when I finish the last sentence, I want a result screen with my Stars (1–3), my Accuracy and my Reading speed, so that I see how I did.
50. As a Child, I want to see the words I needed help with on the result screen, so that I know what to practise.
51. As a Child, I want my Stars total to count only my best Stars per Story, so that I'm encouraged to try new Stories, not repeat one.
52. As a Child, I want to read a Story again to beat my best Stars, so that I can improve.
53. As a Child, I want buttons for "next Story" and "back to home" on the result screen, so that I can keep going or stop.
54. As a Child, I want finishing at least one Reading today (Tashkent time) to count for my Streak, so that my effort each day is recognised.
55. As a Child, I want an unfinished Reading to give no Stars and not count for my Streak, so that the rules are fair and simple.

### Parent screen

56. As a Parent, I want a screen per Child showing how many days they practised this week, so that I know if they read regularly.
57. As a Parent, I want to see how many Stories my Child finished this week, so that I see how much they read.
58. As a Parent, I want to see my Child's Reading speed this week and how it changed over the last four weeks, so that I see whether they are improving.
59. As a Parent, I want to see my Child's top 5 Hard words from the last 7 days, so that I can practise them together.
60. As a Parent, I want the Parent screen to show the current Streak and Stars total, so that I know what motivates my Child.
61. As a Parent, I want a helpful empty state when my Child hasn't read yet, so that the screen isn't confusing on day one.
62. As a Parent, I want to switch between my Children on the Parent screen, so that I can check each one.
63. As a Parent, I want to be told that the reading voice is an AI voice and that recordings are not stored, so that I can trust the app with my Child.

### Stories and voice audio (team)

64. As the team, we want each Story kept as a plain file in the repository with its title, Level and text, so that the reviewing teacher can read it and every change is tracked.
65. As the team, we want one command to load all Story files into the app, safely re-runnable, so that adding or fixing a Story is one step.
66. As the team, we want the app to split each word into syllables by Uzbek rules automatically, so that we don't hand-split every word.
67. As the team, we want a Story file to override the syllable split for specific words, so that words the rule gets wrong are shown correctly.
68. As the team, we want the load command to refuse a change to a Story's words once Children have Readings of it, so that existing results never point at words that changed.
69. As the team, we want one command to generate and save the voice audio for every word and its syllables, skipping audio that already exists, so that audio is made once, checked by ear and served instantly.
70. As the team, we want to regenerate the audio for a single word after listening to it, so that a mispronounced word can be fixed on its own.
71. As the team, we want the speech-to-text and text-to-speech services to be configurable without changing app code, so that we can use whichever wins the experiment.
72. As the team, we want the 9 launch Stories (3 per Level) in place before testers arrive, so that the test has real content.

## Implementation Decisions

### Platform and accounts

- Phone-first web app with Laravel + Inertia + React, built on the React starter kit already in the repo (ADR-0001). All UI copy is Uzbek Latin. Layouts target phone width first and stay usable on a tablet or desktop.
- The **Parent** is the existing user account. The phone number becomes the login identifier in place of email. Numbers are normalised to one canonical form (+998 followed by 9 digits) before they are stored or looked up, and they are unique.
- Sign-up asks for name, phone number and password, with no SMS or email verification.
- These starter-kit authentication features are turned off because they depend on email or add steps testers don't need: email verification, password reset by email, two-factor login and passkeys. Profile settings edit name and phone instead of email.
- A Parent who forgets their password asks the team, who reset it by hand. There is no self-service reset in v1.

### Domain model and schema

- **Child**: belongs to one Parent. Fields: name, Grade (1–4) and avatar (one of a fixed set of keys). Nothing else about the Child is stored.
- **Story**: slug (unique, taken from its file), title, Level (1–3), and estimated minutes. Ordered into **sentences**, each ordered into **words**.
- Each word keeps its displayed text, a normalised form used for matching (lower-case, punctuation stripped, all apostrophe variants of o'/g' unified) and its syllables.
- **Reading**: belongs to a Child and a Story. It records:
  - when it started and finished (an empty finish means unfinished)
  - the position of the next sentence to read, used for resuming
  - accumulated reading time (the length of sentence and retry recordings, excluding Syllable help)
  - once finished: Accuracy, Stars and Reading speed.
- A Child has at most one unfinished Reading per Story. Opening that Story resumes it.
- **Word result**: one per word per Reading. Outcome is one of: correct on the first try, correct on retry, or needed Syllable help. Recorded with the time it happened.
- Streak, Stars total, Hard words and the Parent screen figures are worked out from Readings and word results when needed. No separate counters are stored.

### Reading flow (server-driven)

- The Child reads one sentence at a time. The browser records until it hears silence, then sends the audio of that sentence to the server. The server:
  1. passes the audio, the sentence's expected words and the language "uz" to the speech-to-text interface
  2. discards the audio (ADR-0002)
  3. aligns the recognised words against the expected words
  4. returns a mark per word.
- **Alignment** is order-preserving: recognised words are matched to expected words in sequence, comparing normalised forms. Skipped and inserted words are tolerated. An expected word with no match is misread.
- **Retry**: every misread word in the sentence gets one retry, in order, recorded on its own and checked the same way. Correct on retry is recorded as "correct on retry". Misread again moves to Syllable help.
- **Syllable help**: shows the word's syllables, plays the saved audio for each syllable and then the whole word, and lets the Child read the word once more. That final read is not scored, and the word is recorded as "needed Syllable help" whatever the result. Then the next misread word or the next sentence follows.
- If speech-to-text returns nothing, or fails, the sentence (or word) is not scored and the Child is asked to read it again. Nothing is recorded for that attempt.
- **Finishing** the last sentence finishes the Reading and calculates:
  - **Accuracy**: words correct on the first try ÷ all words in the Story.
  - **Stars**: 3 at an Accuracy of 95% or more, 2 at 85% or more, otherwise 1.
  - **Reading speed**: words correct on the first try ÷ minutes of reading time.
- **Stars total** for a Child = the sum of their best Stars per Story.
- **Streak**: the number of consecutive Tashkent-time calendar days with at least one finished Reading. It counts back from today, or from yesterday if nothing has been finished today yet, and is zero otherwise.
- **Suggested Level** from Grade: Grade 1 → Level 1, Grade 2 → Level 2, Grades 3–4 → Level 3. The home screen suggests the first Story at that Level the Child hasn't finished, falling back to any unfinished Story.

### Child selection

- After login the Parent lands on the "Who is reading?" picker. Choosing a Child makes it the active Child for the session.
- Child screens require an active Child that belongs to the logged-in Parent. The Parent screen and settings need no PIN.

### Parent screen figures (per Child)

- **Days practised this week**: distinct Tashkent days, Monday–Sunday, with at least one finished Reading.
- **Stories finished this week.**
- **Reading speed**: the average Reading speed of finished Readings per week, for the current week and the three before it.
- **Hard words**: words with a "correct on retry" or "needed Syllable help" result in the last 7 days, grouped by normalised word and ranked by how often. Top 5.
- Also shown: Streak, Stars total, and a short notice that the voice is AI-generated and recordings are not kept.

### Speech-to-text interface (new seam)

- One app-level contract: given a short audio clip, the expected text and the language, return the recognised words in order, each with optional timing and confidence.
- The concrete driver is picked by configuration. Gemini 3.5 is preferred, with ElevenLabs Scribe or Azure Speech as fallbacks. A driver must report what the Child actually said and never correct toward the expected text. Whether the expected text is passed as context is decided by the experiment.
- A fake driver returns scripted words. It is used in tests, and can be used locally to demo without a real service.
- Drivers are called directly from the server for the current request (no queue), because the Child is waiting for the result.

### Text-to-speech interface and audio (new seam)

- One app-level contract: given text and speaking options (slow rate, pauses between parts), return audio. The driver is picked by configuration. Gemini Flash 3.8 TTS is preferred, steered by its prompt to read slowly with pauses. Azure Madina or Aisha Gulnoza are fallbacks if it garbles single syllables.
- Each Story word gets two saved clips: its syllables read slowly with pauses, and the whole word read slowly. Clips are saved to public storage under a stable name derived from the word's normalised form and syllables. Identical words across Stories share clips.
- The generation command creates only missing clips by default, and can regenerate the clips for one named word.

### Story files and loading (new seam: artisan commands)

- Each Story is one human-readable file in the repository. It holds a title, a Level and the text (sentences in paragraphs), plus an optional list of syllable overrides (a word and its hand-made split).
- The load command reads every Story file, splits text into sentences and words, and applies the syllable rule with overrides. It creates new Stories and updates changed ones, matched by slug.
- If a Story's words would change and Readings of it already exist, the command refuses with a clear message and leaves that Story untouched.
- The **syllable rule** treats o', g', sh, ch and ng as single letters, and the vowels as a, e, i, o, u and o'. Each syllable holds exactly one vowel. A single consonant between vowels starts the next syllable. When consonants cluster between vowels, the last one starts the next syllable. Word-initial and word-final clusters stay with their vowel. Overrides take precedence.

## Testing Decisions

- A good test drives the app from outside, exactly as a user or operator would: an HTTP request to a page or action, or an artisan command. It then checks what they would see: the response, the data handed to the page, and the stored state. Tests never call internal classes such as the aligner or syllable splitter directly, and never assert how a result was computed, only what it is.
- **Seam 1: HTTP feature tests (main seam).** These cover:
  - Parent sign-up and login with phone numbers, including normalisation and duplicates.
  - Child profile create, edit and delete, and access only to one's own Children.
  - The picker and active-Child rules.
  - Sentence submission and word marks, retry, Syllable help, unscored failures, and finishing.
  - Accuracy, Stars, Reading speed, Stars total and Streak, including Tashkent day boundaries via frozen time.
  - Resuming.
  - The Parent screen figures, and the home and library data.
- **Seam 2: the speech-to-text fake.** Bound in the container for every test, so a test can script what the Child "said" for each sentence or word. Alignment and normalisation cases are tested through sentence submission: apostrophe variants, punctuation, capitals, skipped and extra words.
- **Seam 3: artisan command tests.**
  - The Story load command, run against small fixture Story files. Tests check the stored sentences, words and syllables, that overrides apply, that it is safe to re-run, and that it refuses changes to Stories that already have Readings. Syllable-rule cases are tested here.
  - The audio generation command, with the text-to-speech fake. Tests check that missing clips are created, existing ones skipped, and single-word regeneration works.
- New tests use factories for Parents, Children, Stories and Readings, with states for finished Readings and word outcomes, and the `RefreshDatabase` trait.
- New tests use Pest syntax, per the project rules.
- Prior art: the starter kit's feature tests for authentication, settings and the dashboard. They show the request-then-assert style, factories and `RefreshDatabase` this spec follows.

## Out of Scope

- The Teacher side: class views, rosters, linking Children to schools, and teacher-to-Parent messages.
- Pricing, payments, subscriptions and free/paid limits.
- The Telegram weekly report.
- Badges, the daily reading goal and the advice box.
- Locked Stories and unlocking rules.
- A PIN or lock on the Parent screen.
- SMS or email verification, self-service password reset, two-factor login and passkeys.
- Live word-by-word highlighting while the Child reads (streaming speech-to-text).
- An admin screen for Stories.
- Storing children's audio, and any opt-in for it.
- A native or app-store app.
- Cyrillic script.
- Choosing the speech-to-text and voice services. That is the job of the separate speech-to-text and voice experiment.

## Further Notes

- **Prerequisite.** The speech-to-text and voice experiment must finish first:
  - About 20–30 children reading are recorded, with their parents' permission.
  - The preferred services are **`gemini-3.5-transcribe`** for transcription and **`gemini-3.8-flash-tts`** for the voice (the owner's choice, one Google account for both; the owner reports Google confirmed use in this children's app, as Gemini's terms otherwise forbid services aimed at under-18s). Paid tier only: the free tier may use content to improve Google's products. The experiment confirms they work on children's voices. Fallbacks are ElevenLabs Scribe or Azure Speech for listening, and Azure Madina or Aisha Gulnoza for the voice.
  - Gemini transcription is tested both with and without the sentence's text in its prompt. The key number is how often it lets a misread word pass as correct, because a language-model transcriber can "hear" the expected word.
  - Building the Reading flow beyond the fake drivers should wait for its result. Accounts, Child profiles, Story loading and the Parent screen can be built in parallel.
- **Content.** The 9 launch Stories (3 per Level) must be reviewed by a primary school teacher before release. Generated voice clips must be listened to before testers see them.
- **Hosting.** The app runs on the owner's own VPS through Coolify (Docker Compose, deploys triggered from GitHub Actions), the same way as the owner's ITPlaza CRM. Unlike ITPlaza, production uses SQLite on a persistent volume, since this is a test project. Coolify's proxy provides the HTTPS that browser recording needs. Test early on Android Chrome and iPhone Safari.
- **Terms** follow GLOSSARY.md: Parent, Child, Grade, Story, Level, Reading, Syllable help, Accuracy, Stars, Reading speed, Streak, Hard word.
- **Decisions** follow ADR-0001 (web app instead of native) and ADR-0002 (children's audio is not stored).
