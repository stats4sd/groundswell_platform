# AEC Portfolio — Roles, Permissions & Access Control Audit

> Reference document for evaluating what to port into `groundswell_platform`.
> Audited: 2026-05-15

---

## 1. Spatie Package Configuration

File: `config/permission.php`

| Setting | Value |
|---|---|
| Permission model | `Spatie\Permission\Models\Permission` |
| Role model | `Spatie\Permission\Models\Role` |
| Guard | `web` |
| Team support | **Enabled** |
| Team foreign key | `organisation_id` (not the default `team_id`) |
| Cache expiry | 24 hours |
| Cache key | `spatie.permission.cache` |
| Wildcard permissions | Disabled |
| Exception display | Disabled (permission names hidden from users) |

**Database tables:**

- `roles`
- `permissions`
- `model_has_permissions` — direct user↔permission assignments (unused in practice)
- `model_has_roles` — user↔role assignments (scoped by `organisation_id`)
- `role_has_permissions` — role↔permission assignments

---

## 2. Database Migrations

| Migration | Purpose |
|---|---|
| `2021_04_26_102120_create_permission_tables.php` | Standard Spatie tables with unique (name, guard_name) |
| `2025_01_16_113418_add_teams_fields.php` | Adds `organisation_id` to roles, model_has_permissions, model_has_roles; updates PKs to include org scope |
| `2022_09_13_100658_create_invites_table.php` | Institutional member invites (email, org, inviter, token, is_confirmed) |
| `2022_09_13_103345_create_organisation_members_table.php` | User↔Organisation pivot (user_id, organisation_id) |
| `2022_09_13_100115_create_organisations_table.php` | Core organisation/team model |

---

## 3. Roles (5 total)

| Role | Scope | Description |
|---|---|---|
| **Site Admin** | Global (no org) | Full access across all organisations and all system content |
| **Site Manager** | Global (no org) | Platform-wide content management; can manage organisations but not users/invites |
| **Institutional Admin** | Org-scoped | Full control of one organisation — members, portfolios, projects, data |
| **Institutional Assessor** | Org-scoped | Can assess/edit projects and portfolios; cannot invite members or download data |
| **Institutional Member** | Org-scoped | Read-only viewer; can view dashboards and portfolios |

---

## 4. Permissions (43 total)

### Geographic / Reference Data
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `view continents` | ✓ | | | | |
| `view regions` | ✓ | | | | |
| `view countries` | ✓ | | | | |

### User Management
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `view users` | ✓ | | | | |
| `maintain users` | ✓ | | | | |
| `view admin user invites` | ✓ | | | | |
| `maintain admin user invites` | ✓ | | | | |

### System Content (Red Lines, Principles, Score Tags)
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `view red lines` | ✓ | ✓ | | | |
| `maintain red lines` | ✓ | ✓ | | | |
| `view principles` | ✓ | ✓ | | | |
| `maintain principles` | ✓ | ✓ | | | |
| `view score tags` | ✓ | ✓ | | | |
| `maintain score tags` | ✓ | ✓ | | | |
| `review custom score tags` | ✓ | ✓ | | | |

### Institution Management
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `view institutions` | ✓ | ✓ | | | |
| `maintain institutions` | ✓ | ✓ | | | |
| `view institutional members` | ✓ | ✓ | | | |
| `invite institutional members` | ✓ | | ✓ | | |
| `maintain institutional members` | ✓ | | ✓ | | |
| `edit own institution` | | | ✓ | | |

### Dashboards
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `view institution-level dashboard` | ✓ | | ✓ | ✓ | ✓ |
| `view portfolio-level dashboard` | ✓ | | ✓ | ✓ | ✓ |
| `view project-level dashboard` | ✓ | | ✓ | ✓ | ✓ |

### Data Downloads
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `download institution-level data` | ✓ | | ✓ | | |
| `download portfolio-level data` | ✓ | | ✓ | | |
| `download project-level data` | ✓ | | ✓ | | |
| `download dashboard summary data` | ✓ | | ✓ | ✓ | ✓ |

