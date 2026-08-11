# Milestone 3GT - Finance Browser, Role and Accuracy Verification

Base: verified 3GS commit `aacac26ce1071122e50804365366a8dd058779ab`.

All execution used `%TEMP%\lc_beenachi_3gs_finance_isolated.sqlite`; genuine PostgreSQL remained untouched.

## Role matrix

President/Admin, Secretary and Treasurer loaded club charges, member summaries, obligations, payments and receipts. Each can create/issue charges and record payments. President/Admin and Treasurer have confirmed void controls; Secretary has no void control and the API returns 403. Normal Member loaded only `/finance/me/*`, could not enumerate another member or see any management control. Unauthenticated, inactive and another-club access fail closed in executable tests.

## Exact accuracy

The predetermined member fixture began at 150000 assessed, 40000 paid and 110000 outstanding paise. Browser display was exactly ₹1500.00, ₹400.00 and ₹1100.00. A confirmed 100-paise cash payment to stable MemberCharge ID 1 produced a backend receipt and reloaded exact ₹401.00 paid and ₹1099.00 outstanding values.

Backend tests cover single and multi-allocation equality, partial/full payment, zero outstanding, close, cancellation restriction, void restoration, receipt retention, duplicate idempotent replay, changed-payload conflict, stale version, over-allocation, cross-member and cross-club denial, stable duplicate titles/names, and no partial audit/rows on failure.

## Navigation, privacy and failure

All operational dues, collection, payment, receipt, outstanding and collection-summary routes render `FinanceBackend`. The selected member label includes member code and stable ID, and obligation/payment/receipt rows expose stable IDs. Role downgrade immediately removed totals, receipt and management controls.

The UI displays no bank/UPI credentials, addresses, birth data, private notes to members, or audit payloads. It performs no authoritative total calculation and reloads backend data after every mutation.

With the known backend stopped, previously visible Finance totals disappeared and unavailable states replaced them. The same isolated backend restarted successfully. Failed requests did not show success. Validation and conflict messages are distinct. In-app confirmation exists for payment and void actions.

At 1440, 900 and 390 pixels, document scroll width remained within viewport width. Forms, totals, charge cards, obligation IDs, payment history, receipt text, confirmation and error states remained readable.

## Focused correction

3GT added the missing role-safe charge close/cancel and President/Treasurer payment-void controls. Each uses the persisted `lock_version`, explicit confirmation/reason, existing backend route, and authoritative refresh. No new financial calculation or Reports feature was introduced.

PASS - no unresolved Finance browser, role, privacy or accuracy defect. 3GU may open after tests/build, commit/push, remote match and clean tracking.
