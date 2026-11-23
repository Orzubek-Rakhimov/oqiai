# 10: Microphone check during setup

**What to build:** While setting up the first Child, the app asks for microphone permission and has the Child read "Salom! Mening ismim <name>." through the speech-to-text interface, to confirm the tutor can hear them. See spec user stories 16–19.

**Blocked by:** 02, 04

**Status:** done

- [x] After the first Child is created, the microphone check step appears.
- [x] The test sentence uses the Child's name and is checked through the speech-to-text interface. Any recognised words count as a pass.
- [x] Blocked permission and "nothing heard" each show clear Uzbek help on what to do next.
- [x] The step can be skipped, and the Parent can do it later from the Child's profile.
- [x] Nothing from the check is stored except whether it passed. The audio is discarded.
- [x] HTTP feature tests with the fake driver cover pass, nothing heard and skip.
