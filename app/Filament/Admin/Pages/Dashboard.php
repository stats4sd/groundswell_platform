<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\DataCollectedWidget;
use App\Filament\Admin\Widgets\RegistrationsWidget;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;

class Dashboard extends Page
{
    protected static string $view = 'filament.admin.pages.dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = 0;

    public static function getNavigationLabel(): string
    {
        return 'Admin Panel Dashboard';
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('do admin');
    }

    public function getHeaderWidgets(): array
    {
        return [
            RegistrationsWidget::class,
        ];
    }

    public function getFooterWidgets(): array
    {
        return [
            DataCollectedWidget::class,
        ];
    }

//    public function getHeader(): ?View
//    {
//        return view('components.custom-header', [
//            'heading' => $this->getHeading(),
//            'subheading' => $this->getSubheading(),
//            'actions' => $this->getHeaderActions(),
//            'breadcrumbs' => $this->getBreadcrumbs(),
//        ]);
//    }

    protected ?string $heading = '';
}
