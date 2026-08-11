# Milestone 3GS - Finance Isolated Environment and Frontend Integration

Base: verified 3GR commit `452b8fe9be931cce5cb18432e45458792a66551a`.

The genuine configuration identifies PostgreSQL database `lc_beenachi_connect` at `127.0.0.1:5432`. It remained read-only and was not migrated or mutated. All Finance mutation/API/browser work used `%TEMP%\lc_beenachi_3gs_finance_isolated.sqlite`.

Seeders were inspected before isolated execution. The disposable database was recreated, migrated, seeded, and populated with President/Admin, Secretary, Treasurer, Normal Member, duplicate member names, three synthetic members, duplicate charge titles, a Draft charge, two Issued charges, partial and full allocations, cash/UPI/bank-transfer payments, a voided payment, stable receipts, and sentinel `FINANCE-SENTINEL-DUES`.

Predetermined Normal Member totals were 150000 assessed paise, 40000 paid paise, and 110000 outstanding paise. API alignment returned those exact values for both management and own routes, loaded the sentinel, created and issued isolated charge ID 4, and never touched genuine data.

The fixture uncovered and corrected one focused backend response defect: newly recorded payments now refresh database defaults before response so `lock_version` is non-null.

`app/FinanceBackend.tsx` is a justified additional frontend file because the legacy `app/page.tsx` Finance prototype is more than ten thousand lines and contains unrelated Fellowship/accounting demonstrations. The component isolates the approved collection domain and is mounted by `app/page.tsx` on the central Finance route and selected member profile.

Browser smoke confirmed the sentinel, exact initial totals of ₹1500.00/₹400.00/₹1100.00, stable duplicate-member selection, in-app payment confirmation, backend receipt creation, refreshed ₹401.00 paid and ₹1099.00 outstanding totals, Member role clearing, own read-only access, and no horizontal overflow at 390 pixels. A stopped backend cleared previously displayed Finance totals; the isolated backend was then restarted successfully.

All operational collection, dues, payment, receipt, outstanding and collection-summary navigation screens now route to the backend component instead of legacy demonstration arrays.

The frontend:

- authenticates the selected synthetic role and resolves route club/member IDs;
- shows only backend assessed, paid, outstanding, obligations, payments, and receipts;
- uses integer paise entry and performs no authoritative monetary aggregation;
- creates Draft charges and issues them by stable IDs;
- records payments against stable MemberCharge IDs with a generated idempotency key;
- uses an explicit in-app review/confirmation step;
- shows success only after backend payment/receipt confirmation;
- reloads summary, obligations, payments, and receipts after mutation;
- clears totals, rows, receipt, selection confirmation, and prior output at request start;
- shows explicit unavailable, forbidden, validation, conflict, empty, and success messages;
- gives Normal Member own read-only data with no create, issue, record, or void controls;
- clears all data on role/member remount;
- displays no Financial Reports, PDF, export, gateway, refund, or full-accounting claim.

GO - 3GT may open after Finance tests, production build, isolated browser smoke, commit/push, remote match, and clean tracking.
