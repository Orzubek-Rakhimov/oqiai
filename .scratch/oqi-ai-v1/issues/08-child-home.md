# 08: Child home: greeting, Streak and suggested Story

**What to build:** The Child's home screen greets them by name and shows their Streak, Stars total, a suggested Story and a "continue" card for an unfinished Reading. See spec § Reading flow (Streak, Suggested Level).

**Blocked by:** 05

**Status:** done

- [x] The greeting uses the Child's name and avatar.
- [x] Streak = consecutive Tashkent calendar days with at least one finished Reading, counting back from today (or from yesterday if nothing is finished today yet); otherwise 0.
- [x] Suggested Level from Grade: 1 → 1, 2 → 2, 3–4 → 3. The suggestion is the first unfinished Story at that Level, falling back to any unfinished Story.
- [x] An unfinished Reading shows as a "continue" card with how far the Child got.
- [x] Stars total is shown. The home screen links to the library and back to the picker.
- [x] HTTP feature tests cover the Streak with frozen time across Tashkent midnight, gaps and today-versus-yesterday, plus the suggestion fallback and the continue card.
