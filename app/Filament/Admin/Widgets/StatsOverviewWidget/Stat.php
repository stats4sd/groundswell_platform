<?php

namespace App\Filament\Admin\Widgets\StatsOverviewWidget;

use Illuminate\Contracts\View\View;

class Stat extends \Filament\Widgets\StatsOverviewWidget\Stat
{
    protected ?string $suffix = null;

    public function render(): View
    {
        return view('filament.stats-overview-widget.stat', $this->data());
    }

    public function suffix(?string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function getSuffix(): ?string
    {
        return $this->suffix;
    }
}