### Portfolios & Projects
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `view portfolios` | ✓ | | ✓ | ✓ | ✓ |
| `maintain portfolios` | ✓ | | ✓ | ✓ | |
| `view projects` | ✓ | | ✓ | ✓ | ✓ |
| `maintain projects` | ✓ | | ✓ | ✓ | |

### Assessment
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `review redlines` | ✓ | | ✓ | ✓ | |
| `assess project` | ✓ | | ✓ | ✓ | |

### Custom Content
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `view custom principles` | ✓ | ✓ | ✓ | ✓ | ✓ |
| `maintain custom principles` | ✓ | ✓ | ✓ | ✓ | |

### User Self-Service
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `show institution name and role` | | | ✓ | ✓ | ✓ |
| `request to remove all personal data from an institution` | | | ✓ | ✓ | ✓ |
| `request to leave an institution` | | | ✓ | ✓ | ✓ |
| `request to remove everything for institution` | ✓ | | ✓ | | |

### Navigation / Session
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `select institution` | ✓ | ✓ | | | |
| `auto set default institution` | | | ✓ | ✓ | ✓ |

### Platform Admin Tools
| Permission | Site Admin | Site Manager | Inst. Admin | Inst. Assessor | Inst. Member |
|---|:---:|:---:|:---:|:---:|:---:|
| `manage user feedback` | ✓ | ✓ | | | |
| `manage revisions` | ✓ | ✓ | | | |
| `manage removal requests` | ✓ | ✓ | | | |
| `manage definitions` | ✓ | ✓ | | | |
| `manage help text entries` | ✓ | ✓ | | | |

---

## 5. Policy Classes (12 total)

All policies live in `app/Policies/` and are **auto-discovered** — no explicit `$policies` array in `AuthServiceProvider`.

All policies check **permissions only**. They do not check model ownership or organisation membership — data scoping is handled separately via Eloquent global scopes.

| Policy | Governed Model | Methods | Permissions Used |
|---|---|---|---|
| `ProjectPolicy` | `Project` | viewAny, view, create, update, delete, restore, forceDelete, `reviewRedlines`, `assessProject` | `view projects`, `maintain projects`, `review redlines`, `assess project` |
| `PortfolioPolicy` | `Portfolio` | viewAny, view, create, update, delete, restore, forceDelete | `view portfolios`, `maintain portfolios` |
| `OrganisationPolicy` | `Organisation` | viewAny, view, create, update, delete, restore, forceDelete | `view institutions` / `maintain institutions`; update also accepts `edit own institution` |
| `OrganisationMemberPolicy` | `OrganisationMember` | viewAny, view, create, update, delete, restore, forceDelete | `view institutional members`, `invite institutional members`, `maintain institutional members` |
| `UserPolicy` | `User` | viewAny, view, create, update, delete, restore, forceDelete | `view users`, `maintain users` |
| `RedLinePolicy` | `RedLine` | viewAny, view, create, update, delete, restore, forceDelete | `view red lines`, `maintain red lines` |
| `PrinciplePolicy` | `Principle` | viewAny, view, create, update, delete, restore, forceDelete | `view principles`, `maintain principles` |
| `ScoreTagPolicy` | `ScoreTag` | viewAny, view, create, update, delete, restore, forceDelete | `view score tags`, `maintain score tags` |
| `RoleInvitePolicy` | `RoleInvite` | viewAny, view, create, update, delete, restore, forceDelete | `view admin user invites`, `maintain admin user invites` |
| `ContinentPolicy` | `Continent` | viewAny, view (read-only) | `view continents` |
| `CountryPolicy` | `Country` | viewAny, view (read-only) | `view countries` |
| `RegionPolicy` | `Region` | viewAny, view (read-only) | `view regions` |

### `ProjectPolicy` — custom methods

