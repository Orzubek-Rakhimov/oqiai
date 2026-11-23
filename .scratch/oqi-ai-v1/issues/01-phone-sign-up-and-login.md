# 01: Parent signs up and logs in with a phone number

**What to build:** A Parent can create an account with name, phone number and password, then log in with phone number and password. There's no SMS or email step. Sign-up, login and settings screens are in Uzbek (Latin) and use the prototype's look. This ticket also sets up the shared groundwork every later ticket uses: theme tokens (cream ground, plum ink, terracotta accent; Nunito headings, Inter body), the Uzbek copy, and phone-first layouts. See spec § Platform and accounts.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] Sign-up takes name, phone number and password. The Parent is logged in afterwards.
- [ ] Phone numbers typed with or without +998, spaces or dashes are stored as +998 followed by 9 digits.
- [ ] A phone number that is already registered is rejected with a clear Uzbek message.
- [ ] Login works with the phone number in any of those input forms, plus the password.
- [ ] Email verification, password reset, two-factor login and passkeys are turned off, and their screens and routes are gone.
- [ ] Profile settings edit name and phone number (with the same normalisation and uniqueness). The password change still works.
- [ ] Auth and settings screens are in Uzbek and use the theme tokens. Layouts work at phone width.
- [ ] HTTP feature tests (Pest) cover sign-up, normalisation, duplicates, login, and the disabled features. Starter-kit tests for removed features are updated or removed, with the change explained in the PR.
