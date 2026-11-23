# 17: Aisha drivers use the VoiceLab developer API

**What to build:** The `aisha` listening and voice drivers from ticket 15 call the older Aisha API (`https://back.aisha.group`, header `X-Api-Key`). The owner's key was created in the VoiceLab dashboard (voicelab.uz → developer → API keys). It's a scoped developer key in the documented `vlk_…` format, and `back.aisha.group` rejects it: `403 {"error_key":"invalid_api_key_format"}`. VoiceLab's developer docs say these keys are sent as `Authorization: Bearer vlk_<secret>` to the developer API at `api.voicelab.uz`. Move both drivers to the VoiceLab developer API so the owner's key works. Keep the driver names (`aisha`), the `.env` switch and everything ticket 15 guarantees.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] Read the VoiceLab developer docs before coding, and follow them over memory and over ticket 15's Aisha details:
  - https://docs.voicelab.uz/api/authentication
  - https://docs.voicelab.uz/api/ (the HTTP reference)
  - the speech-to-text and text-to-speech pages (e.g. https://docs.voicelab.uz/api/stt and the TTS equivalent)
  - https://docs.voicelab.uz/api/errors
  - https://docs.voicelab.uz/libraries/sdk

  Confirm the base URL, paths, request fields (language, voice/model, mood, speed), accepted audio formats (WAV must be accepted, since the browser now records WAV), response shapes, and whether results arrive synchronously or need polling.
- [ ] Both drivers send `Authorization: Bearer <AISHA_API_KEY>` to the VoiceLab developer API. The key setting keeps its name, `AISHA_API_KEY`. The base URL is a setting (e.g. `AISHA_BASE_URL`, default the documented developer base) so the owner can switch back if Aisha confirms the old API still works for some keys.
- [ ] Listening returns the recognised words in order, never sends the Story text, and maps "nothing heard" to an empty list.
- [ ] The voice keeps ticket 14's one clip per word: syllables with pauses, then the whole word, slow pace, plus the configurable voice and mood. Use the developer API's own controls for pauses and speed if it has them; otherwise use punctuation as in ticket 15.
- [ ] Errors follow the documented error codes. Authentication and permission errors (e.g. invalid key format, missing scope) are logged with a clear message naming the permission needed (Speech to text / Text to speech). They surface as `SpeechToTextFailed` / `TextToSpeechFailed`. Rate limits and insufficient balance surface as `TextToSpeechRateLimited` for the voice, so ticket 14's backoff applies. A `Retry-After` header is honoured when present.
- [ ] `docs/deployment.md` explains which key type is needed (VoiceLab developer key, `vlk_…`) and which permissions it needs (Speech to text and Text to speech, level Write or Access). `.env.example`, `.env.production.example` and the compose file gain any new setting with a real default.
- [ ] Tests (Pest, `Http::fake()`, `Http::preventStrayRequests()`) cover the new request shapes, the Bearer header, the response mapping, polling if any, and the error paths. No test calls the real service.
- [ ] Anything in VoiceLab's docs that's missing, unclear or contradictory is listed in the report.
