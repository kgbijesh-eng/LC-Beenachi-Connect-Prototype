# Milestone 3AI — Outstanding Dues and Member Finance Summary Read API Narrow Implementation

Implemented narrow read-only finance summary API.

Decision marker:
OUTSTANDING_DUES_FINANCE_SUMMARY_READ_API_NARROW_IMPLEMENTATION_ADDED

Implemented routes:
GET /api/v1/clubs/{club}/finance/member-summary
GET /api/v1/clubs/{club}/members/{member}/finance-summary

Implemented files:
app/Services/Finance/MemberFinanceSummaryService.php
app/Http/Controllers/Api/V1/Finance/MemberFinanceSummaryController.php
app/Http/Resources/Finance/MemberFinanceSummaryResource.php
routes/api.php
tests/Feature/Milestone3aiOutstandingDuesFinanceSummaryReadApiNarrowImplementationTest.php
docs/milestone_3ai_outstanding_dues_finance_summary_read_api_narrow_implementation.md

Behavior:
The API reads existing posted member ledger entries, posted payments, and issued receipts only.

Allowed query parameters:
include_pending_items
include_last_payment
include_receipt_status

Returned fields include:
member_id
member_code
member_name
current_dues_amount
current_dues_label
last_payment_amount
last_payment_date
pending_items
receipt_available
payment_status
generated_at
source_type
source_label
amount
due_date
status

Member-facing labels:
Current Dues
Pending Items
Last Payment
Receipt Available
Paid
Partly Paid
Pending
Advance

Access rules:
Unauthenticated access fails closed.
Normal member can read only own finance summary through /finance/member-summary.
Normal member cannot read another member finance summary.
Treasurer can read same-club selected member finance summaries.
Secretary can read same-club selected member finance summaries.
President/Admin can read same-club selected member finance summaries.
Cross-club member finance access fails closed.
User without active club membership fails closed through existing club.view middleware.

Explicit non-scope:
No frontend
No dashboard
No reports
No reminders
No Excel
No PDF
No payment mutation
No receipt mutation
No ledger mutation
No correction workflow
No reversal workflow
No cancellation workflow
No special collection workflow
No fellowship allocation workflow
No route using POST, PATCH, PUT, or DELETE

The service performs read-only summary calculation only. It does not create, update, delete, allocate, reverse, cancel, generate receipts, generate PDFs, send reminders, export files, or mutate finance records.
