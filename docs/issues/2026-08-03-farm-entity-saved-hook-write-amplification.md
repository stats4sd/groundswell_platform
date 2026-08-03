# `FarmEntity` saved/deleted hooks amplify writes on bulk adoption

**Date raised:** 2026-08-03 (spun out of the [refreshFromCentral concurrency fix](../change-logs/farm-entity-refresh-concurrency.md); deliberately out of scope there)

`FarmEntity::booted()` (`app/Models/SampleFrame/FarmEntity.php:24-33`) fires per row on both `saved` and `deleted`:

```php
$farmEntity->owner->update(['has_updated_locations' => true]);
$farmEntity->owner->xlsforms()->update(['draft_needs_update' => true]);
```

Both are per-row flag flips to the same team row and the same set of xlsforms, so on a bulk operation every row after the first repeats work that is already done:

- Adopting a team's ~366 Central farms fires ~366 × (1 owner load + 1 `teams` update + 1 `xlsforms` mass update) on top of the farm inserts themselves. This is the main reason a first-time farms-list load took ~14s.
- Those writes now sit inside `refreshFromCentral()`'s transaction, so it holds row locks on `teams` and the team's `xlsforms` rows for the whole sync. Anything else touching those rows blocks until it commits.
- `bulkCreateFarms()` has the same shape - it batches the Central round trip but still inserts locally row by row, so the hooks fire per row there too.

Suggested direction: set the flags once per batch instead of per row - e.g. have the bulk paths (`refreshFromCentral()`, `bulkCreateFarms()`) suppress the model events and flip both flags once after the loop, or make the hook idempotent-cheap by skipping the write when the flag is already set. The per-row hook is still wanted for single-farm create/update/delete through the Filament pages.
