# ATEM API — Claude Code Guide

## Environment

- **Framework:** Laravel 10, PHP 8.1+
- **Runtime:** Laragon on Windows (`C:\laragon\www\atem-api`)
- **Database:** MySQL, database name `atem_api`
- **ODB Frontend (Production):** `C:\xampp\htdocs\odb\atem`
- **ODB Frontend (Local dev):** `C:\laragon\www\odb\atem` — run this locally via Laragon against this API during development
- Always check the relevant frontend copy alongside any API change.

## Key Constraints

### Migrations — Always Create a New Migration File

Every schema change — a brand-new table, or a change to an existing table (add/drop column, index, etc.) — gets its own new migration file. Do NOT edit old migration files that have already been committed.

```bash
php artisan make:migration <descriptive_name>
php artisan migrate
```

### FK IDs Only — Never Store Name Snapshots in `atems`

`atems.issuer_staff_id` and `atems.staff_dept_id` store ODB FK ids. Staff names and department names are resolved on the ODB frontend by looking up these ids against the ODB `staff` and `staff_department` tables. Never add name columns to `atems`.

Why: staff and department names can change over time (transfers, renames). If `atems` stored a name snapshot, it would silently drift out of sync with ODB. Resolving live via the FK id guarantees the name shown is always current.

Exception: `atem_arci` stores name snapshots (`staff_name`, `department_name`) intentionally — ARCI members are a point-in-time assignment.

### Exceptions — Use Specific Classes

Do not use the base `Exception` class. Import and use the most specific exception class available.

Why: a generic `Exception` can't be caught selectively by calling code, and it loses meaning in logs and error handlers. A specific class like `QueryException` lets callers and error handlers react precisely to what actually went wrong.

```php
// Correct
use Illuminate\Database\QueryException;
throw new QueryException($sql, $bindings, $previous);

// Wrong
throw new Exception('Something went wrong');
throw new \Exception('Something went wrong');
```

## Architecture

### Authentication

- JWT via `tymon/jwt-auth`, guard `auth:api`
- Shared service account JWT — staff identity comes from request body fields (`staff_id`, `actor_id`, `issuer_staff_id`), not the JWT subject
- Token TTL configured in `config/jwt.php`

### Staff Data from ODB

Staff grade, struct, and atem flag are fetched from the ODB system via `StaffApiService::getStaffInfo(array $staffIds)`:

```php
$map = $this->staffApiService->getStaffInfo([1, 2, 3]);
// Returns: [ staff_id => ['grade' => int, 'struct' => int, 'atem' => int] ]
```

The ODB API endpoint is `POST {ODB_API_URL}/staff/info.php`, configured in `.env` as `ODB_API_URL_LOCAL` / `ODB_API_URL_PROD`.

### `staff.atem` SuperAdmin Flag

`staff.atem TINYINT(1)` — `0` = normal user, `1` = superadmin.

The ODB `staff/info.php` endpoint returns this alongside `grade` and `struct`. `StaffApiService::getStaffInfo()` includes it in its return map. When `atem = 1`, the user has SuperAdmin privileges in the ATEM module regardless of their `grade`. The backend propagates this flag so future access control logic can act on it.

### Models and Relationships

```
Atem
  hasMany AtemArci
  hasMany AtemReferenceLink
  hasMany AtemAttachment
  hasMany AtemProgress
  hasMany AtemAuditLog
  belongsTo LevelStructure
  belongsTo IncentiveRule
  belongsTo AtemStatus (via atem_status_id)
```

`Atem` uses `SoftDeletes`. Hard deletes are not performed in normal operation.

### Audit Logging

`AtemObserver` fires on `Atem` model events and calls `AtemAuditLogger` to write immutable rows to `atem_audit_logs`. Never delete audit log records.

### Incentive Calculation

`IncentiveCalculatorService` computes `a_incentive_amount`, `r_incentive_amount`, and `total_incentive_amount` from `base_incentive` and the linked `IncentiveRule`. Called from `AtemController` when a card is closed with a completed/excellence status.

### Bonus Eligibility

`CalculateBonusEligibility` artisan command (`atem:calculate-bonus --month= --year=`):
1. Fetches all non-draft ATEM cards for the period
2. Calls `StaffApiService::getStaffInfo()` to get current grade/struct for each issuer
3. Upserts `atem_bonus_eligibilities` records (snapshots grade/struct at run time)

