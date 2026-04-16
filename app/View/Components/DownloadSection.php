<?php

namespace App\View\Components;

use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Illuminate\View\View;

class DownloadSection extends Component
{
    public function __construct(
        public ?string $heading,
        public string|HtmlString|null $description,
        public ?string $buttonLabel,
        public ?string $url
    ){}

    public function render(): Factory|Application|\Illuminate\Contracts\View\View|View|null
    {
        return view('components.download-section');
    }
}
