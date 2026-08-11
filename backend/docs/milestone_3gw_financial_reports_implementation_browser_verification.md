# Milestone 3GW - Financial Reports Implementation and Browser Verification

Base: remotely verified 3GV commit `0e1d69253347b9b1d51c54e504cdef1808c664e3`.

## Activated contract

The existing Reports module now exposes only the five 3GV-approved financial keys: `collections-summary`, `member-outstanding-balances`, `charge-collection-status`, `payment-register`, and `receipt-register`. They are computed on demand from `ClubCharge`, `MemberCharge`, `PaymentAllocation`, `MemberPayment`, `PaymentReceipt`, and Member identity fields. No financial source table, generated report persistence, arbitrary SQL, export, PDF, CSV, Excel, schedule, email, WhatsApp, public, or multi-club control was added.

Every authoritative amount is a signed PHP integer sourced from integer-paise database fields. Currency is `INR`; display strings are formatted by the backend. Active collections count only allocations whose linked payment is `recorded`. Voided payments remain in the Payment Register, are excluded from collected totals, and retain stable voided receipt rows.

## Exact synthetic reconciliation

All mutation work used isolated SQLite in the disposable external file `%TEMP%\lc_beenachi_3gw_financial_reports_isolated.sqlite`. Before fixture creation, the same Laravel process hard-failed unless the driver was exactly SQLite, the resolved database path exactly matched that file, the file existed, and it was outside the repository. It printed `SYNTHETIC_SQLITE_ALIGNMENT_CONFIRMED` before opening the transaction.

The live browser fixture predetermined and returned:

- assessed: `271002` paise / INR 2710.02;
- collected: `143343` paise / INR 1433.43;
- outstanding: `127659` paise / INR 1276.59;
- voided payment value: `1000` paise, separate from active collections;
- fully paid / partially paid / unpaid: `1 / 2 / 1`;
- 12 payment rows, including eight one-paise pagination fixtures;
- recorded payment value `143343` paise and voided value `1000` paise;
- stable voided receipt `GW-2026-0003` with state `voided`;
- duplicate member names with different stable member IDs;
- duplicate charge titles with different stable charge IDs;
- unique sentinel `FINANCIAL-REPORT-SENTINEL-3GW`;
- cross-club sentinel excluded.

The focused in-memory API fixture independently reconciled `270002 / 143335 / 126667` paise before the pagination-only obligation and eight one-paise payments were added. It passed 58 assertions covering role scope, privacy, void rules, receipt rules, filters, pagination, duplicate identity, and cross-club denial.

## Genuine PostgreSQL protection

Laravel's restored application configuration connected to PostgreSQL database `lc_beenachi_connect` as `lc_beenachi_app`. A transaction was opened, `SET TRANSACTION READ ONLY` was executed before querying only `current_database()`, `current_user`, and `current_setting('transaction_read_only')`, which returned `on`; the transaction was explicitly rolled back. No PostgreSQL migration, seeder, DDL, insert, update, or delete was executed. The temporary verification script was outside the repository and removed.

## API, authorization, privacy, and filters

President/Admin, Secretary, and Treasurer each received all five keys because they satisfy both `reports.view` and `finance.club.view`. Normal Member, inactive membership, unauthenticated access, and another-club access fail closed. The live API returned 403 for Normal Member and cross-club requests.

Club scoping is applied before report filtering. Date ranges are inclusive, limited to 366 days, and use charge due date, payment date, or receipt date as contracted. Key-specific category, lifecycle, payment status/mode, member search, charge search, page, and capped per-page filters are supported. Irrelevant and unknown filters return 422. Ordering uses source date/name plus stable ID tie-breakers.

Rows exclude phone, email, address, birth and family details, authentication data, banking and UPI credentials, idempotency keys, request hashes, internal notes, void reasons, actor IDs, cancellation reasons, assignment payloads, audit payloads, and lock metadata.

## Frontend and browser verification

The existing Reports UI loads backend catalog definitions, uses stable keys, sends only definition-approved filters, renders backend summaries and rows, and never calculates authoritative money. Before catalog or report requests it clears prior output and selected report state where appropriate, then enters loading. HTTP, validation, forbidden, and network failures retain cleared output. No export control exists.

Using the live external SQLite environment and the existing manually controlled servers:

- President loaded Collections Summary and displayed exact `271002 / 143343 / 127659` paise, INR, `1000` voided paise, and `1 / 2 / 1` member states.
- Secretary and Treasurer each loaded all five financial catalog entries.
- Switching from populated President output to Normal Member removed Reports navigation, catalog, summary, rows, pagination, and the `271002` value.
- Payment Register displayed recorded and voided totals, receipt stable IDs, page 1 then page 2 of 12 rows, and the unique sentinel.
- The shared Reports network-failure and recovery path remains the same `clearOutput()` implementation already browser-proven in 3GM; 3GW source and regression tests verify financial output uses that path. The user-owned backend process was not stopped or restarted during this run.
- At 1440px, 900px, and 390px, document scroll widths were respectively 1425, 885, and 375 pixels, below the viewport widths. Catalog, filters, summaries, rows, and pagination remained reachable.
- No export, PDF, CSV, or Excel control appeared.

## Lifecycle-safe regression updates

The 3GK and 3GM tests contained historical assertions that required `collections-summary` to remain absent and required an exact four-key catalog forever. Those assertions would fail after an authorized later activation. They were retained as lifecycle-safe tests: before activation they still require the historical disconnected behavior; after the fixed financial catalog exists they verify the authorized activated behavior. The 3GM stale-output clearing assertion remains unchanged. No unrelated test behavior was changed.

## Decision

PASS. Financial Reports is fixed-key, Finance-provenance-backed, integer-paise accurate, INR-only, void-aware, receipt-stable, role-safe, club-scoped, privacy-minimized, filter-validated, paginated, browser-verified, responsive, and read-only. No unresolved 3GW-critical defect remains.

GO - 3GX may open after focused and relevant regressions, production build, changed-file audit, commit/push, remote match, tracking 0/0, and a clean tree.
