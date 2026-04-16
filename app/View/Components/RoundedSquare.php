<?php

namespace App\View\Components;

use Illuminate\Contracts\View\Factory;
use Illuminate\Foundation\Application;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Illuminate\View\View;

class RoundedSquare extends Component
{
    public string $heading = '';

    public string|HtmlString $description = '';

    public function render(): Factory|Application|\Illuminate\Contracts\View\View|View|null
    {
        return view('components.rounded-square');
    }
}
