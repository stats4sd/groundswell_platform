# No delete action for team-created translation locales

**Status**: Open — reported in a comment on GitHub issue #36 (a duplicated locale could not be removed by the user).

The translations table (`TeamTranslationEntry`) offers only "Select" and "View / Edit" record actions. A team that duplicates or creates a locale by mistake cannot remove it. With visibility now scoped to the owning team (2026-07-16), the blast radius is smaller, but the mistaken locale still clutters the team's own list forever.

Suggested shape: a delete action visible only when the locale is editable by the current team (`is_editable`) and not `is_default`, blocked when the locale is the team's currently selected locale for the language, deleting its `LanguageString` records with it.
