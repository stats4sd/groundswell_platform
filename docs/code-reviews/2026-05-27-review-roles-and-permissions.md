# Code Review — `review-roles-and-permissions` → `dev`

**Date**: 2026-05-27
**Branch**: `review-roles-and-permissions`
**Reviewer**: Claude (automated multi-angle review)
**Scope**: 56 files, 1,945 insertions — new Spatie role/permission model with 5 roles and policies across three Filament panels.

---

## Summary by Priority

| # | Severity | File | Issue | Comment / Resolution
| --- | --- | --- | --- | --- | 
| 1 | **Security** | `TeamTranslationReviewEditForm.php` | Public Livewire property used as server-side auth guard | Fixed by removing $canMaintain 
| 2 | **Security** | `UsersRelationManager.php` (App panel) | DetachAction/DetachBulkAction fully unguarded | Un-needed currently; to review alongside user / program / team management featuers
| 3 | **Security** | `UsersRelationManager.php` (3 panels) | AttachAction only has UI guard, no server-side abort | Un-needed currently; to review alongside user / program / team management featuers
| 4 | **Bug** | `Team.php` | Null deref on missing 'Team Admin' role in sendInvites() | Default to parent role if role doesn't exist
| 5 | **Bug** | `TeamsRelationManager.php` (Program panel) | CreateAction exposed to Program Viewers |
| 6 | **Bug** | `ListUsers.php` (Admin panel) | Base CreateAction not guarded for Global Viewers |
| 7 | **Reliability** | `RoleAndPermissionSeeder.php` | Role::create() crashes on re-run |
| 8 | **UX** | `EditProfile.php` | Cancel button hidden from read-only users |
| 9 | **Clarity** | `DataAnalysisIndex.php` | Wrong-named permission in canAccess() |
| 10 | **Cleanup** | `RoleAndPermissionSeeder.php` | Dead `maintain monitor data collection` permission |

---

## Findings

### 1\. Server-side authorization bypass via public Livewire property

**File**: `app/Livewire/SurveyLanguages/TeamTranslationReviewEditForm.php` line 44

`public bool $canMaintain = false;` is a hydrated Livewire property used as the sole server-side guard in `submit()` (line 108) and `duplicate()` (line 154). Without a `#[Locked]` attribute, Livewire will accept client-supplied state updates for this property. A malicious user can send a crafted component-update request setting `canMaintain = true`, causing the `if (!$this->canMaintain) { abort(403); }` check to pass and writing unauthorized translation data.

**Fix**: Either add `#[\Livewire\Attributes\Locked]` to the property declaration, or replace the check with a live re-evaluation:

```php
if (!auth()->user()->can('maintain survey translations')) { abort(403); }
```

---

### 2\. DetachAction and DetachBulkAction unguarded in App panel Team Users tab

**File**: `app/Filament/App/Resources/TeamResource/RelationManagers/UsersRelationManager.php` lines 94–100

The `headerActions` (invite and attach) are correctly gated behind `can('maintain my team')`, but `DetachAction` (line 94) and `DetachBulkAction` (line 100) carry no `->visible()` guard and no `abort(403)`. Any user with 'view my team' (e.g. Program Viewer) can remove members from a team.

---

### 3\. AttachAction has only a UI visibility guard — no server-side abort

**Files**:

*   `app/Filament/App/Resources/TeamResource/RelationManagers/UsersRelationManager.php` line 87
*   `app/Filament/Admin/Resources/TeamResource/RelationManagers/UsersRelationManager.php`
*   `app/Filament/Program/Resources/ProgramResource/RelationManagers/UsersRelationManager.php`

The "Add Existing User" `AttachAction` in all three panels has `->visible(fn () => auth()->user()->can('...'))` but no `->action()` callback with `abort(403)`. The sibling "Invite Users" action correctly has both. A user who bypasses the UI (direct Livewire wire call) can attach any user to a team or program without the required permission. `UserPolicy` does not define an `attachAny` method, so Filament has no policy fallback for this action.

---

### 4\. Fatal null dereference in `Team::sendInvites()` if 'Team Admin' role is missing

**File**: `app/Models/Team.php` (sendInvites method)

```php
$teamAdminRole = Role::where('name', 'Team Admin')->first();
// ...
$teamAdminRole->id  // crashes if role doesn't exist
```

On a fresh install before the seeder runs, or if the role was renamed/deleted, this throws `Attempt to read property "id" on null` for every invite attempt.

**Fix**: `Role::where('name', 'Team Admin')->firstOrFail()` or add a null check with a meaningful error.

---

### 5\. Program panel `TeamsRelationManager` — `CreateAction` has no visibility guard

**File**: `app/Filament/Program/Resources/ProgramResource/RelationManagers/TeamsRelationManager.php` line 15

`AttachAction` (line 18) and `DetachAction` (line 22) both have `->visible(fn () => auth()->user()->can('maintain my program'))`, but `CreateAction::make()` has no guard at all. A Program Viewer sees the Create button and can open the modal; `TeamPolicy::create()` will eventually block the save, but the button should not be visible to read-only users.

---

### 6\. Admin panel `ListUsers` — base `CreateAction` not guarded

**File**: `app/Filament/Admin/Resources/UserResource/Pages/ListUsers.php` line 22

The `getHeaderActions()` override iterates the parent's actions and applies `->visible()` only when `$action->getName() === 'invite users'`. The base `Actions\CreateAction::make()` (name: `'create'`) is not matched and remains fully visible to a Global Viewer who has `'view users'` but not `'maintain users'`. `UserPolicy::create()` will block the actual save, but the button and form are incorrectly exposed.

---

### 7\. Seeder uses `Role::create()` — crashes on re-run

**File**: `database/seeders/Prep/RoleAndPermissionSeeder.php` lines 16–20

All five roles are created with `Role::create([...])`. Re-running the seeder on a database that already has roles (e.g. resetting permissions on staging, or running in a CI environment) throws a unique constraint violation mid-seeder, leaving the database in a partially-seeded state.

**Fix**: Replace with `Role::firstOrCreate(['name' => '...'])`.

---

### 8\. `EditProfile` — Cancel button hidden from read-only users who land on the page

**File**: `app/Filament/App/Pages/Auth/EditProfile.php` (`getFormActions()`)

`canAccess()` uses `can('view my account') || can('maintain my account')`, so a Program Viewer (who has 'view my account') can reach the page. But the Cancel button in `getFormActions()` is wrapped in `->visible(fn () => can('maintain my account'))`. A read-only user who navigates to their profile sees disabled fields and no Cancel button — only the browser back button.

---

### 9\. `DataAnalysisIndex::canAccess()` uses an oddly-named permission

**File**: `app/Filament/App/Pages/DataAnalysis/DataAnalysisIndex.php` line 32

```php
return auth()->user()->can('view download data');
```

The page is titled "Data Analysis" but the gate is named `view download data`. The mutating actions inside are correctly guarded by `maintain download data`. This is a naming inconsistency that will confuse future maintainers about which permission controls access to which page. Worth renaming or at minimum documenting the intentional dual-use.

---

### 10\. Dead permission: `maintain monitor data collection`

**File**: `database/seeders/Prep/RoleAndPermissionSeeder.php`

The permission `maintain monitor data collection` is created and assigned to Program Admin and Team Admin but is checked by no policy, `canAccess()`, or action guard in the codebase. The Monitor Data Collection page is read-only with no write actions. The permission creates a false impression in the access-control model.
