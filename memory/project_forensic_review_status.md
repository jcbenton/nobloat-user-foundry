---
name: Forensic review status
description: Tracking forensic code review rounds — 5a/5b/5c complete (65 total fixes in round 5)
type: project
---

7 rounds of forensic code review completed on NoBloat User Foundry plugin (v1.6.0).

- Rounds 1-4: Split agents by domain silo (auth/2FA, shortcodes/router, data/options, admin/profiles, restrictions/TOS)
- Round 5a (2026-04-12): 6 agents by function (input/output, token lifecycle, auth/2FA, bulk I/O, access control, logging). 25 fixes. Commit 105e97a.
- Round 5b (2026-04-12): 6 agents by trust boundary (unauth→system, user→own data, admin→users, admin→config, system→filesystem, system→admin display). 19 fixes. Commit 4feb0f5.
- Round 5c (2026-04-12): 6 agents by analytical lens (error handling, nonce/CSRF, type safety, race conditions, output escaping, WP API misuse) with alphabetical round-robin file assignment. 21 fixes. Commit a964c0e.

**Key round 5 highlights:**
- HIGH: Multi-role priv esc (editable_roles), backup code reuse via parallel requests (FOR UPDATE transaction)
- Functionality: Missing nonce field broke admin profile saves, password change token not passed to render
- Race conditions: Magic link TOCTOU, digest send-then-delete, 2FA device trust rotation order
- Type safety: Multiple (int) cast gaps causing infinite loops, mass warnings, TypeErrors

**How to apply:** Codebase is well-hardened after 3 shuffles. Diminishing returns expected. If more rounds desired, try: data flow tracing (follow one field from input to DB to output), or negative testing (what if every option is null/empty/corrupt).
