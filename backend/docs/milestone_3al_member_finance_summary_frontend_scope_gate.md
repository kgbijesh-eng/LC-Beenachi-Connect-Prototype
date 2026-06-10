# Milestone 3AL - Member Finance Summary Frontend Scope Gate

Status: Docs/tests-only frontend scope gate.

Decision marker:
MEMBER_FINANCE_SUMMARY_FRONTEND_SCOPE_GATE_DEFINED

Purpose:
Define the future frontend integration boundary for member-facing finance summary display.
Use the completed 3AI/3AJ backend finance summary read API without changing backend behavior in 3AL.
Prevent frontend scope drift into dashboards, reports, reminders, payment actions, receipt generation, Excel import, or finance workflows.

Frontend area under consideration:
Member-facing dashboard summary card.
Member-facing finance summary panel.
Existing member area where Current Dues and Last Payment can be shown clearly.

Future API endpoints allowed for frontend use:
GET /api/v1/clubs/{club}/finance/member-summary
GET /api/v1/clubs/{club}/members/{member}/finance-summary

Normal member frontend boundary:
Normal member should use only GET /api/v1/clubs/{club}/finance/member-summary for own finance summary.
Normal member must not use selected member finance summary route.
Normal member must see member-friendly wording only.
Normal member must not see internal ledger, debit, credit, accounting, voucher, or audit wording.

Allowed normal member fields:
Current Dues
Last Payment
Last Payment Date
Receipt Available
Receipt Status
Pending Items
Payment Status

PST frontend boundary:
President/Admin, Secretary, and Treasurer may later use selected member finance summary for same-club members.
PST selected member finance summary UI must stay read-only in this scope lane.
PST UI must not include payment recording, receipt generation, correction, reversal, cancellation, report, reminder, or Excel actions unless separately authorized.

Display wording rules:
Use Current Dues instead of outstanding balance or debit balance.
Use Last Payment instead of latest credit.
Use Receipt Available instead of receipt ledger status.
Use Pending Items only for user-facing dues or collection items.
Do not expose raw ledger source labels if they are not member-friendly.

Loading state rules:
Show a simple loading state while finance summary is being fetched.
Do not block the whole member directory or whole dashboard if finance summary loading fails.

Empty state rules:
If no dues are pending, show No current dues.
If no last payment exists, show No payment recorded yet.
If no receipt exists, show Receipt not available.

Error state rules:
If the finance summary request fails, show Finance summary unavailable.
Do not expose technical API errors to normal members.
Do not show cross-club or permission failure details to normal members.

Privacy rules:
Normal members must see only their own finance summary.
PST users must see only same-club selected member finance summary.
Cross-club data must never be displayed.
Unauthenticated users must not see finance summary data.

3AL allowed files:
docs/milestone_3al_member_finance_summary_frontend_scope_gate.md
tests/Feature/Milestone3alMemberFinanceSummaryFrontendScopeGateTest.php

Next recommended milestone:
Milestone 3AM - Member Finance Summary Frontend Narrow Implementation

3AM candidate implementation boundary:
Connect existing frontend member-facing area to GET /api/v1/clubs/{club}/finance/member-summary.
Display Current Dues, Last Payment, Receipt Available, Payment Status, and Pending Items.
Add loading, empty, and safe error states.
Do not add payment actions, receipt generation actions, reports, reminders, Excel import, or new backend routes.

3AL explicit non-scope:
No frontend change
No API change
No controller change
No service change
No resource change
No request change
No model change
No migration change
No seeder change
No dashboard implementation
No report implementation
No reminder implementation
No Excel implementation
No PDF implementation
No payment action
No receipt generation action
No ledger mutation
No correction workflow
No reversal workflow
No cancellation workflow
No special collection workflow
No fellowship allocation workflow

Closure rule:
3AL only defines frontend scope. Any UI implementation must wait for 3AM or a later authorized implementation milestone.
