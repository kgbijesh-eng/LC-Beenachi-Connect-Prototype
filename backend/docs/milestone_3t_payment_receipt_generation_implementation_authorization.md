\# Milestone 3T — Payment Receipt Generation Implementation Authorization



\## Status



Milestone 3T status: IMPLEMENTATION AUTHORIZATION ONLY

Scope type: DOCS/TESTS-ONLY

Implementation status: NOT IMPLEMENTED IN 3T



\## Purpose



Milestone 3T authorizes a future narrow implementation for payment receipt generation after the Milestone 3S scope gate.



This milestone does not implement receipt generation. It only defines what a future implementation milestone may build and what must remain blocked.



\## Authorization decision



PAYMENT_RECEIPT_GENERATION_IMPLEMENTATION_AUTHORIZED_FOR_FUTURE_NARROW_SLICE

IMPLEMENTATION_NOT_PERFORMED_IN_3T

NEXT_IMPLEMENTATION_CANDIDATE_REQUIRES_SEPARATE_BRANCH



\## Future implementation candidate



The next implementation milestone may be:



Milestone 3U — Payment Receipt Generation Narrow Implementation



Milestone 3U, if opened, may implement only the backend receipt generation service and receipt number generation logic needed to create an official receipt for an already recorded posted payment.



\## Authorized future implementation boundaries



A future narrow implementation may add:



Receipt generation service

Receipt number generation logic

Payment-to-receipt creation workflow

Transaction-safe receipt creation

Tests for receipt generation success and failure cases

Tests for duplicate receipt prevention

Tests for receipt number uniqueness

Tests for club-scoped receipt numbering

Implementation documentation



\## Explicitly blocked in 3T



Milestone 3T itself must not add:



No receipt service

No receipt number generator

No PDF generation

No receipt route

No controller

No request class

No resource class

No payment service change

No payment API change

No frontend

No WhatsApp sending

No SMS sending

No email sending

No reports

No Excel import



\## Receipt generation guardrails for future implementation



The future receipt generation service must follow these rules:



Generate a receipt only for a successfully recorded posted payment.

Do not generate a receipt for failed, draft, cancelled, reversed, or incomplete payments.

Do not create a payment while generating a receipt.

Do not change the original payment amount, payment date, member, club, or payment mode.

Do not create additional ledger entries during receipt generation.

Do not treat receipt generation as ledger posting authority.

Do not allow receipt generation across clubs.

Do not allow normal members to generate receipts.

Do not generate duplicate official receipts for the same payment.



\## Receipt number generation guardrails



Future receipt number generation must be:



Unique within the club.

Sequential within a defined financial-year scope.

Transactional and concurrency-safe.

Generated only after the payment is accepted for receipt issue.

Never reused after issue.

Not manually editable by normal users.

Audit-friendly and human-readable.



Suggested future format remains only a design reference:



LCB/2026-27/RCP/0001



The exact format may be implemented later only if the future implementation milestone confirms it.



\## Payment-to-receipt relationship rules



Future implementation must preserve these relationship rules:



One receipt belongs to one club.

One receipt belongs to one payment.

One receipt is linked to the member connected to the payment.

A valid receipt must not exist without a valid payment reference.

A payment should expose its official receipt once generated.

A payment should not receive multiple active official receipts unless a later correction or reissue workflow is separately authorized.



\## Snapshot requirements for future receipt records



A future receipt should preserve the payment snapshot needed for audit, viewing, and later download.



Possible snapshot fields:



club name

member name

member code

payment amount

payment mode

payment date

recorded by

receipt number

receipt issued date

payment reference

notes



These are authorized only as future implementation design boundaries. 3T does not create or modify database fields.



\## Permission boundaries for future implementation



Future implementation must keep receipt generation restricted:



President/Admin may generate receipts for valid club payments.

Treasurer may generate receipts for valid club payments if granted finance permission.

Secretary access must be separately reviewed before generation authority is granted.

Normal members must not generate receipts.

Normal members may later view/download only their own receipts if a separate receipt read/download API is authorized.

Cross-club receipt access must be blocked.



\## Blocked until later separate authorization



The future 3U-style implementation must still not add these unless separately authorized:



Receipt PDF generation

Receipt download route

Receipt public API response expansion

Frontend receipt screen

Frontend receipt download button

WhatsApp receipt sending

SMS receipt sending

Email receipt sending

Receipt reports

Excel receipt import/export

Payment cancellation workflow

Payment reversal workflow

Receipt correction workflow

Receipt reissue workflow



\## Existing behaviour that must remain unchanged



Future implementation must preserve:



Existing payment recording API contract

Existing payment recording service authority

Existing member ledger source-of-truth behaviour

Existing ledger credit posting behaviour

Existing role-permission boundaries

Existing club scoping

Existing payment validation rules

Existing tests



\## 3T closure rule



Milestone 3T may close only if:



Only this authorization document is added.

Only the matching 3T authorization test is added.

Focused 3T tests pass.

Milestone3 chain passes.

Full backend suite passes.

No runtime receipt implementation is added.

No payment API behaviour changes.



\## 3T decision



PAYMENT_RECEIPT_GENERATION_IMPLEMENTATION_AUTHORIZATION_RECORDED

FUTURE_3U_NARROW_IMPLEMENTATION_CANDIDATE_DEFINED

NO_IMPLEMENTATION_DONE_IN_3T


