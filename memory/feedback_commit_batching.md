---
name: Batch commits
description: User prefers batching all fixes into a single commit to minimize approval prompts
type: feedback
---

Don't create 30 individual commits; batch all fixes into a single commit at the end.

**Why:** Too many individual commits require too many approval prompts and clutter git history.

**How to apply:** For forensic review rounds and similar multi-fix tasks, apply all edits first, then create one commit with a detailed message listing all findings by severity.
