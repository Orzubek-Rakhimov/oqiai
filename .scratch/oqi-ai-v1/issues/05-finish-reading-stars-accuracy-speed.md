# 05: Finish a Reading: Stars, Accuracy and Reading speed

**What to build:** When the Child reads the last sentence, the Reading is finished and the result screen shows its Stars (1–3), Accuracy, Reading speed and the words that needed help. The library shows the Child's best Stars per Story, and the Stars total counts only the best Stars per Story. See spec § Reading flow (Finishing).

**Blocked by:** 04

**Status:** done

- [x] Finishing the last sentence stores the finish time, Accuracy, Stars and Reading speed.
- [x] Accuracy = words correct on the first try ÷ all words in the Story.
- [x] Stars: 3 at 95%+ Accuracy, 2 at 85%+, otherwise 1.
- [x] Reading speed = words correct on the first try ÷ minutes of reading time.
- [x] The result screen shows Stars, Accuracy, Reading speed and the words not correct on the first try, with "next Story" and "back to home" buttons.
- [x] The library shows best Stars per Story. A re-read can improve the best but never lowers it.
- [x] Stars total = sum of best Stars per Story. Unfinished Readings give no Stars.
- [x] HTTP feature tests cover the thresholds (including exactly 95% and 85%), best-per-Story, re-reads and unfinished Readings.
