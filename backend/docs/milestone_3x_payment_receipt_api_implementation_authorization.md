# Milestone 3X - Payment Receipt API Implementation Authorization

Status: DOCS_TESTS_ONLY_IMPLEMENTATION_AUTHORIZATION

Decision:
Milestone 3X authorizes a future narrow backend receipt API implementation milestone. This milestone does not implement routes, controllers, requests, resources, services, PDF output, frontend screens, messaging, reports, Excel export, or cancellation workflows.

Purpose:
Milestone 3X converts the Milestone 3W receipt API scope gate into a precise future implementation authorization. It defines the exact future route boundary, file boundary, controller guardrails, request validation guardrails, resource response boundary, permission rules, and blocked scope before any API code is written.

Future implementation candidate:
Milestone 3Y - Payment Receipt API Narrow Implementation

Authorized future route boundary:
POST /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt
GET /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt

Future route rules:
The POST route may generate receipt metadata for an already posted payment. The GET route may return existing generated receipt metadata. The GET route must not auto-generate a receipt. Both routes must be authenticated and club-scoped. Both routes must fail closed for wrong club, wrong member, wrong payment relationship, missing permission, and cross-member access.

Authorized future file boundary:
app/Http/Controllers/Api/V1/Finance/ReceiptController.php
app/Http/Requests/Finance/GenerateReceiptRequest.php
app/Http/Resources/Finance/ReceiptResource.php
docs/milestone_3y_payment_receipt_api_narrow_implementation.md
tests/Feature/Milestone3yPaymentReceiptApiNarrowImplementationTest.php

Controller guardrails:
ReceiptController may delegate receipt generation to ReceiptGenerationService. ReceiptController must not duplicate receipt number logic. ReceiptController must not mutate ledger entries. ReceiptController must not mutate payment amount, dues, balance, finance reports, or receipt numbering outside the service. ReceiptController must not send WhatsApp, SMS, or email. ReceiptController must not create PDF output.

Request guardrails:
GenerateReceiptRequest may authorize authenticated club-scoped receipt generation. It must not accept backend controlled fields such as receipt_number, receipt_status, generated_by, generated_at, club_id, member_id, payment_id, amount, or payment_date from the client. The selected club, member, and payment must come from the route and server-side validation.

Resource guardrails:
ReceiptResource may return safe receipt metadata only. It must not expose raw audit log payloads, internal ledger mutation internals, sensitive system metadata, PDF binary data, messaging payloads, report data, or Excel import/export data.

Allowed future response fields:
receipt_id, receipt_number, club_id, member_id, member_name, payment_id, amount, payment_date, payment_method, receipt_status, generated_at, generated_by.

Permission rules:
President/Admin may generate and view receipts for all members in the club. Treasurer may generate and view receipts for all members in the club. Secretary may generate and view receipts only if explicitly granted payment or receipt permission. Normal Member must not generate receipts. Normal Member may view only own generated receipt metadata if the future implementation explicitly supports own receipt viewing. Normal Member must not view other members receipts.

Receipt generation authority:
ReceiptGenerationService remains the receipt generation authority. The future API layer must only expose the service safely through HTTP. The API layer must not become the receipt business-logic authority.

Blocked scope for 3X:
No route. No controller. No request class. No resource class. No service change. No PDF. No frontend. No WhatsApp/SMS/email. No reports. No Excel. No cancellation/reversal/correction/reissue workflow.

Blocked future scope unless separately authorized:
Receipt PDF download. Receipt print format. WhatsApp delivery. SMS delivery. Email delivery. Receipt reports. Excel export. Receipt cancellation. Receipt reversal. Receipt correction. Receipt reissue. Special collection allocation changes. Fellowship payment workflow changes.

Allowed files in 3X:
backend/docs/milestone_3x_payment_receipt_api_implementation_authorization.md
backend/tests/Feature/Milestone3xPaymentReceiptApiImplementationAuthorizationTest.php

Closure rule:
Milestone 3X can close only when the authorization document exists, the authorization test exists, focused 3X tests pass, full backend tests pass, only the two 3X files are changed, the branch is pushed, the remote head matches local HEAD, and tracking status is clean.
