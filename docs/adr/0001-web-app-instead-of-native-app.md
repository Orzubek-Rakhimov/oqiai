# Build v1 as a phone-first web app, not a native app

The prototype is a phone app (390×844 screens), and an Expo project was started for it. v1 is instead a Laravel + Inertia + React web app laid out for phone screens. v1 exists to test with 10–20 families and to show the product to people. A web link reaches any phone with no app store, TestFlight or Expo Go. One codebase also removes the separate API layer. Browsers on Android Chrome and iOS Safari can record from the microphone, which is the only native-feeling capability the product needs.

## Consequences

- Audio recording is somewhat less dependable than in a native app. If the speech-to-text test or real testers show browser recording is the problem, revisit this decision for v2.
- A store app later would mean rewriting the screens (React knowledge carries over to React Native; the components do not).
