# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Testing
```bash
./vendor/bin/pest                          # Run all tests
./vendor/bin/pest --filter=SpecificTest    # Run a single test by name
./vendor/bin/pest tests/Feature/           # Run a specific test directory
```

### Code Quality
```bash
./vendor/bin/phpstan analyse   # Static analysis (level 5)
./vendor/bin/pint               # Code style formatting
./vendor/bin/rector             # Automated refactoring
```

### Frontend
```bash
npm run dev     # Development server
npm run build   # Production build
```

### Artisan
```bash
php artisan queue:work    # Process jobs (or use Horizon in prod)
php artisan horizon       # Start Horizon queue manager
```

## Architecture Overview

This is a multi-tenant Laravel 11 + Filament 3 platform for managing agricultural survey data collection via ODK Central.

### Multi-Panel Filament Architecture

Three Filament panels with distinct tenancy models:
- **Admin Panel** (`app/Providers/Filament/AdminPanelProvider.php`) — system administration, no tenancy
- **App Panel** (`app/Providers/Filament/AppPanelProvider.php`) — team-based tenancy using `Team::class`
- **Program Panel** (`app/Providers/Filament/ProgramPanelProvider.php`) — program-based tenancy

Panels use automatic resource/page/widget discovery. Panel-specific resources live in `app/Filament/Admin/`, `app/Filament/App/`, and `app/Filament/Program/`. Shared traits are in `app/Filament/Shared/`.

### Local Packages

Two in-tree packages in `/packages/` are loaded via `composer.json` path repositories:
- `stats4sd/filament-odk-link` — ODK Central integration (form deployment, submission processing)
- `stats4sd/filament-team-management` — Team/program membership and management

Models in this app often extend base classes from these packages (e.g., `User`, `Team`, `Dataset`).

### Model Domain Structure

Models are namespaced by domain under `app/Models/`:
- `Models/SampleFrame/` — Farm, Location, LocationLevel (the survey sample population)
- `Models/SurveyData/` — FarmSurveyData, Crop, Product and other ODK submission data
- `Models/Holpa/` — LocalIndicator, Theme, Domain (custom indicator framework)
- `Models/Reference/` — Reference/lookup data

Notable patterns:
- `staudenmeir/eloquent-has-many-deep` for nested multi-level relationships
- `staudenmeir/belongs-to-through` for inverse deep relationships
- `shiftonelabs/laravel-cascade-deletes` for cascading deletes on models
- `stats4sd/laravel-sql-views` for SQL view definitions
- `Spatie\MediaLibrary\InteractsWithMedia` for file attachments
- `Spatie\Permission` roles: Super Admin, Program Admin, and team-scoped roles

### Livewire Components

`app/Livewire/` contains interactive components for:
- `DataCollection/` — farm/location/submission-level data views
- `Lisp/` — custom indicator module version editing and question forms
- `SurveyLanguages/` — multi-language form management

These components are embedded within Filament pages and resources.

### Data Import/Export

- `app/Imports/` — 7 Excel importers (users, farms, locations, etc.) via `maatwebsite/excel`
- `app/Exports/` — 10+ Excel exporters including `DataExport/` (full dataset exports) and `LocalIndicatorXlsformQuestions/`

### Async Processing

- `app/Jobs/` — queued jobs for notifications
- `app/Events/` — events fired on import completion (LocationImportCompleted, LanguageImportIsComplete, etc.)
- Laravel Reverb (WebSockets) + Laravel Echo for real-time updates to frontend

### Testing Setup

- **Pest PHP 3** with Laravel, Livewire, and Architecture plugins
- SQLite in-memory for test database (`phpunit.xml`)
- `$seed = true` in TestCase — `DatabaseSeeder` runs automatically before each test
- Helper functions in `tests/Pest.php`: `createSuperAdmin()`, `createAppUser()`, `withAdminPanel()`, `withAppTenant()`, `withProgramTenant()`, `createProgramAdmin()`

### Frontend

Vue 3 + Vite + Tailwind CSS 3 with Filament preset. Entry points:
- `resources/js/app.js` — main app bundle (includes Leaflet maps, Chart.js, Laravel Echo)
- `resources/js/public-map.js` — public-facing map component
- Custom Filament theme: `resources/css/filament/app/theme.css`
