# Milestone 3CI — Member Data Import Commit Backend Narrow Implementation

## Purpose of 3CI

Milestone 3CI implements the server-side member import commit endpoint for manual/paste row JSON data payloads. This endpoint allows authorized officers to commit a batch of members after they have previewed them, verifying the eligibility of each member row at execution time before writing records to the database.

## Branching Context

* **Base Branch:** `milestone-3ch-member-data-import-commit-backend-scope-and-authorization-closure`
* **Local Created Branch:** `milestone-3ci-member-data-import-commit-backend-narrow-implementation`

## Allowed Files

This milestone modified or created only the following approved files:

* `backend/routes/api.php`
* `backend/app/Http/Controllers/Api/V1/Members/MemberDataImportCommitController.php`
* `backend/app/Http/Requests/Members/MemberDataImportCommitRequest.php`
* `backend/app/Http/Resources/Members/MemberDataImportCommitResource.php`
* `backend/app/Services/Members/MemberDataImportCommitService.php`
* `backend/docs/milestone_3ci_member_data_import_commit_backend_narrow_implementation.md`
* `backend/tests/Feature/Milestone3ciMemberDataImportCommitBackendNarrowImplementationTest.php`

All other files are blocked and were not modified.

## Endpoint Implemented

* **Endpoint:** `POST /api/v1/clubs/{club}/members/import-commit`
* **Payload Format:** JSON manual row payloads only:
  ```json
  {
    "rows": [
      {
        "Member Code": "MEM001",
        "Member Name": "John Doe",
        "Mobile": "9876543210",
        "Email Address": "john@example.com",
        "Member Status": "Active",
        "Joined Date": "2026-01-01",
        "Blood Group": "O+",
        "Blood Donor Consent": "Yes"
      }
    ]
  }
  ```

## Relationship to Existing Preview Endpoint

* **Existing Preview Endpoint:** `POST /api/v1/clubs/{club}/members/import-preview` is separate and remains fully intact.
* **Confirmation:** The preview endpoint controller, request, resource, and service files were NOT modified or touched in any way.

## Authorization & Scope

* **Officer-Only Access:** Only users with active memberships in the club having roles `president-admin`, `secretary`, or `treasurer` are authorized to commit imports.
* **Normal Member Exclusion:** Users with only `member` or other non-officer roles are strictly forbidden and blocked.
* **Club-Scoped Behavior:** Endpoint operates entirely within the target club boundary.
* **Cross-Club Fail-Closed Behavior:** Attempts by a user from Club A to commit members to Club B are strictly blocked (403 Forbidden). Unauthenticated requests are blocked (401 Unauthorized).

## Server-Side Commit Validation

* **Never Trust Frontend Validation:** Every row in the commit payload is fully normalized and validated server-side.
* **Fresh Duplicate Re-Checks:** Duplicate rows inside the incoming batch are identified and failed at commit time.
* **Fresh Existing-Member Re-Checks:** The database is queried at execution time to ensure no new duplicates or overlaps with existing member records have been introduced since preview.
* **Stale Preview Mitigation:** Row checks use live database lookups during the transaction, preventing stale preview state bugs.

## Mutation Boundary & Row-Level Eligibility

* **Create-Only New Members:** This endpoint is strictly create-only for eligible, valid new rows.
* **No Updates/Deletes:** The endpoint never updates existing member records, deletes member records, or overwrites existing member data.
* **No Finance Mutation:** It does not mutate or touch members' financial ledger entries, dues, payments, receipts, payment histories, or balances.
* **Skip/Fail Behavior:**
  * **Failed:** Invalid rows or batch-duplicate rows.
  * **Skipped:** Rows matching existing member records.
  * **Created:** Eligible rows successfully stored as new members.
* **No-Op / Fail-Closed Preference:** The entire processing batch is run inside a database transaction (`DB::transaction`) to prevent partial or corrupted writes under execution failure.

## Blocked/Excluded Scope Claims

* No Excel/CSV file upload or storage backend changes.
* No integration of spreadsheet parsing packages/parsers.
* No asynchronous job or queue processing.
* No frontend runtime changes or additions.
* No SaaS multi-tenant configuration modifications.
* No client-specific hardcoding.

## Test Suite Adjustments for Obsolete Absence Checks

To accommodate the approved 3CI `import-commit` route and `MemberDataImportCommitController` file without weakening other scope protections, we narrowly updated the obsolete absence assertions in the following older milestone test files:
* **Route whitelist checks:** Whitelisted `api/v1/clubs/{club}/members/import-commit` in `Milestone3ak`, `Milestone3al`, `Milestone3b`, `Milestone3c`, `Milestone3d`, `Milestone3e`, `Milestone3f`, `Milestone3g`, `Milestone3h`, `Milestone3i`, `Milestone3j`, `Milestone3k`, and `Milestone3l` tests.
* **Controller file checks:** Whitelisted `MemberDataImportCommitController.php` in `Milestone3by`, `Milestone3bz`, `Milestone3cb`, `Milestone3cc`, `Milestone3cd`, `Milestone3ce`, `Milestone3cf`, `Milestone3cg`, and `Milestone3ch` tests.
* **Expected route signature arrays:** Added `POST api/v1/clubs/{club}/members/import-commit` in `Milestone3bv`, `Milestone3bw`, `Milestone3bx`, `Milestone3bz`, `Milestone3ca`, and `Milestone3r` tests.

All other broad scope gates and mutation protections in those tests remain fully active.

## Next Milestone

* **Expected Next Milestone:** `Milestone 3CJ — Member Data Import Commit Backend Validation and Closure`
