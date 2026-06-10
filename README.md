# ATEM API

Laravel 10 REST API backing the ATEM (Accountability, Tracking, Engagement & Metrics) module. It stores ATEM cards, ARCI members, progress updates, attachments, reference links, incentive rules, level structures, and bonus eligibility records. Staff identity and grade data are resolved on-demand from the ODB system via `StaffApiService`.

---

## Tech Stack

- **Framework:** Laravel 10 (PHP 8.2+)
- **Auth:** JWT via `tymon/jwt-auth`
- **Database:** MySQL (`atem_api` database)
- **ODB Integration:** HTTP calls to `staff/info.php` for staff grade/struct/atem data

---

## Setup

```bash
cd C:\laragon\www\atem-api
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate:fresh --seed
php artisan serve
```

### Key Environment Variables (`.env`)

| Key | Description |
|---|---|
| `APP_ENV` | `local` or `production` |
| `DB_*` | MySQL connection |
| `ODB_API_URL_LOCAL` | ODB API base URL for local (`http://localhost/api`) |
| `ODB_API_URL_PROD` | ODB API base URL for production |
| `JWT_SECRET` | JWT signing secret |

---

## Authentication

All routes except `POST /api/login` require `Authorization: Bearer <token>`.

The frontend uses a shared service account (`atem-service@local`) — individual staff are identified by `staff_id` fields in request bodies, not by the JWT subject.

```
POST /api/login
{ "email": "atem-service@local", "password": "atem-service-local" }
→ { "data": { "access_token": "...", "expires_in": 3600 } }
```

---

## API Endpoints

### Auth
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/login` | Obtain JWT |
| POST | `/api/logout` | Invalidate JWT |
| GET | `/api/me` | Current auth user |

### Lookups
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/atem/lookups` | Combined levels, rules, statuses |
| GET | `/api/atem/levels` | Level structures |
| GET | `/api/atem/rules` | Incentive rules |
| GET | `/api/atem/statuses` | ATEM statuses |

### ATEM Cards
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/atem` | All cards |
| POST | `/api/atem` | Create card (draft or final) |
| GET | `/api/atem/{id}` | Single card |
| PUT | `/api/atem/{id}` | Update card |
| DELETE | `/api/atem/{id}` | Delete card (draft only, issuer only) |

### ARCI Members
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/atem/{id}/arci` | Add member |
| DELETE | `/api/atem/{id}/arci` | Remove member (`?staff_id=&role=`) |
| DELETE | `/api/atem/{id}/arci/role/{role}` | Remove all of a role |

### Reference Links
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/atem/{id}/reference-links` | List |
| POST | `/api/atem/{id}/reference-links` | Add |
| DELETE | `/api/atem/{id}/reference-links/{linkId}` | Remove |

### Progress Updates
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/atem/{id}/progress` | List |
| POST | `/api/atem/{id}/progress` | Add |
| PUT | `/api/atem/{id}/progress/{progressId}` | Update |
| DELETE | `/api/atem/{id}/progress/{progressId}` | Remove |

### Attachments
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/atem/{id}/attachments` | List |
| POST | `/api/atem/{id}/attachments` | Upload (multipart) |
| DELETE | `/api/atem/{id}/attachments/{attId}` | Remove |
| GET | `/api/atem/{id}/attachments/{attId}/download` | Download file |

### Bonus Eligibility
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/bonus-eligibility` | List (`?month=&year=&staff_id=`) |
| PUT | `/api/bonus-eligibility/{id}` | Update remark |
| POST | `/api/bonus-eligibility/calculate` | Trigger calculation (`{month, year}`) |

---

## Database Schema

### `atems`
- `id`, `title`, `description`
- `issuer_staff_id`, `staff_dept_id` — ODB FK ids only; names resolved on the frontend
- `level_structure_id`, `incentive_rule_id`
- `base_incentive`, `a_incentive_amount`, `r_incentive_amount`, `total_incentive_amount`, `claimable`
- `start_date`, `end_date`, `is_extended`, `extended_date_1/2`, `extension_count`, `final_due_date`, `closure_date`
- `atem_status_id`, `remarks`
- `created_by`, `updated_by`, `closed_by` (staff ids)
- Soft deletes + timestamps

### `atem_arci`
`atem_id`, `staff_id`, `staff_name`, `staff_dept_id`, `department_name`, `role`, `assigned_by`

### `atem_reference_links`
`atem_id`, `name`, `url`, `added_by`

### `atem_attachments`
`atem_id`, `file_name`, `file_path`, `file_size`, `mime_type`, `uploaded_by`

### `atem_progress`
`atem_id`, `content`, `created_by`

### `atem_audit_logs`
`atem_id`, `actor_staff_id`, `action`, `old_values`, `new_values` — immutable audit trail

### `level_structures`
ATEM level definitions (L1 Operational through L4 Company-Level).

### `incentive_rules`
Incentive multiplier rules linked to level structures.

### `atem_statuses`
Card lifecycle statuses (Draft, Active, Completed, Completed with Excellence, Failed, Extended, etc.).

### `atem_bonus_eligibilities`
Monthly eligibility snapshots: `staff_id`, `staff_grade`, `staff_struct`, `month`, `year`, `remark`. Grade and struct are snapshotted at calculation time from the ODB API.

---

## ODB Integration

### `StaffApiService::getStaffInfo(array $staffIds)`

Calls `POST {ODB_API_URL}/staff/info.php` and returns:
```php
[
    staff_id => [
        'grade'  => int,   // ATEM grade level 0-6
        'struct' => int,   // Structure level
        'atem'   => int,   // SuperAdmin flag: 0=normal, 1=superadmin
    ]
]
```

### `staff.atem` SuperAdmin Flag

`staff.atem = 1` means the staff member is a SuperAdmin in the ATEM module regardless of their `staff.grade`. The ODB `staff/info.php` endpoint returns this flag alongside `grade` and `struct` so the backend has it available for future access control decisions.

---

## Console Commands

**`atem:calculate-bonus-eligibility {month} {year}`** (`app/Console/Commands/CalculateBonusEligibility.php`)

Calculates bonus eligibility for all staff with ATEM cards in the given period. Calls `StaffApiService` to snapshot current grade/struct, then writes `atem_bonus_eligibilities` records.

---

## Key Services

| Service | File | Purpose |
|---|---|---|
| `StaffApiService` | `app/Services/StaffApiService.php` | Fetch grade/struct/atem from ODB |
| `OctopusApiService` | `app/Services/OctopusApiService.php` | Base HTTP client for ODB API |
| `IncentiveCalculatorService` | `app/Services/IncentiveCalculatorService.php` | Incentive amount calculations |
| `AtemAuditLogger` | `app/Services/AtemAuditLogger.php` | Write audit log entries |
| `TableauApiService` | `app/Services/TableauApiService.php` | Tableau view data proxy |
