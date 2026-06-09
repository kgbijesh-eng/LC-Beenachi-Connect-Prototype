# Milestone 3W - Payment Receipt API Scope Gate

Status: SCOPE_GATE_ONLY

Purpose:
Milestone 3W defines the future backend API boundary for payment receipt generation and receipt viewing. This milestone does not implement the API. It only records the approved scope, permission rules, response boundaries, and blocked items before any receipt API route, controller, request, or resource work is opened.

Background:
Milestone 3U introduced the backend-only ReceiptGenerationService. Milestone 3V validated and closed that receipt generation service. Milestone 3W is the next scope gate before exposing that service through HTTP APIs.

Future receipt generation endpoint:
POST /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt
Generate a receipt for an already recorded posted payment.

Generation boundary:
Accept only an existing club-scoped member payment. Reject payments outside the selected club. Reject payments that do not belong to the selected member. Reject non-posted, invalid, reversed, cancelled, or correction-related payments. Use ReceiptGenerationService as the receipt generation authority. Return receipt metadata only. Do not generate PDF output. Do not send WhatsApp, SMS, or email. Do not mutate payment amount, ledger amount, dues, member balance, or finance reports.

Future receipt view endpoint:
GET /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt
View receipt metadata for an already generated receipt.

View boundary:
Return receipt metadata for the selected club, member, and payment only. Return not found when no receipt exists. Do not auto-generate receipt from the view endpoint. Do not expose other members payment or receipt details. Do not return PDF binary data. Do not return internal audit-only implementation details.

Permission rules:
President/Admin may generate receipts. Treasurer may generate receipts. Secretary may generate receipts only if explicitly granted finance/payment receipt permission. Normal Member must not generate receipts.

View permission rules:
President/Admin may view receipts for all members in the club. Treasurer may view receipts for all members in the club. Secretary may view receipts only if explicitly granted finance/payment receipt view permission. Normal Member may view only their own generated receipt metadata. Normal Member must not view other members receipts.

Fail-closed rules:
Unauthenticated request must fail. Wrong club request must fail. Wrong member/payment relationship must fail. Missing permission must fail. Normal Member cross-member access must fail.

Allowed response fields:
receipt_id, receipt_number, club_id, member_id, member_name, payment_id, amount, payment_date, payment_method, receipt_status, generated_at, generated_by.

Blocked response fields:
Other members payment details. Internal ledger mutation internals. Raw audit log payloads. Sensitive system metadata. PDF binary data. WhatsApp/SMS/email delivery payloads. Report data. Excel import/export data.

Blocked scope:
No route. No controller. No request class. No resource class. No service change. No PDF. No frontend. No WhatsApp/SMS/email. No reports. No Excel. No cancellation/reversal/correction/reissue workflow.

Allowed files:
backend/docs/milestone_3w_payment_receipt_api_scope_gate.md
backend/tests/Feature/Milestone3wPaymentReceiptApiScopeGateTest.php

Next milestone candidate:
Milestone 3X - Payment Receipt API Implementation Authorization. That milestone should still be docs/tests-only unless explicit implementation authorization is given later.
