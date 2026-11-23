# 16: Test suite back to fast

**What to build:** Find and remove whatever makes the test suite slow. After ticket 14 merged, the full suite (326 tests) takes about 178 s. Before ticket 14, 309 tests took about 15 s. In a JUnit profile, nearly every slow test takes a multiple of about 2.1 s. Even "the login screen is rendered", a single GET, takes 2.14 s. That points to a fixed delay of about 2 s per HTTP request in tests, such as a timeout, a sleep, a lock wait, a DNS lookup, or a deferred callback that blocks. It could be in app code, in test setup, or in the environment. Drive C: is nearly full, which may matter for temp files.

**Blocked by:** None (can start immediately).

**Status:** done

- [x] The root cause is found and explained in the report, with evidence (e.g. timing before and after one change).
- [x] The full suite runs in roughly the time it did before ticket 14 (well under a minute on this machine). Every test still passes, and none is deleted or skipped.
- [x] If the cause is in app code (e.g. ticket 14's after-response clip work, cache locks or retries), the fix keeps production behaviour as ticket 14 specifies. If it's test-only, the fix lives in test setup.
- [x] If the cause is the machine (e.g. a full C: drive), the report says so plainly with evidence, and any project-side mitigation (such as a temp path setting for tests) is documented.
