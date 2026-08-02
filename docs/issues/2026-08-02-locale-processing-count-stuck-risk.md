# `locales.processing_count` stuck/underflow risks

**Date raised:** 2026-08-02 (spun out of the processing-flag reset plan; deliberately out of scope there)

`locales.processing_count` tracks in-flight translation imports per locale, but its bookkeeping is fragile:

- Non-atomic `++`/`--`: `TeamTranslationReviewEditForm.php:145` increments and `NotifyUserThatLanguageImportIsComplete` / `NotifyUserThatLanguageImportIsFailed` decrement via read-modify-write on the model, so concurrent imports can lose updates.
- The column is unsigned, so a double-decrement throws instead of going negative.
- If a translation-import job dies without firing either Notify job (e.g. a worker crash or a failure that is not an `ImportFailed` event), the count is never decremented and the locale is stuck "processing".

Suggested direction for the September ODK Link rewrite: use atomic `increment()`/`decrement()` guarded against underflow, and add a failure path (or sweeper) that decrements when an import chain dies.
