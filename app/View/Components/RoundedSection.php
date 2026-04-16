<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Illuminate\View\View;

class RoundedSection extends Component
{
    public function __construct(
        public ?string $heading,
        public string|HtmlString|null $description,
        public ?string $buttonLabel,
        public ?string $url
    ) {}

    public function render(): \Illuminate\Contracts\View\View|Application|Factory|Htmlable|Closure|string|View|null
    {
        return view('components.rounded-section');
    }
}
