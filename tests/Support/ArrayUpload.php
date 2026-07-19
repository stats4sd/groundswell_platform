<?php

namespace Tests\Support;

use Maatwebsite\Excel\Concerns\FromArray;

class ArrayUpload implements FromArray
{
    /** @param array<int, array<int, mixed>> $rows */
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }
}
