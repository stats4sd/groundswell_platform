<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property ?Collection<int, mixed> $errors
 * @property-read Collection<int, array{row: ?int, attribute: ?string, messages: non-empty-list<string>}> $error_lines
 * @property-read int $error_count
 * @property-read string $status
 * @property-read ?string $file_name
 */
class Import extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function casts(): array
    {
        return [
            'success' => 'boolean',
            'errors' => 'collection',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * NOTE: this shadows the raw model_type column on read, so a table column or filter that
     * needs the class name (App\Models\SampleFrame\Location) must query the column directly
     * rather than go through the model.
     */
    public function modelType(): Attribute
    {
        return new Attribute(
            get: function ($value) {
                return Str::of($value)->afterLast('\\')->plural();
            },
        );
    }

    public function fileName(): Attribute
    {
        return new Attribute(
            get: function () {
                return $this->getFirstMedia()?->file_name;
            },
        );
    }

    /**
     * Every surface that displays import errors reads through here, because imports.errors has
     * held several shapes over time and old rows are never rewritten. Returns plain arrays
     * rather than objects: RepeatableEntry resolves its child entries by array key.
     */
    public function errorLines(): Attribute
    {
        return new Attribute(
            get: fn () => collect(self::normaliseErrorLines($this->errors)),
        );
    }

    public function errorCount(): Attribute
    {
        return new Attribute(
            get: fn (): int => $this->error_lines->sum(fn (array $line): int => count($line['messages'])),
        );
    }

    /**
     * Derived, not a column - so the imports table can neither sort nor search on it in the
     * database. A worker killed mid-import never reaches ReadChunk::failed(), so an import that
     * has been pending for an hour is reported as stale rather than left looking in-progress.
     */
    public function status(): Attribute
    {
        return new Attribute(
            get: function (): string {
                if ($this->error_count > 0) {
                    return 'failed';
                }

                if ($this->success) {
                    return 'complete';
                }

                if ($this->created_at?->lt(now()->subHour()) === true) {
                    return 'stale';
                }

                return 'pending';
            },
        );
    }

    /**
     * Maatwebsite\Excel\Jobs\ReadChunk::failed() raises ImportFailed once per failing chunk, so
     * a writer that assigns imports.errors keeps only the last chunk's problems. Every writer
     * appends through here instead, under a row lock because two chunks can fail on two workers.
     *
     * @param  array<int, array{row?: ?int, attribute?: ?string, errors?: mixed}>  $lines
     */
    public function appendErrorLines(array $lines): void
    {
        DB::transaction(function () use ($lines): void {
            $locked = static::query()->lockForUpdate()->find($this->getKey());

            if ($locked === null) {
                return;
            }

            $merged = array_map(
                fn (array $line): array => [
                    'row' => $line['row'],
                    'attribute' => $line['attribute'],
                    'errors' => $line['messages'],
                ],
                [...self::normaliseErrorLines($locked->errors), ...self::normaliseErrorLines($lines)],
            );

            $locked->update(['errors' => $merged]);

            $this->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** @return list<array{row: ?int, attribute: ?string, messages: non-empty-list<string>}> */
    private static function normaliseErrorLines(mixed $errors): array
    {
        return collect($errors)
            ->map(fn (mixed $line): array => self::normaliseErrorLine($line))
            ->reject(fn (array $line): bool => count($line['messages']) === 0)
            ->values()
            ->all();
    }

    /**
     * Handles all four shapes written since imports.errors was introduced: the canonical
     * {row, attribute, errors}, FarmEntityImport's older {location: {row, column}, errors},
     * a bare exception message string, and a line whose errors is a string not an array.
     *
     * @return array{row: ?int, attribute: ?string, messages: list<string>}
     */
    private static function normaliseErrorLine(mixed $line): array
    {
        if (! is_array($line)) {
            return ['row' => null, 'attribute' => null, 'messages' => self::normaliseMessages($line)];
        }

        if (array_is_list($line)) {
            return ['row' => null, 'attribute' => null, 'messages' => self::normaliseMessages($line)];
        }

        $location = is_array($line['location'] ?? null) ? $line['location'] : [];

        $row = $line['row'] ?? $location['row'] ?? null;
        $attribute = $line['attribute'] ?? $location['column'] ?? $location['attribute'] ?? null;

        return [
            'row' => $row !== null ? (int) $row : null,
            'attribute' => $attribute !== null ? (string) $attribute : null,
            'messages' => self::normaliseMessages($line['errors'] ?? $line['messages'] ?? null),
        ];
    }

    /** @return list<string> */
    private static function normaliseMessages(mixed $messages): array
    {
        return collect(Arr::wrap($messages))
            ->flatten()
            ->map(fn (mixed $message): string => trim((string) $message))
            ->filter()
            ->values()
            ->all();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
