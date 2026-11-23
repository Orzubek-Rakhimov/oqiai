# 02: Child profiles and the "Who is reading?" picker

**What to build:** Right after sign-up, the Parent is asked to create their first Child (name, Grade 1–4, an avatar from a fixed set). They can add more Children and edit or delete them. After login the app opens on the "Who is reading?" picker. Choosing a Child sets the active Child for the session and opens a basic Child home. A Parent button opens the Parent area without a PIN. See spec § Child selection, § Domain model.

**Blocked by:** 01

**Status:** done

- [x] A new Parent is taken to "create your first Child" after sign-up.
- [x] A Child has only name, Grade (1–4) and avatar (one of the fixed keys). Validation errors are shown in Uzbek.
- [x] The Parent can add, edit and delete Children.
- [x] After login the Parent lands on the picker, which shows each Child's avatar and name plus a Parent button.
- [x] Choosing a Child sets the active Child and opens a basic Child home with a way back to the picker.
- [x] Child screens refuse access without an active Child that belongs to the logged-in Parent.
- [x] A Parent can never see, edit, delete or choose another Parent's Child.
- [x] Factories exist for Child (and the Parent account). HTTP feature tests cover all of the above.
