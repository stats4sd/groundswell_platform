# Plan: fix missing choice labels on new teams, and deploy job noise

**Status: Completed.** Change log: [2026-11-12-fix-missing-choice-labels-and-deploy-job-noise.md](../change-logs/2026-11-12-fix-missing-choice-labels-and-deploy-job-noise.md)

Agreed with Dan on 2026-11-12: C (export fills labels per locale), A (deploy job guards against a deleted owner), B (explain ODK's missing-label error, only after ODK rejects the form, so valid forms are never blocked), D-lite (quiet skip for a duplicate file-update job). A full atomic lock against duplicate generation was deferred.
