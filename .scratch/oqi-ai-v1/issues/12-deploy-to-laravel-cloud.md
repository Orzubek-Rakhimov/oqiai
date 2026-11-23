# 12: Deploy to Laravel Cloud

**What to build:** The app runs on Laravel Cloud at an HTTPS link that opens on phones, so the browser microphone can be tested early on Android Chrome and iPhone Safari. Later tickets ship to the same environment.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] A Laravel Cloud application and environment exist with a database, and migrations run on deploy.
- [ ] The app is reachable over HTTPS. A Parent can sign up and log in from a phone.
- [ ] Secrets (speech and voice service keys, when they exist) live in the environment's settings, never in the repository.
- [ ] Saved voice clips are served from storage that survives deploys.
- [ ] A short note in docs/agents (or the README) says how to deploy.
