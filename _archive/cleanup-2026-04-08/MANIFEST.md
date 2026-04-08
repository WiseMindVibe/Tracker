# Cleanup Manifest (2026-04-08)

Archive-first cleanup for unused/obsolete files.

## Protected Exclusions

- `.git/**`
- `.env`
- `public/**`
- `api/**`
- `data/countries.json`
- `src/migrations/files/**`

## Candidate Classification

### High-Confidence Archive Candidates

- `src/services/telegram.php`
  - Reason: Duplicates `sendTelegramMessage()` already present in `src/services/redirection_functions.php`.
- `src/models/ModelReporting.php`
  - Reason: Defines `ReportingModel`; no in-repo references found.
- `src/functions/affiliate_offer_url.php`
  - Reason: Function definitions are unreferenced in repo usage checks.

### Needs-Review Archive Candidates

- `src/migrations/explain_dashboard_queries.php`
- `src/migrations/explain_reporting_v2_queries.php`
- `src/controllers/ControllerTest.php`
- `src/models/ModelTest.php`
- `src/views/ViewTest.php`

## Archive Mapping

### High-Confidence

- `src/services/telegram.php` -> `_archive/cleanup-2026-04-08/src/services/telegram.php`
- `src/models/ModelReporting.php` -> `_archive/cleanup-2026-04-08/src/models/ModelReporting.php`
- `src/functions/affiliate_offer_url.php` -> `_archive/cleanup-2026-04-08/src/functions/affiliate_offer_url.php`

### Needs-Review

- `src/migrations/explain_dashboard_queries.php` -> `_archive/cleanup-2026-04-08/needs-review/src/migrations/explain_dashboard_queries.php`
- `src/migrations/explain_reporting_v2_queries.php` -> `_archive/cleanup-2026-04-08/needs-review/src/migrations/explain_reporting_v2_queries.php`
- `src/controllers/ControllerTest.php` -> `_archive/cleanup-2026-04-08/needs-review/src/controllers/ControllerTest.php`
- `src/models/ModelTest.php` -> `_archive/cleanup-2026-04-08/needs-review/src/models/ModelTest.php`
- `src/views/ViewTest.php` -> `_archive/cleanup-2026-04-08/needs-review/src/views/ViewTest.php`

## Organization Rename

- `readme.mb` -> `README.md`

## Execution Status

- Completed on `2026-04-08`.
- Source-path files above were removed from active locations after archive copies were created.
- Archive copies are retained for rollback and traceability.

## Kept vs Archived Report

### Removed From Active Tree (Approved)

- `src/services/telegram.php`
- `src/models/ModelReporting.php`
- `src/functions/affiliate_offer_url.php`
- `src/migrations/explain_dashboard_queries.php`
- `src/migrations/explain_reporting_v2_queries.php`
- `src/controllers/ControllerTest.php`
- `src/models/ModelTest.php`
- `src/views/ViewTest.php`
- `readme.mb` (renamed to `README.md`)

### Retained In Archive

- `_archive/cleanup-2026-04-08/src/services/telegram.php`
- `_archive/cleanup-2026-04-08/src/models/ModelReporting.php`
- `_archive/cleanup-2026-04-08/src/functions/affiliate_offer_url.php`
- `_archive/cleanup-2026-04-08/needs-review/src/migrations/explain_dashboard_queries.php`
- `_archive/cleanup-2026-04-08/needs-review/src/migrations/explain_reporting_v2_queries.php`
- `_archive/cleanup-2026-04-08/needs-review/src/controllers/ControllerTest.php`
- `_archive/cleanup-2026-04-08/needs-review/src/models/ModelTest.php`
- `_archive/cleanup-2026-04-08/needs-review/src/views/ViewTest.php`

## Validation Results

- Reference checks show removed symbols only inside `_archive/cleanup-2026-04-08/**`.
- No `require/include` references to `src/services/telegram.php` remain.
- PHP lint checks passed for:
  - `index.php`
  - `public/postback.php`
  - `src/bootstrap.php`
  - `src/controllers/ControllerReporting.php`
  - `src/services/ReportingService.php`

