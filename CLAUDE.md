# CLAUDE.md — Working Conventions for the arc-qb-sync Repo

This file is the cross-session coaching for arc-qb-sync. It loads at the start of every Cowork session and is the standing context for every Code session. Per-version Code prompts (`CLAUDE_CODE_PROMPT_v*.md`) live alongside this file and are the Cowork→Code handoff docs for specific ships — this file is the background everything else assumes.

For org-wide operational context, see `arc-ops/CLAUDE.md`. This file overrides or extends arc-ops only where the specifics of this repo differ.

---

## What This Repo Is

`arc-qb-sync` is a WordPress plugin that integrates Quickbase with **thearcoregon.org**. It provides shortcodes for training event detail pages, instructor CPT support, and a filterable public course catalog. The plugin is the older sibling of `summit-qb-sync` (which targets iddmhsummit.com) — many of summit's architectural patterns were first built here.

**Target site:** thearcoregon.org
**QB app:** The Arc Oregon training catalog (events, courses, instructors)
**Install path on server:** standard WP plugin directory
**Local repo path (primary build box):** `C:\Users\alytle\OneDrive - The Arc Oregon\GitHub\arc-qb-sync\`

The README is the shortcode/user-facing reference; CHANGELOG records every shipped version; per-version `CLAUDE_CODE_PROMPT_v*.md` files are the historical decision records for what Code actually did.

---

## How This Project Divides Between Cowork and Code

Most work in this repo is **Code mode** — the plugin ships as numbered versions, each one a discrete code change. Cowork handles the orbit around that.

**Cowork handles:**

- Writing and refining `CLAUDE_CODE_PROMPT_vX.Y.Z.md` for the next Code session
- Updating `docs/field-mapping-*.md` from fresh QB snapshots
- Updating the README shortcode tables when shortcodes are added or removed
- Reading QB snapshots (from `arc-ops/systems/applications/quickbase/.../snapshot-*.md`) and extracting FIDs into `docs/field-mapping-*.md`
- Repo hygiene (CHANGELOG cleanup, doc alignment, stale-branch triage)
- Session notes that capture a Cowork pass

**Code handles:**

- Writing PHP plugin files (`arc-qb-sync/includes/*.php`)
- Version bumps, CHANGELOG entries
- Branch creation, commits, push, PR open
- Running `./build.sh` if a packaged zip is needed

**The handoff is always sequential.** Cowork settles the design and produces a ready-for-code prompt; Code executes it. Never pass a `CLAUDE_CODE_PROMPT_*.md` to Code while open questions remain — fix them in Cowork first.

---

## Versioning Convention

Semantic versioning: `MAJOR.MINOR.PATCH`.

| Bump | When |
|---|---|
| **Patch** (3.5.**x**) | Bug fix or small correction in a shipped feature. Multiple patches in a day are fine. |
| **Minor** (3.**x**.0) | New feature — new field(s), new shortcode(s), new query hook, new file. Most `CLAUDE_CODE_PROMPT_*.md` files ship as a minor. |
| **Major** (**x**.0.0) | Breaking change — shortcode removal without alias, CPT slug change, API contract change. |

Version is set in two places in `arc-qb-sync/arc-qb-sync.php` — the plugin header `Version:` line and the `ARC_QB_SYNC_VERSION` constant. Both must match. CHANGELOG entry is required.

**Current version:** see `CHANGELOG.md` top entry.

---

## Repo Structure

```
arc-qb-sync/                       ← repo root
├── arc-qb-sync/                   ← the plugin folder (built into arc-qb-sync.zip)
│   ├── arc-qb-sync.php
│   ├── includes/
│   └── assets/
├── arc-training-details/          ← legacy reference assets (pre-plugin)
├── docs/                          ← field mappings, setup, QA reviews (never shipped)
├── sessions/                      ← Cowork + Code session notes (convention below)
├── CLAUDE.md                      ← this file
├── CLAUDE_CODE_PROMPT_v*.md       ← per-version handoff prompts (kept as decision record)
├── CHANGELOG.md
├── README.md                      ← public plugin reference
├── build.sh                       ← produces arc-qb-sync.zip for WP upload
└── LICENSE
```

**Never write plugin PHP to the repo root.** All PHP goes under `arc-qb-sync/`.

---

## Session Notes

arc-qb-sync did not originally maintain a `sessions/` folder — the CHANGELOG + per-version prompts carried the narrative. That is fine while every ship is a clean full-cycle Code session, but breaks down the moment:

- A Cowork session does meaningful work without producing a version ship (doc-only pass, spec refactor, prompt authoring).
- Multiple patch versions land in rapid sequence and the "why" of each is not obvious from the CHANGELOG alone.
- A Code session ends abnormally and the next session needs to know where it left off.

**Convention going forward:** every Cowork session that touches repo files, and every Code session that ships a version, drops a note into `sessions/`.

**Filename pattern:** `YYYY-MM-DD-HHMM_mode_short-topic.md`

Examples: `2026-04-21-1430_cowork_claude-md-bootstrap.md`, `2026-04-13-0900_code_v3.3.0.md`.

**Required front matter:**

```yaml
---
date: YYYY-MM-DD
start_time: "HH:MM"
end_time: "HH:MM"  # or "TBD" if written mid-session
mode: cowork | code
participant: Alan Lytle
# Code sessions:
version_shipped: X.Y.Z
orchestration_prompt: CLAUDE_CODE_PROMPT_vX.Y.Z.md
# Retroactive notes:
source: retroactive — assembled from [sources]
---
```

**Required body:**

- Goal (one line).
- What was actually done (files touched, functions written, decisions made).
- Branch name + commit SHA (Code sessions).
- Open questions or anything unresolved.
- Next-session handoff.

Session notes are working records, not polished deliverables. Write one even if the session ended abnormally — that's when they matter most.

**Retroactive notes are valid.** If a Code session shipped without a note, reconstruct the note from the CHANGELOG + the corresponding `CLAUDE_CODE_PROMPT_vX.Y.Z.md`, mark `source: retroactive` in the front matter, and file it under the date it actually shipped.

---

## Orchestration Prompt Format (`CLAUDE_CODE_PROMPT_vX.Y.Z.md`)

This is the format arc-qb-sync has refined and that summit-qb-sync's `_drafts/` prompts also now follow. It's what turns a fuzzy intent into a clean Code ship.

**Front matter** (add at top; older files without it are grandfathered):

```yaml
---
status: draft | in-development | ready-for-code | shipped
started: YYYY-MM-DD
owner: Alan Lytle
target_version: X.Y.Z
# After ship:
shipped: YYYY-MM-DD
shipped_version: X.Y.Z
---
```

**Required body sections, in order:**

1. **What You Are Doing and Why** — two-paragraph max. Name the change and the motivation. If a bug surfaced the work, name the bug.
2. **Prerequisites — confirm before starting** — bulleted list Code can verify in 30 seconds (current plugin version reads `X.Y.Z-1`; QB schema prereqs in place per snapshot-NNNN; etc.).
3. **Read These Files First** — numbered list of files Code must load before writing. Include `docs/field-mapping-*.md` when FIDs are in play.
4. **Target File Structure Changes** — fenced tree showing only files that will be touched, annotated with `← update: ...` / `← NEW: ...` / `← remove: ...`. Scope at a glance.
5. **Numbered Tasks** — one section per logical change. Each task names the file, shows the exact code to add/replace/remove, and explains any non-obvious decision inline (not in a footnote).
6. **Verification Checklist** — numbered post-ship checks Code runs against the finished code (version constant and header both read X.Y.Z; no references to removed fields remain in sync-*.php; docs updated; CHANGELOG entry present).
7. **What NOT to Change** — named exclusions. Stops Code from drifting into out-of-scope refactors.
8. **Manual tests** (optional) — small table of pre-/post-conditions to exercise live against the running site after merge. Include when the change is observable end-to-end.

**After ship:** don't delete the prompt. Flip its front-matter `status` to `shipped`, add `shipped:` and `shipped_version:`, and **move the file from the repo root to `_archive/code-prompts/`** as the decision record. Add a one-line italic header below the front matter: `_Shipped YYYY-MM-DD; paths and concepts in this document reflect the state at ship time. Refer to CLAUDE.md for current state._` This stops a fresh session from misreading a stale orchestration prompt as a current task list — the prompt body stays as it shipped (decision record), but the header makes the time-of-write framing explicit. The session note references the prompt by filename — bare filenames resolve fine since `_archive/code-prompts/` is the only place a shipped prompt lives.

Killed-without-shipping prompts also live in `_archive/code-prompts/` with `status: archived` and an `archived_reason:` line in the front matter. The prompt's `status` field distinguishes between shipped and archived prompts in the same folder.

In-flight prompts (`status: draft | in-development | ready-for-code`) live at the repo root so a fresh Code session sees them first. Only shipped or archived prompts move into `_archive/code-prompts/`.

Existing shipped `CLAUDE_CODE_PROMPT_v*.md` files at repo root from before this convention was adopted can be retro-migrated to `_archive/code-prompts/` in a one-time hygiene pass; not urgent.

---

## Ending a Session

### Cowork sessions

At the end of any Cowork session that made design decisions or touched repo files:

1. Add a session note to `sessions/` using the filename pattern above.
2. If a CLAUDE_CODE_PROMPT_vX.Y.Z.md was produced or updated, confirm its front-matter status is current (`ready-for-code` if it's ready to ship, `in-development` if it's not).
3. **Hand the commit to Alan.** Cowork does not run `git commit` / `git push` from the sandbox — see the Windows + OneDrive note below for why. Generate a copy-paste-ready commit message and display it at end-of-session. Alan commits and pushes via GitHub Desktop or terminal.
4. Cowork does **not** open PRs. The sandbox has no access to `gh` against Alan's GitHub auth. PR opening is a Code-mode step (or Alan does it in-browser).

**Commit-message format Cowork produces:**

- **Subject:** imperative, lowercase, under ~70 chars, scoped (e.g., `add CLAUDE.md; fix README merge conflict`).
- **Description:** 2–5 bullets covering what changed and why. Reference filenames when it helps. Mention the session-note filename. No marketing language.

No version bump for design-only Cowork sessions.

### Code sessions

**Tooling assumption (locked 2026-04-21):** GitHub CLI (`gh`) 2.90.0+ is installed on Alan's primary Windows build box and authenticated against `github.com/alytle-thearcoregon`. Claude Code should assume `gh` is available and call it directly. **Do not** probe for it (`which gh`, `gh --version`, `command -v gh`, trying to install it, asking Alan whether it's available). If `gh` ever fails with an auth or network error, surface the real error — don't silently hand the PR step back.

At the end of any Code session that changed plugin files, in this order:

1. Bump the version in `arc-qb-sync/arc-qb-sync.php` (both the header `Version:` and `ARC_QB_SYNC_VERSION` constant).
2. Add a dated entry to `CHANGELOG.md`.
3. Update `docs/field-mapping-*.md` if FIDs or meta keys changed.
4. Add a session note to `sessions/` — not optional. Include branch name and commit SHA.
5. Commit to the `claude/*` branch and push to `origin`.
6. Open the PR. Preferred: `gh pr create` directly. Acceptable fallback: Claude Code's `/create-pr` skill. Return the PR URL to Alan in the final chat message.
7. Flip the CLAUDE_CODE_PROMPT_vX.Y.Z.md front matter to `shipped`, add `shipped:` + `shipped_version:`, move the file from the repo root to `_archive/code-prompts/`, and add the one-line italic header below the front matter. See "Orchestration Prompt Format" → After ship for the exact pattern.
8. Alan reviews + merges in-browser, deletes the remote branch there, pulls `main` via GitHub Desktop, then runs the post-merge cleanup block (below) on his Windows box. This is a human step — Code does not merge.

**Canonical PR command (Code runs this directly — do not ask first):**

```powershell
gh pr create `
  --base main `
  --head "$(git branch --show-current)" `
  --title "<imperative, lowercase, ≤70 chars, scoped>" `
  --body-file "<path-to-prepared-body.md>"
```

On Unix the line continuations are `\` instead of backticks; `--body-file` behaves the same.

**PR title and description for this repo:**

- **Title:** imperative, lowercase, under ~70 chars, scoped (e.g., `v3.5.0: add _arc_event_schedule; remove instructor1/2/3 slots`).
- **Description:**
  - One-line summary.
  - "What changed" — bullets of `file → what`.
  - Any post-merge manual steps (e.g., "Re-save Permalinks if CPT routing changed").
  - Branch name and commit SHA(s).
  - Link to the shipped CLAUDE_CODE_PROMPT_vX.Y.Z.md.

**If a Code session must end before all of these steps are complete:** at minimum, write the session note with whatever was done, commit to `claude/*`, and push. A session that wrote code but left no commit trail is a bug, not a feature. If only the PR step didn't complete, say so explicitly and paste the exact `gh pr create` command Alan can run.

---

## Cowork mount sync caveats

Cowork sessions edit your mounted folder directly through a sync layer. Within a single session, that layer can lag behind your real-time edits — two observable symptoms:

1. **Stale mtime even when content has changed.** Claude reads a file mid-session, the file's mtime claims the same value it had earlier, but you actually saved an updated version. Claude's read may capture the new content on a later re-read while the mtime never moves.
2. **Edits not propagating at all within the session.** You re-save a file on your machine; Claude sees it unchanged in size, content, and mtime. The edit hasn't crossed the sync layer yet.

**The reliable signal: a fresh session always sees current state.** If you save (or commit) and then start a new Cowork session, the new session mounts fresh and reads everything correctly. The friction is purely *within-session*, not across sessions.

When Claude's read disagrees with what you report, default conclusion is "the sync is stale," not "the user is mistaken." Diagnostic pattern Claude should use:

1. State exactly what it's reading before drawing conclusions — "I see this file at N bytes, mtime X — does that match what you see?"
2. Triangulate beyond mtime — compute `sha256sum`, file size, and a content fingerprint (specific lines or section names). Mtime is unreliable across the mount; content sha is more honest.
3. Use `git status` as the synchronization point — git diffs against object content, so it's robust to the mtime weirdness. If your machine sees `M path/to/file` and Claude's sandbox sees no changes, the sync hasn't propagated.
4. When in doubt, the hard reset is: commit locally and start a new Cowork session. The new session's first read is correct every time.

---

## Closing a session — trigger rule

Claude never starts the session-closing sequence (session note, doc sweep, commit message) until Alan explicitly says to close the session — e.g. "wrap up," "close the session," "let's close," "ok go ahead," or similar phrasing. Claude may *suggest* closing when the work seems naturally complete, but must wait for the go-ahead before doing any closing steps.

A suggestion sounds like: "Looks like a good stopping point — want me to close the session?" — then stop. Don't draft a commit message preemptively; don't write the session note yet; don't run the doc sweep. The suggestion is the action; the close is Alan's call.

Enthusiasm, urgency, or added detail in Alan's message is not a close signal. The trigger phrase is.

---

## Pre-commit doc sweep

Before drafting any commit message, if the session renamed, retired, replaced, moved, or completed anything that other docs reference (a file, a folder, a status value, a convention name, a setting key, a function name, an open item that's now resolved), grep the touched repo(s) for the old name or stale claim and confirm every downstream reference is updated or annotated as legacy/historical with a date.

This is the systematic fix for the "stale doc read as current" failure mode: a session ships work that changes reality, but the doc that the next window opens still describes the old reality. Subsequent windows build against the wrong picture. The doc sweep catches this at the producing session, not the consuming one.

Grep targets vary by repo, but common ones: `CLAUDE.md`, `README.md`, sprint orchestrator or status docs, architecture or spec docs, session-folder docs, and any README inside an `_archive/` folder. If a stale claim can't be updated cleanly in the moment, annotate it as legacy with a date rather than leaving it as a true-looking false claim.

---

## Closing-message formatting (applies to every Cowork session)

The end-of-session message is the handoff. Format it for fast scanning and clean copy-paste — Alan reads it once and acts.

**Repo callouts in prose.** Mark up the repo that was *touched* in backticks. Untouched repos referenced as context get no backticks. The backtick formatting is the visual signal of "this is where the work lives."

Right: `` `event-management` repo. (arc-event-reg had no changes in this session.) ``
Wrong: `` event-management repo only — `arc-event-reg` had no changes. ``

When multiple repos were touched, mark up each one. When only one was touched and others are referenced for context only, only the touched one gets backticks.

**Commit messages in separate code blocks.** When producing a commit message, put the subject and the description in two separate fenced code blocks, each independently copy-pasteable. Do not combine them into a single block. Label them `Subject:` and `Description:` above each block.

This applies whether the commit message is for one repo or several. One subject + description pair per repo, each pair in two blocks.

**One commit per repo touched.** Per the Confirmed working pattern (separate commits per repo), if a Cowork session touched two repos, produce two subject blocks and two description blocks, clearly grouped under the relevant repo callout.

---

## Windows + OneDrive — stale locks and `.git/config` corruption

This repo lives at `C:\Users\alytle\OneDrive - The Arc Oregon\GitHub\arc-qb-sync\`. The OneDrive + GitHub Desktop + Cowork sandbox combination has a known failure mode where file handles on `.git/` cause the sandbox to leave behind lock files it can't clean up — and in rare cases, partial writes leave `.git/config` padded with null bytes.

Symptoms:

- `fatal: bad config line N in file .git/config`
- `Unable to create ... packed-refs.lock: File exists`
- Interactive "Should I try again? (y/n)" loops on `git worktree prune`
- GitHub Desktop reports *"A lock file already exists in the repository, which blocks this operation from completing"*
- `git status` in the Cowork sandbox reports `Operation not permitted` on its own files

**Before any Code session or PowerShell cleanup:** pause OneDrive sync (taskbar icon → Pause syncing → pick a duration longer than the expected session) and close GitHub Desktop. Both hold file handles on `.git/` and will re-lock files the moment they're cleared.

**From PowerShell, in the repo root:**

```powershell
cd "$env:USERPROFILE\OneDrive - The Arc Oregon\GitHub\arc-qb-sync"

# Clear stale lock files (safe; no-op if absent)
Remove-Item -Force -ErrorAction SilentlyContinue .git\index.lock
Remove-Item -Force -ErrorAction SilentlyContinue .git\packed-refs.lock
Remove-Item -Recurse -Force -ErrorAction SilentlyContinue .git\refs\heads\claude

# Clear stale worktree admin dirs (if `git worktree prune` goes interactive)
Remove-Item -Recurse -Force -ErrorAction SilentlyContinue .git\worktrees\*

# Normal cleanup
git fetch --prune
git worktree prune
git branch --merged main | Select-String '^\s*\+?\s*claude/' | ForEach-Object { git branch -D ($_ -replace '^\s*\+?\s*','').Trim() }
```

**If `git` reports `fatal: bad config line N in file .git/config`,** the config was partially overwritten. The safe canonical content for this repo is:

```
[core]
	repositoryformatversion = 0
	filemode = false
	bare = false
	logallrefupdates = true
	symlinks = false
	ignorecase = true
[submodule]
	active = .
[remote "origin"]
	url = https://github.com/alytle-thearcoregon/arc-qb-sync.git
	fetch = +refs/heads/*:refs/remotes/origin/*
[branch "main"]
	remote = origin
	merge = refs/heads/main
```

Rewrite `.git/config` to exactly this content, resume OneDrive, and run `git status` to confirm.

After cleanup finishes, re-enable OneDrive sync and reopen GitHub Desktop.

---

## Post-merge cleanup

Isolated worktrees leave behind local `claude/*` branches and `.claude/worktrees/*` admin entries even after the remote branch is merged and deleted. Without cleanup, these accumulate across sessions. **The leftover `.claude/worktrees/busy-cannon/` currently in the repo is exactly this — a Code worktree from an older merge that never got cleaned up.**

Run this in the repo root after pulling `main`:

```powershell
git fetch --prune
git worktree prune
git branch --merged main | Select-String '^\s*\+?\s*claude/' | ForEach-Object { git branch -D ($_ -replace '^\s*\+?\s*','').Trim() }
# Also clear the worktree staging folder if it has leftovers:
Remove-Item -Recurse -Force -ErrorAction SilentlyContinue .claude\worktrees\*
```

`-D` (force delete) is required because git still sees the branches as checked out in the now-missing worktrees. The `--merged main` filter keeps this safe — only branches already merged to `main` are deleted.

If stale `claude/*` branches or worktrees are spotted at the start of a Cowork session (i.e., cleanup was missed after a previous merge), flag them and run the cleanup before building on top of the repo.

---

## Notes for Every Session

- Read the relevant `CLAUDE_CODE_PROMPT_vX.Y.Z.md` in full before starting a Code session.
- FIDs always come from the latest QB snapshot in `arc-ops/` — never invent or guess them.
- Function naming convention: `arc_qb_*` throughout.
- Meta key prefix: `_arc_` (Events use `_arc_event_*`, Courses `_arc_course_*`, Instructors `_arc_instructor_*`).
- All DB writes: `sanitize_text_field()` for plain text, `wp_kses_post()` for HTML. Do not apply `wpautop()` to content that arrives from QB pre-formatted (Bio, Trainer Role(s), Event Description — all have embedded HTML from QB).
- Never write to the `summit-qb-sync` repo from this session.
- At the end of each session, update or create the session note in `sessions/`.

---

*Last updated: 2026-04-21 (initial authoring — cross-repo coaching unification with summit-qb-sync).*
