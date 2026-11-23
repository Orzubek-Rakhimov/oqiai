# 09: Parent screen

**What to build:** For each Child, the Parent sees this week's practice and how the Child is improving: days practised, Stories finished, Reading speed for this week and the three before, top 5 Hard words, Streak and Stars total. See spec § Parent screen figures.

**Blocked by:** 05, 06

**Status:** ready-for-agent

- [ ] Days practised this week = distinct Tashkent days (Monday–Sunday) with at least one finished Reading.
- [ ] Stories finished this week.
- [ ] Reading speed = average of finished Readings per week, for this week and the three before, shown as a trend.
- [ ] Hard words = words with a "correct on retry" or "needed Syllable help" result in the last 7 days, grouped by normalised word, top 5 by count.
- [ ] Streak and Stars total match the Child home.
- [ ] Empty state when the Child hasn't read yet. Switching between Children.
- [ ] A notice that the voice is AI-generated and recordings are not kept.
- [ ] Only the Parent's own Children are shown.
- [ ] HTTP feature tests with factories and frozen time cover each figure, including week boundaries and the 7-day window.
