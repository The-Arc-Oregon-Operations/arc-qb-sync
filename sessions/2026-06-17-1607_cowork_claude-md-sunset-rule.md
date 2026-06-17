---
date: 2026-06-17
start_time: "~16:07"
end_time: "16:07"
mode: cowork
participant: Alan Lytle
---

# Cowork — add Transitional Status / Sunset standing rule to CLAUDE.md

## Goal

Land the feature-freeze / sunset standing rule for arc-qb-sync. This was decided earlier on 2026-06-17 in the event-management doc-structure session, but arc-qb-sync was not mounted at that time, so the rule was held as a pending action recorded in `event-management/ROADMAP.md`. Alan mounted the repo and asked to catch the docs up.

## What was done

- Added a prominent new section `## Transitional Status — Feature Freeze and Sunset — Standing Rule` to `CLAUDE.md`, immediately after "What This Repo Is":
  - arc-qb-sync is transitional; end-state is sunset, folding into arc-event-reg on Alan's timing (Claude does not initiate).
  - No new features; tweaks only, where needed to keep transitional events rendering correctly; surface new-feature needs as arc-event-reg candidates.
  - Carries a `> Confirmed (2026-06-17 · Alan)` block.
  - Points to the canonical homes in event-management (OVERHAUL "Direction of travel"; ROADMAP "Upcoming milestone").
- Resolved the matching pending-action note in `event-management/ROADMAP.md` (now records the rule as landed 2026-06-17).

No code or version change. Design-only Cowork touch.

## Open questions / handoff

- `README.md` (user-facing shortcode reference) does not yet note transitional status. Left as-is; optional one-line note if Alan wants the user-facing doc to reflect the sunset. Not done unprompted.
- Full framing and dated Confirmed blocks live in event-management (OVERHAUL + ROADMAP).
