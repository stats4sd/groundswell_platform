# filament-team-management Filament 5 reference fixes

Host-app changes after bumping the `stats4sd/filament-team-management` submodule to its Filament 5 line. The package restructured its Filament classes (Admin resources moved into per-resource subdirectories, and the Program panel moved from a `ProgramResource` to a `ManageProgram` tenant-profile page), so all host-app references to it were updated.

## Mechanical renames (package moved Admin resources into per-resource subdirectories)

- [ProgramResource.php](../../app/Filament/Admin/Resources/ProgramResource.php) — extends `…\Resources\Programs\ProgramResource`
- [ProgramResource/Pages/ListPrograms.php](../../app/Filament/Admin/Resources/ProgramResource/Pages/ListPrograms.php) — `…\Programs\Pages\ListPrograms`
- [TeamResource.php](../../app/Filament/Admin/Resources/TeamResource.php) — extends `…\Teams\TeamResource`; `InvitesRelationManager` from `…\Teams\RelationManagers`
- [TeamResource/RelationManagers/UsersRelationManager.php](../../app/Filament/Admin/Resources/TeamResource/RelationManagers/UsersRelationManager.php) — `…\Teams\RelationManagers\UsersRelationManager`
- [App TeamResource EditTeam](../../app/Filament/App/Resources/TeamResource/Pages/EditTeam.php) / [ListTeams](../../app/Filament/App/Resources/TeamResource/Pages/ListTeams.php) — `…\Teams\TeamResource`
- [UserResource/Pages/ListUsers.php](../../app/Filament/Admin/Resources/UserResource/Pages/ListUsers.php) — `…\Users\Pages\ListUsers`
- [ProgramPanelProvider](../../app/Providers/Filament/ProgramPanelProvider.php) — `RegisterProgram` moved from `Filament\App\Pages` to `Filament\Program\Pages`

## Admin pages — adopted package structure

- [UserResource.php](../../app/Filament/Admin/Resources/UserResource.php): the package dropped full-page `CreateUser`/`EditUser`; `getPages()` is now `index`-only (users are created via the list page's "invite users" action).

## Program panel — re-architected (Resource → tenant-profile page)

The package replaced the Program-panel `ProgramResource` with a `ManageProgram` `EditTenantProfile` page (Projects/Members/Invites tabs).

- Deleted the orphaned `app/Filament/Program/Resources/ProgramResource*` (resource, Edit/View pages, Teams/Users relation managers).
- Added [ManageProgram](../../app/Filament/Program/ManageProgram/ManageProgram.php) + [ManageProgramMembers](../../app/Filament/Program/ManageProgram/ManageProgramMembers.php) + [ManageProgramProjects](../../app/Filament/Program/ManageProgram/ManageProgramProjects.php) — subclasses that re-apply the **`maintain my program`** gating on invite/attach/detach/create/edit actions. Placed outside the discovered `Pages/Resources/Widgets` dirs so they're used only via `tenantProfile()`/`Livewire::make()`, not double-registered.
- Wired `->tenantProfile(ManageProgram::class)` and removed the now-empty `discoverResources` line in the panel provider.
- Rewrote [Dashboard.php](../../app/Filament/Program/Pages/Dashboard.php) to extend `Filament\Pages\Dashboard` (package `Dashboard` is gone), keeping the `view program admin panel dashboard` gate.

## Tests

- [AdminPanelCrudTest](../../tests/Feature/Crud/AdminPanelCrudTest.php): User block rewritten to list + invite-action model (full-page create/edit no longer exist).
- [ProgramPanelCrudTest](../../tests/Feature/Crud/ProgramPanelCrudTest.php): rewritten to `ManageProgram` (edit) + Admin `ListPrograms` create-action (create).

## Verification status

All changed files pass `php -l`; no references to old package paths remain; every new target class/middleware/model/event exists in the package source.

`pest`/`phpstan` could **not** be run: installed Filament is still **v3.3.52** (root `composer.json` pins `^3.3.3`), while the package now requires Filament 5. The repo is the intermediate state described in [the upgrade plan](../archive/plans/filament-5-livewire-4-laravel-13-upgrade.md) — these reference/structure fixes only fully validate after the staged `composer update` to Filament 5 lands.

Two things to re-check at that point:

1. The exact `EditTenantProfile` save/`fillForm` API used in `ProgramPanelCrudTest`.
2. That `Livewire::make()` resolves the un-discovered `ManageProgram*` widgets (the package relies on the same pattern, so it should).
