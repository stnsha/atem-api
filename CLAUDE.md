# ATEM API — Claude Code Guide

## Environment

- **Framework:** Laravel 10, PHP 8.2+
- **Runtime:** Laragon on Windows (`C:\laragon\www\atem-api`)
- **Database:** MySQL, database name `atem_api`
- **ODB Frontend:** `C:\xampp\htdocs\odb\atem` (always check alongside any API change)

## Key Constraints

### Migrations — Dev Only: Edit Existing, Never Create New

Do NOT create new migration files. In development, edit the existing migration files directly and run:
```bash
php artisan migrate:refresh --seed
```

### FK IDs Only — Never Store Name Snapshots in `atems`

`atems.issuer_staff_id` and `atems.staff_dept_id` store ODB FK ids. Staff names and department names are resolved on the ODB frontend by looking up these ids against the ODB `staff` and `staff_department` tables. Never add name columns to `atems`.

Exception: `atem_arci` stores name snapshots (`staff_name`, `department_name`) intentionally — ARCI members are a point-in-time assignment.

### Exceptions — Use Specific Classes

Do not use the base `Exception` class. Import and use the most specific exception class available.

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

`CalculateBonusEligibility` artisan command (`atem:calculate-bonus-eligibility {month} {year}`):
1. Fetches all non-draft ATEM cards for the period
2. Calls `StaffApiService::getStaffInfo()` to get current grade/struct for each issuer
3. Upserts `atem_bonus_eligibilities` records (snapshots grade/struct at run time)

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
database/migrations/        Edit in place, never add new files
```

## Common Commands

```bash
# Refresh database (dev only)
php artisan migrate:refresh --seed

# Run development server
php artisan serve

# Calculate bonus eligibility
php artisan atem:calculate-bonus-eligibility {month} {year}
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
