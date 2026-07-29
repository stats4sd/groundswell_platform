# Structured cross-team sharing of translation locales

**Status**: Deferred — out of scope for the issue #36 fixes (see docs/archive/plans/2026-07-16-translation-uploader-fixes.md).

The original platform intended locales/translations to be shareable across teams, but the implementation that shipped exposed every team-created locale to every team with the language selected — an unstructured leftover of an unfinished attempt. As part of the issue #36 fixes, visibility was restricted to default locales plus the team's own locales.

A future, structured approach could let a team mark a locale as "complete and shareable", with a review/approval step in the admin or program panels before it becomes visible to other teams. Design questions: who approves (Program Admin vs Super Admin), whether shared locales are copied or referenced (edits by the owner team after sharing), and how "Needs updating" status propagates to consumers of a shared locale.
