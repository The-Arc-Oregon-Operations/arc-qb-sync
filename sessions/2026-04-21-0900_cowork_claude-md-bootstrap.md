---
date: 2026-04-21
start_time: "09:00"
end_time: "12:50"
mode: cowork
participant: Alan Lytle
source: cross-repo coaching unification pass with summit-qb-sync — arc-qb-sync was the "better at Cowork↔Code transitions" side per Alan's observation, but was missing all cross-session coaching infrastructure
---

# Goal

Bootstrap arc-qb-sync with the cross-session coaching infrastructure it was missing — a top-level `CLAUDE.md`, a `sessions/` folder, and a resolved README — so that Cowork and Code sessions against this repo have the same baseline discipline as sister repo summit-qb-sync. Fold in Alan's lock-in that `gh` CLI 2.90.0 is installed on the primary Windows build box and should be assumed-available for every Code session.

# Context — what Alan observed

Alan noticed that Claude handles Cowork↔Code transitions more cleanly in arc-qb-sync than in summit-qb-sync, and asked for a cross-populate pass to bring both repos to a unified best-practice. Main pain point: Claude has been spending time searching for `gh` CLI, hitting a wall, and handing PR creation back to Alan. Alan installed GitHub CLI 2.90.0 on the Windows build box during this pass.

# What arc-qb-sync was doing well (and what we kept)

- **Per-version orchestration prompts** (`CLAUDE_CODE_PROMPT_vX.Y.Z.md`) are tight, structured, and self-contained. Each one has: *What You Are Doing and Why → Prerequisites → Read These Files First → Target File Structure Changes → Numbered Tasks → Verification Checklist → What NOT to Change.* They're version-archived (kept as decision records) rather than deleted after ship.
- **Low drift surface.** CHANGELOG + archived prompts are the whole record. No sprawling Current State checklist to keep in sync.
- **Prescriptive, not deliberative.** Prompts give Code exact file → exact code → exact text, so Code doesn't re-decide design mid-session.

# What arc-qb-sync was missing (and what we added)

- **No cross-session coaching file.** Every Cowork or Code session started cold — no standing context about versioning, end-of-session discipline, OneDrive/lock recovery, post-merge cleanup, or gh workflow. Fixed by creating `CLAUDE.md` at repo root.
- **No sessions/ convention.** Doc-only Cowork passes (like today's) had nowhere to live; multi-patch days like summit-qb-sync's v1.8.1–1.8.5 cycle would have vanished into the CHANGELOG with no narrative. Fixed by creating `sessions/` and writing this file as the first entry.
- **No Windows + OneDrive + stale-lock recovery recipe.** Same failure mode summit-qb-sync has documented; same machine. Backported into CLAUDE.md with the repo-specific path and canonical `.git/config` content.
- **No post-merge cleanup recipe.** The leftover `.claude/worktrees/busy-cannon/` in the repo root is literally a Code worktree from an earlier merge that never got cleaned up — direct symptom. Recipe is in CLAUDE.md; the actual cleanup is still owed.
- **Unresolved git merge conflict in README.md.** `<<<<<<< Updated upstream / ======= / >>>>>>> Stashed changes` markers were still in the file. Resolved by keeping the current plugin shortcode reference (upstream side) and discarding the stashed generic-prose version. Also added three shortcodes that were missing from the table: `[event_schedule]` (v3.5.0), `[course_offers_online]` and `[course_offers_inperson]` (v3.3.0).

# Files Changed

### `CLAUDE.md` (NEW)

Full cross-session coaching file covering: what this repo is, Cowork/Code split, versioning convention, repo structure, session notes convention, orchestration prompt format, end-of-session discipline (Cowork + Code), `gh` tooling assumption + canonical PR command, Windows + OneDrive stale-lock recovery, post-merge cleanup (flags the busy-cannon leftover), notes for every session.

### `README.md`

- Removed git merge conflict markers. Kept the current plugin shortcode reference; discarded the generic reference-implementation prose version.
- Added `[event_schedule]` to the Events shortcode table (shipped in v3.5.0).
- Added `[course_offers_online]` and `[course_offers_inperson]` to the Courses shortcode table (shipped in v3.3.0).
- Added "Working in This Repo" section pointing to `CLAUDE.md` and the per-version prompts.

### `sessions/` (NEW)

Folder created; this file is the first entry.

# gh CLI (2.90.0) — locked assumption

Installed today on Alan's primary Windows build box and authenticated against `github.com/alytle-thearcoregon`. CLAUDE.md instructs Code sessions to call `gh pr create` directly without probing for it. The canonical PowerShell PR command is in CLAUDE.md's "Ending a Session — Code sessions" block.

# Related — summit-qb-sync changes in the same pass

The same unification pass made two edits to `summit-qb-sync/summit-qb-sync-orchestrator.md`:

1. **"Ending a Session — Code sessions" block** rewritten to lock in the gh-available assumption, specify the exact PR command, and order the end-of-session steps explicitly (including the non-optional session note, even for small patches).
2. **New "Orchestration Prompt Format" section** codifying the arc-qb-sync-style structure (front matter + eight body sections) that summit's `_drafts/` prompts should follow going forward. Backports arc's strength.

# Open Questions / Flags

- **Leftover `.claude/worktrees/busy-cannon/`** in this repo's root. Needs manual cleanup from Alan's Windows PowerShell — not something Cowork can do. Recipe is in CLAUDE.md under "Post-merge cleanup."
- **Older `CLAUDE_CODE_PROMPT_v*.md` files don't have the front-matter** the new format specifies. Grandfathered — no backfill needed unless a future session touches one for another reason.
- **sessions/ folder convention** is newly introduced here. Retroactive notes for recent Code ships (v3.5.0, v3.3.0, etc.) could be reconstructed from CHANGELOG + prompt files on a future Cowork pass if the narrative proves useful, but is not in scope today.

# Next Session Handoff

If the next session against this repo is a Code ship, use the current `CLAUDE_CODE_PROMPT_vX.Y.Z.md` format Alan has already refined; optionally add the new front matter. End-of-session steps now live in `CLAUDE.md` — the per-version prompt does not need to re-specify them.

If the next session is a Cowork pass on a new prompt, write it into a new `CLAUDE_CODE_PROMPT_vX.Y.Z.md` at repo root using the eight-section format in `CLAUDE.md`.

# Commit message (copy/paste)

```
add CLAUDE.md; fix README merge conflict; bootstrap sessions/

CLAUDE.md (new): cross-session coaching file — Cowork/Code split,
versioning, session notes convention, orchestration prompt format,
end-of-session discipline for both modes, gh CLI 2.90.0 tooling
assumption + canonical PR command, Windows + OneDrive stale-lock
recovery, post-merge cleanup (flags the leftover .claude/worktrees/
busy-cannon/ for manual cleanup).

README.md: resolved <<<<<<<<=======>>>>>>>> merge conflict. Kept the
current plugin shortcode reference; discarded the generic
reference-implementation prose version. Added [event_schedule]
(v3.5.0) and [course_offers_online] / [course_offers_inperson]
(v3.3.0) that were missing from the shortcode tables. Added pointer
to CLAUDE.md for working conventions.

sessions/ (new): folder + first entry
sessions/2026-04-21-0900_cowork_claude-md-bootstrap.md.

Cross-repo: companion commit in summit-qb-sync updates the
orchestrator's end-of-session block to lock in the gh assumption
and backports arc-qb-sync's per-version prompt structure as a new
"Orchestration Prompt Format" section for _drafts/ files.
```
