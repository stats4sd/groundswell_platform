<?php

namespace App\Imports\Concerns;

use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Validators\ValidationException;
use Throwable;

/**
 * Shared by every importer that writes imports.errors, so all of them agree on one stored
 * payload shape (Import::errorLines() normalises the older shapes still in the database) and
 * on one plain-text summary for notification bodies.
 */
trait FormatsImportFailures
{
    /**
     * @return array<int, array{row: ?int, attribute: ?string, errors: array<int, string>}>
     */
    protected function formatFailures(Throwable $exception): array
    {
        if (! $exception instanceof ValidationException) {
            return [
                [
                    'row' => null,
                    'attribute' => null,
                    'errors' => [$exception->getMessage()],
                ],
            ];
        }

        return collect($exception->failures())
            ->map(fn (Failure $failure) => [
                'row' => $failure->row(),
                'attribute' => $failure->attribute(),
                'errors' => $failure->errors(),
            ])
            ->all();
    }

    /**
     * A plain-text summary for the failure notification and for the dependent import's record.
     * Validation failures are capped because one badly prepared file can fail every row, and
     * a ValidationException's own message ("The given data was invalid.") names nothing.
     */
    protected function describeFailure(Throwable $exception): string
    {
        if (! $exception instanceof ValidationException) {
            return $exception->getMessage();
        }

        $failures = collect($exception->failures());

        $summary = $failures->take(10)
            ->map(fn (Failure $failure) => "Row {$failure->row()}: ".implode(' ', $failure->errors()))
            ->implode("\n");

        if ($failures->count() <= 10) {
            return $summary;
        }

        return $summary."\n(and ".($failures->count() - 10).' more rows with problems)';
    }
}
