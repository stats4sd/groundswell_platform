# Hotfix PRs since `fix-issues-20261019` (for merging into `dev`)

**Date**: 2026-11-25
**Purpose**: List of main repo PRs #68–#85 (excluding #69 and #70, which are GitHub issues) and the `filament-odk-link` submodule changes they carry, so the hotfixes can be merged into the restructured `dev` branch.
**Method**: Reconstructed from git history only (`gh` is not installed). PR numbers and branch names come from merge commits; submodule PR numbers come from the submodule's merge commits; submodule bumps come from gitlink diffs in each main repo merge commit.

## Table

| Main repo PR | Main repo branch | Submodule PR | Submodule branch | Short description |
|---|---|---|---|---|
| #68 | `fix-issues-20261019` | – | – | Fix `crop_list` showing crop options twice; both lists keep the same options via a sync helper on create/update/delete. |
| #71 | `fix-issue-20261026` | #155 (closed, not merged) | `fix-issue-20261026` | Fix `crop_list` labels, choice order and module localisation lookup; team choices ordered after global ones; imported farms listed in file order; fix Replace XLSForm list button; one-off staging repair command (R1, R2). Submodule `e048b4b` → `80b95d2`. |
| #72 | `instance-name-hotfix-20261027` | – (commits pushed to the staging-hotfix line) | `staging-hotfix-20261026` | ODK form `instance_name` and `default_language` fix; `odk:backfill-template-settings` command and migration; vite ignores `vendor/`. Submodule `80b95d2` → `f9fc720`. |
| #73 | `remove-choice-list-entries-hotfix-20261029` | – (commit pushed to the staging-hotfix line) | `staging-hotfix-20261026` | Leave removed choice list entries out of generated forms and flag forms for republish. Submodule `f9fc720` → `ea6885f`. |
| #74 | `enhance-entity-label-hotfix-20261102` | – | – | Optional farmer name in farm entity labels ("ID: x / Name: y"). Main repo only. |
| #75 | `hotfix-replace-xlsform` | #162 | `hotfix-replace-xlsform` | Replace XLSForm keeps team-owned module versions and attaches new modules to existing team forms; R3 repair for emptied team module versions. Submodule `ea6885f` → `253ebc5`. |
| #76 | `docs-record-staging-deployment` | – | – | Docs only: staging deployment, R3 results, merged PRs. |
| #77 | `fix-replace-sync-deleted-team` | #163 | `fix-sync-deleted-team-owner` | Fix Replace XLSForm sync for forms of deleted teams. Submodule `253ebc5` → `80df415`. |
| #78 | `fix-replace-xlsform-translations` | #164 | `keep-choice-entry-ids-hotfix-20261111` | Replace XLSForm keeps choice entry ids (team locale strings no longer wiped); test added. Submodule `80df415` → `d13fec3`. |
| #79 | `hotfix-20261112` | #165 | `hotfix-20261112` | Fix missing choice labels; deploy job guard tests; TeamFactory namespace test fix. Submodule `d13fec3` → `083ea20`. |
| #80 | `docs-choice-label-verification` | – | – | Docs only: staging verification of the choice label fix. |
| #81 | `fix-choice-list-unique-rule` | – | – | Allow editing a choice list entry that has same-named copies. Main repo only. |
| #82 | `fix-processing-count` | – | – | Keep locale `processing_count` correct with atomic updates (fixes stuck translation processing count). Main repo only. |
| #83 | `fix-export` | #168 | `fix-export` | Export falls back to the default locale's text when a team locale has none. Submodule `083ea20` → `df3a7cf`. |
| #84 | `fix-duplicate-rows` | #169 | `fix-duplicate-rows` | Translation file limited to the module versions the team's form uses (removes duplicate rows). Submodule `df3a7cf` → `3eac9df`. |
| #85 | `improve-initial-pilot-page` | #170 | `improve-initial-pilot-page` | Explicit "Update test forms" button on Initial Pilot; hide expired QR codes; show snapshot time; atomic form claim during draft build/deploy; translation import notification kept in the bell. Submodule `3eac9df` → `002b832`. |

## Notes for the merge into `dev`

- **Submodule PR #155** was closed without merging (confirmed by Dan). Its branch `fix-issue-20261026` (commits `9d124c2`..`80b95d2`) reached the hotfix line through `staging-hotfix-20261026`, so its changes are still part of the delta.
- **Commits without a submodule PR.** `f7f5fe5`, `6ad9f3c`, `f9fc720` (main PR #72) and `ea6885f` (main PR #73) were committed directly on `staging-hotfix-20261026` with no submodule branch or PR (confirmed by Dan). They are still part of the delta.
- **Main PR #71 is merged into `dev` although submodule PR #155 was closed.** Main PR #71 only records a gitlink to commit `80b95d2`, so the pointer resolves. Those commits live only on the `staging-hotfix-20261026` line and are not in the submodule's `dev`.
- **Risk 1: dangling pointer.** If both `fix-issue-20261026` and `staging-hotfix-20261026` are deleted, commits from #155 (and #72/#73) lose all branch references, may be garbage-collected, and the main repo's submodule pointer would break. Keep both branches until the changes are ported into the restructured `dev`.
- **Risk 2: missed changes.** Anyone listing changes from merged submodule PRs only will miss #155 (`crop_list` labels, choice order, module localisation lookup, Replace XLSForm list button) and the directly committed #72/#73 changes. Use the commit range below instead.
- **Delta for the submodule** = `git diff e048b4b 002b832` (merge-base with `origin/dev` to the final hotfix state): 16 non-merge commits, 50 files changed, +2873/−125. Compute the delta by commit range, not by PR number.
- **Submodule base.** All submodule work sits on the old (pre-restructure) layout, based on `e048b4b`. The restructured `dev` has moved on (`origin/dev` includes PR #154 and later), so submodule changes will need to be ported to the new package paths rather than merged directly.
- **Final submodule state** of the hotfix line is `002b832` (`improve-initial-pilot-page` / `staging-hotfix-20261026`).
- **Main repo merge commits** (for cherry-picking in order): #68 `334f49e`, #71 `d752640`, #72 `b3ca63a`, #73 `a6ded82`, #74 `981fca5`, #75 `42d2a04`, #77 `29a267c`, #78 `320c1c7`, #79 `330afff`, #80 `8363a19`, #81 `e4ade7e`, #82 `8eb8ccf`, #83 `4961df1`, #84 `deca79d`, #85 `5921464`, #76 `a283364`.
