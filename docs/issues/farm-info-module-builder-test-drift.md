# FarmInfoModuleBuilder: stale `farm_` prefix in tests

Found while investigating the "Attempt to read property 'id' on null" crash in
`FarmInfoModuleBuilder::populate()` (fixed separately — see
[docs/change-logs](../change-logs) for that fix). Unrelated to that fix; left
untouched per Dan's decision.

## Symptom

`tests/Feature/Services/FarmInfoModuleBuilderTest.php` still asserts calculate
row names, `calculation` targets, and `farmer_note` text using a `farm_`
prefix, e.g.:

```php
$identifierRow = $rows->firstWhere('name', 'farm_certificate_no');
...
expect($text)->toContain('Certificate Number: ${farm_certificate_no},');
```

But `FarmInfoModuleBuilder::buildSurveyRows()` / `noteText()` (current code)
build these using the raw `DatasetVariable` name with no prefix:

```php
['name' => $variable->name, 'type' => 'calculate'],
...
$lines[] = $variable->label.': ${'.$variable->name.'},';
```

This causes 2 of 6 tests in that file to fail:
- "builds a calculate row per identifier and property variable, pulling from the selected entity"
- "builds the farmer_note listing every identifier and property with its label"

## Cause

Commit `2b414a9` ("minor fixes to Location and Farm Info builder...")
deliberately removed the `farm_` prefix from production code (previously
`'farm_'.$variable->name`) but never updated this test file to match.

## Open question

Whether the prefix removal in `2b414a9` was intentional/correct (in which case
the test should be updated to match, same as the `Local Farm Info` →
`Local farm info` casing drift from `91bed8e` that was fixed alongside the
Dataset bug), or whether dropping the prefix was itself a regression (e.g. if
downstream Xlsform/ODK tooling expects the `farm_` prefix on these calculated
field names to avoid colliding with the raw entity property names). Needs a
decision from whoever owns the Xlsform module builder work before either the
test or the production code is changed.
