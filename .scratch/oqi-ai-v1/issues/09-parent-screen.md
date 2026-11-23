# 09: Parent screen

**What to build:** For each Child, the Parent sees this week's practice and how the Child is improving: days practised, Stories finished, Reading speed for this week and the three before, top 5 Hard words, Streak and Stars total. See spec § Parent screen figures.

**Blocked by:** 05, 06

**Status:** done

- [x] Days practised this week = distinct Tashkent days (Monday–Sunday) with at least one finished Reading.
- [x] Stories finished this week.
- [x] Reading speed = average of finished Readings per week, for this week and the three before, shown as a trend.
- [x] Hard words = words with a "correct on retry" or "needed Syllable help" result in the last 7 days, grouped by normalised word, top 5 by count.
- [x] Streak and Stars total match the Child home.
- [x] Empty state when the Child hasn't read yet. Switching between Children.
- [x] A notice that the voice is AI-generated and recordings are not kept.
- [x] Only the Parent's own Children are shown.
- [x] HTTP feature tests with factories and frozen time cover each figure, including week boundaries and the 7-day window.