`--month` and `--year` both default to the current month/year when omitted. Passing `--year` alone (no `--month`) processes every month of that year in one run; `--all-months` forces the same whole-year behavior explicitly (e.g. `atem:calculate-bonus --year=2026 --all-months`).

## Laravel & MVC Basics (for this project)

This section is for anyone new to Laravel. It explains the request lifecycle using a real, working example from this codebase.

### What MVC means here

- **Model** — an Eloquent class in `app/Models/` that represents one database table. It lets you query/insert/update rows using PHP instead of writing raw SQL.
- **Controller** — a class in `app/Http/Controllers/` with methods that handle one incoming HTTP request each, and return a response.
- **View** — not used in this project. This is a JSON API, not an HTML app, so there are no Blade templates. The controller builds a JSON response directly instead of rendering a view.

### Request lifecycle, end to end

Example: `GET /api/atem/statuses`

**1. Route** (`routes/api.php`) maps a URL + HTTP verb to a controller method:
```php
Route::get('/atem/statuses', [AtemStatusController::class, 'index']);
```

**2. Controller** (`app/Http/Controllers/AtemStatusController.php`) runs, queries the model, and builds the response:
```php
public function index(): JsonResponse
{
    $statuses = AtemStatus::orderBy('id')->get();

    return response()->json([
        'success' => true,
        'data'    => $statuses,
    ]);
}
```

**3. Model** (`app/Models/AtemStatus.php`) — Eloquent maps this class to the `atem_statuses` table automatically, by naming convention (no config needed):
```php
class AtemStatus extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'value', 'description', 'system_action', 'incentive_treatment',
    ];
}
```
`$fillable` lists which columns can be mass-assigned (e.g. `AtemStatus::create($data)`). `SoftDeletes` means `delete()` sets a `deleted_at` timestamp instead of removing the row — see "Models and Relationships" above.

**4. Response shape** — there is **no shared helper or trait** for this. Every controller method builds the `success` / `message` / `data` array inline, by hand, every time (see "Response Shape Convention" below). When writing a new endpoint, copy the shape from a neighboring method in the same controller rather than inventing a new one.

### Validation

This project does **not** use Laravel `FormRequest` classes (there is no `app/Http/Requests/` directory). Validation is done inline, directly inside the controller method:
```php
$request->validate([
    'field' => 'required|string',
]);
```
Follow this existing pattern for new endpoints rather than introducing FormRequest classes, unless specifically asked to.

### Where to look for patterns

- "Models and Relationships" above — how models relate to each other in this app.
- "Directory Layout" below — where each kind of file lives.
- "Response Shape Convention" below — the exact JSON shape every endpoint must return.

## Directory Layout

```
app/
  Console/Commands/         Artisan commands
  Http/Controllers/
    API/AuthController      JWT login/logout/me
    AtemController          ATEM card CRUD + lookups
    AtemArciController      ARCI members
    AtemAttachmentController  File upload/download
    AtemBonusEligibilityController  Bonus records
    AtemProgressController  Progress updates
    AtemReferenceLinkController  Reference links
    AtemStatusController    Status list
    IncentiveRuleController Rule list
    LevelStructureController  Level list
    TableauApiController    Tableau proxy
  Models/                   Eloquent models
  Observers/AtemObserver    Audit trigger
  Services/
    OctopusApiService       HTTP base client for ODB
    StaffApiService         Grade/struct/atem from ODB
    IncentiveCalculatorService
    AtemAuditLogger
    TableauApiService
routes/api.php              All API routes
database/migrations/        Always add a new migration file, never edit existing ones
```

## Common Commands

```bash
# Create a new migration (for any schema change — new table or change to existing one)
php artisan make:migration <descriptive_name>

# Apply pending migrations
php artisan migrate

# Full local reset with seed data (only when you want to wipe and rebuild the whole local DB)
php artisan migrate:refresh --seed

# Run development server
php artisan serve

# Calculate bonus eligibility for a single month
php artisan atem:calculate-bonus --month=3 --year=2026

# Calculate bonus eligibility for every month of a year
php artisan atem:calculate-bonus --year=2026
```

## Response Shape Convention

All API responses follow:
```json
{
  "success": true,
  "message": "...",
  "data": { ... }
}
```

Error responses:
```json
{
  "success": false,
  "message": "...",
  "errors": { "field": ["validation message"] }
}
```
