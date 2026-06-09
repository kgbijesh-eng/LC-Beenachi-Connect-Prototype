# Milestone 3AC — Payment Receipt PDF Implementation Authorization

## Status

Milestone 3AC is a docs/tests-only implementation authorization for a future narrow payment receipt PDF implementation.

This milestone authorizes a future implementation milestone only.

Milestone 3AC does not implement PDF generation.

## Context

Milestone 3AB defined the Payment Receipt PDF Scope Gate.

The finance foundation already includes:

- Payment recording database foundation
- Payment recording service
- Payment recording API
- Receipt generation service
- Receipt API
- Receipt privacy and cross-club protection rules

The next implementation milestone may add PDF receipt generation, but only inside the narrow boundaries authorized here.

## Authorization decision

Milestone 3AC authorizes opening a future narrow implementation milestone:

Milestone 3AD — Payment Receipt PDF Narrow Implementation

Milestone 3AD may implement backend-only receipt PDF generation and download behavior.

Milestone 3AC itself remains docs/tests-only.

## Future package authorization

Milestone 3AD may choose one approved PDF rendering approach:

- Install and use `barryvdh/laravel-dompdf`

The package must be used only for server-side receipt PDF rendering.

No other PDF package, browser automation package, image rendering package, or external PDF service is authorized unless a later milestone changes the decision.

Milestone 3AC does not install the package.

## Future approved file boundary

Milestone 3AD may add or modify only files needed for narrow backend PDF support.

Allowed future file categories:

- Composer dependency files, only if the approved PDF package is installed
- A receipt PDF controller or a narrow receipt PDF method
- A receipt PDF service
- A receipt PDF Blade template
- A receipt PDF feature test
- A short implementation document
- Route registration for the approved PDF endpoint only

Milestone 3AD must not modify unrelated finance, member, auth, ledger, report, reminder, Excel, fellowship, or frontend files.

## Future approved route boundary

Milestone 3AD may add one protected backend route for downloading an already-issued receipt PDF.

Approved future route shape:

GET /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf

The route must:

- Require authentication
- Require club access
- Reuse existing receipt ownership and visibility rules
- Return a PDF download response
- Fail closed for cross-club access
- Fail closed for unauthenticated users
- Not generate duplicate receipts
- Not create payments
- Not mutate ledger entries

No public PDF route is authorized.

No signed public URL route is authorized.

No email, WhatsApp, SMS, or sharing route is authorized.

## Future controller and service boundary

Milestone 3AD may add narrow backend code that:

- Finds an already-issued receipt for the selected payment
- Verifies the authenticated user's permission to view/download the receipt
- Renders the receipt data into a PDF
- Returns the PDF as a download response

The future implementation must not:

- Record payments
- Generate duplicate receipts
- Change receipt numbers
- Cancel receipts
- Reverse payments
- Correct payments
- Reissue receipts
- Modify ledger entries
- Modify member balances

## Future PDF template boundary

Milestone 3AD may add one Blade template for the receipt PDF.

The template may display:

- Club name
- Receipt number
- Receipt date
- Financial year
- Member name
- Member code
- Payment amount
- Payment mode
- Payment date
- Payment reference number when available
- Receipt status
- Issued by details when available
- System-generated footer
- Non-editable receipt disclaimer

The template must not include editable form fields or frontend interactivity.

## Future storage decision

Milestone 3AD should generate PDFs on demand from existing receipt data.

Permanent PDF file storage is not authorized in 3AD.

The future implementation must not add:

- Storage disk configuration
- Public file URLs
- Signed file URLs
- Media table behavior
- Stored PDF file paths
- Background PDF generation jobs

A later milestone may revisit stored PDFs if required.

## Future access rules

The future implementation must enforce these access rules:

- President/Admin may download receipt PDFs for members in their club
- Secretary may download receipt PDFs when permission allows
- Treasurer may download receipt PDFs when permission allows
- Normal Member may download only their own already-issued receipt PDFs
- Cross-club access must fail closed
- Unauthenticated access must fail closed

## Future test requirements

Milestone 3AD must include tests proving:

- Authorized President/Admin can download a PDF receipt
- Authorized Treasurer or Secretary access follows existing permission rules
- Normal Member can download only their own receipt PDF
- Normal Member cannot download another member's receipt PDF
- Cross-club receipt PDF download fails closed
- Unauthenticated receipt PDF download fails closed
- PDF route does not create duplicate receipts
- PDF route does not create payments
- PDF route does not mutate ledger entries
- PDF route does not expose public, sharing, report, or Excel routes

## Blocked scopes

Milestone 3AC blocks all implementation work.

Not allowed in this milestone:

- No PDF package installation
- No PDF implementation
- No PDF template
- No PDF route
- No controller change
- No service change
- No model change
- No migration change
- No storage change
- No frontend
- No WhatsApp sharing
- No SMS sharing
- No email sharing
- No reports
- No Excel import
- No cancellation workflow
- No reversal workflow
- No correction workflow
- No receipt reissue workflow
- No special collection allocation workflow
- No fellowship payment workflow

## Boundary confirmation

Milestone 3AC changes only documentation and validation tests.

It authorizes a future narrow implementation but does not change production finance behavior.