```php
// reviewRedlines() — beyond standard CRUD
public function reviewRedlines(User $user, Project $project): bool
{
    return $user->can('review redlines');
}

// assessProject()
public function assessProject(User $user, Project $project): bool
{
    return $user->can('assess project');
}
```

### `OrganisationPolicy` — dual-permission update

```php
public function update(User $user, Organisation $organisation): bool
{
    return $user->can('maintain institutions') || $user->can('edit own institution');
}
```

---

## 6. AuthServiceProvider

`app/Providers/AuthServiceProvider.php` has an **empty `$policies` array** and no `Gate::define()` calls. Policies are resolved automatically by Laravel's implicit discovery.

One manual gate exists in `TelescopeServiceProvider`:

```php
Gate::define('viewTelescope', function ($user) {
    // custom Telescope access logic
});
```

---

## 7. Custom Middleware (4 classes)

### `CheckIfAdmin`
Guards the Backpack admin panel. Verifies user is authenticated via `backpack_auth()->guest()`. Falls back to a `checkIfUserIsAdmin()` method (currently returns `true` for all authenticated users — the role check is commented out).

### `SetOrgForPermissions`
Sets `setPermissionsTeamId()` to the first organisation when no org is active in the session. Allows Site Admin/Manager to reach the admin panel before selecting an org.

### `TeamsPermission`
The core team-scoping middleware. Reads `session('selectedOrganisationId')` and calls `setPermissionsTeamId($orgId)` on every request. This ensures all downstream `can()` / `hasPermissionTo()` calls are scoped to the correct organisation.

### `EnsureOrganisationIsSelected`
Enforces that a user has selected an organisation before accessing the main app. If the user belongs to only one organisation, it is auto-selected. Otherwise, the user is redirected to a selection view.

---

## 8. Route Groups & Middleware

Routes are in `routes/backpack/custom.php` and `routes/web.php`.

**Group 1 — Admin panel setup** (middleware: `set_org_for_permissions`)
- Allows access before an org is selected
- Covers: org selection UI, system-level CRUD (continents, countries, regions, users, principles, red lines, score tags, role invites)

**Group 2 — Main application** (middleware: `org.selected`, `teams.permission`)
- Requires an org to be selected in session
- Covers: organisation detail/edit/export, portfolio CRUD, project CRUD (with `project.set` middleware), assessments, dashboards, custom principles, user role management, data deletion, feedback, help text

**API routes** (no role/permission middleware — controller-level checks only):
- `POST /organisation/update`
- `GET /assessment/{assessment}/principle-assessments`
- `POST /exchange-rate`
- `POST /store-project-id-in-session`

**PDF routes** (basic auth, auth policy deliberately bypassed):
- `GET /project/{id}/show-as-pdf`
- `GET /assessment/{id}/show-as-pdf`

---

## 9. Authorization in Controllers

All CRUD controllers call `$this->authorize()` against their corresponding policy:

```php
// List
$this->authorize('viewAny', Project::class);

// Single resource
$this->authorize('view', $project);
$this->authorize('update', $project);

// Custom policy methods
$this->authorize('assessProject', $project);
$this->authorize('reviewRedlines', $project);
```

A few controllers use direct `can()` instead:

```php
if (!Auth::user()->can('manage user feedback')) {
    abort(403);
}
```

---

## 10. View-Level Checks (Blade)

Views use `Auth::user()->can()` to **conditionally show UI elements only** — they do not enforce access. Real enforcement is always in the controller.

```blade
@if(Auth::user()->can('maintain institutional members'))
    {{-- show edit/remove buttons --}}
@endif

@if(Auth::user()->can('invite institutional members'))
    {{-- show invite button --}}
@endif

@if(Auth::user()->can('download project-level data'))
    {{-- show export button --}}
@endif
```

No `@can`, `@role`, or `@hasrole` Blade directives are used — only raw `@if` with `can()`.

---

## 11. Data Scoping — How Users Only See Their Data

Three mechanisms work together:

| Mechanism | Where | How |
|---|---|---|
| **Spatie team support** | `model_has_roles` table | `organisation_id` column means a role in Org A grants no permissions in Org B |
| **`Organisation` global scope** (`'owned'`) | `Organisation` model | Eloquent queries automatically filter to orgs the user belongs to (or bypassed by `view institutions` permission) |
| **Session-based team ID** | `TeamsPermission` middleware | `setPermissionsTeamId(session('selectedOrganisationId'))` activates the correct scope per request |

---

## 12. Models

### `User` (`app/Models/User.php`)
- Traits: `HasRoles` (Spatie), `HasFactory`, `Notifiable`, `CrudTrait`
- `isAdmin()` — returns `true` if user has `'Site Admin'` role
- `withPermissionNames()` — returns flattened array of all permission names from the user's roles
- Relationships: `organisations()`, `removalRequests()`, `userFeedBacks()`, `revisions()`, `userPreference()`, `signed_organisations()`, `tempProjectImports()`

### `Organisation` (`app/Models/Organisation.php`)
- Acts as the "team" for all Spatie scoping
- Global scope `'owned'` restricts queries:
  - User ID 1 (default Site Admin) always passes
  - Users with `view institutions` permission pass (Site Admin/Manager)
  - Otherwise restricted to organisations the user is a member of
- Relationships: `users()`, `admins()` (Institutional Admins), `editors()` (Institutional Assessors), `viewers()` (Institutional Members), `projects()`, `portfolios()`, `assessments()`, `invites()`
- `sendInvites($emails, $roleId)` — creates invitations and assigns roles

### `Invite` / `RoleInvite`
- Both have `is_confirmed` flag and observers that fire invitation emails on creation
- Both carry a global scope `'unconfirmed'` filtering to pending invitations
- `Invite` — for institutional membership (`invite institutional members` permission)
- `RoleInvite` — for Site Admin/Manager level invitations (`maintain admin user invites` permission)

---

## 13. Key Design Patterns

### Permission naming convention
All permissions follow `verb noun` format with two verbs:
- `view X` — read access
- `maintain X` — create, update, delete access

Exception: some action-specific permissions use descriptive names (`review redlines`, `assess project`, `edit own institution`).

### No direct permission assignment to users
All permissions flow through roles. Users cannot hold permissions directly. This is standard RBAC — simpler to reason about but less granular.

### Policies are permission-only
No policy checks ownership or org membership. Those concerns are handled by:
1. Global scopes (Eloquent query filtering)
2. Spatie team scoping (permission checks already scoped to selected org)

This keeps policies simple and readable.

### Custom policy methods extend CRUD
`ProjectPolicy` adds `reviewRedlines()` and `assessProject()` beyond the standard CRUD methods. This is a clean pattern for actions that don't map to create/update/delete.

---

## 14. Gaps & Things to Be Aware Of

| Issue | Detail |
|---|---|
| **Hard-coded role names** | `Organisation` model queries for `'Institutional Admin'` by string in a few places. Consider constants or an enum if porting. |
| **`AuthServiceProvider` is empty** | Policies are auto-discovered — no single place to see all registrations. Fine in Laravel 10+ but can surprise newcomers. |
| **PDF routes bypass auth** | Deliberate, to support print/export flows. Decide early if `groundswell_platform` needs the same. |
| **Team ID middleware is critical** | If `TeamsPermission` middleware is missing from a route group, `setPermissionsTeamId()` is never called and all org-scoped permission checks fail silently (may grant or deny incorrectly). |
| **`CheckIfAdmin` role check is commented out** | The admin panel check currently passes all authenticated users through. The actual role-level restriction relies on policies within the panel, not the gateway middleware. |
| **No audit trail for permission changes** | Role assignment changes are not logged. The `Revision` model tracks some data changes but not role/permission events. |
| **Blade uses `Auth::user()->can()` not `@can`** | Inconsistent with Laravel conventions — not a bug but worth standardising if porting. |
