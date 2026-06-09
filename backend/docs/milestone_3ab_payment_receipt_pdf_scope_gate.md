# Milestone 3AB — Payment Receipt PDF Scope Gate

## Status

Milestone 3AB is a docs/tests-only scope gate for future payment receipt PDF capability.

This milestone does not implement PDF generation.

## Context

Milestone 3AA closed the finance module backend foundation from Milestone 3A through Milestone 3Z.

The current backend foundation already supports:

- Member account ledger foundation
- Member balance calculation
- Protected member balance read API
- Payment recording database foundation
- Payment recording service
- Payment recording API
- Receipt generation service
- Receipt API

The next natural finance capability is receipt PDF generation, but it must not be implemented until the PDF scope, fields, access rules, storage model, and blocked channels are clearly defined.

## Scope gate purpose

The purpose of this scope gate is to define the boundary for a future payment receipt PDF implementation milestone.

Milestone 3AB only decides what a future PDF receipt feature may include and what it must not include.

## Future PDF receipt purpose

A future payment receipt PDF should provide a printable and downloadable proof of an already issued receipt.

The PDF must represent receipt data already created by the backend receipt generation service and exposed through the receipt API.

The PDF must not create payments, alter ledger entries, generate duplicate receipts, correct receipts, cancel receipts, reverse payments, or reissue receipts.

## Future PDF receipt fields

A future receipt PDF may include:

- Club name
- Club identifier
- Receipt number
- Receipt date
- Financial year
- Member name
- Member code
- Payment amount
- Payment mode
- Payment date
- Payment reference number when available
- Payment note when available
- Receipt status
- Issued by user name or identifier
- System-generated footer
- Non-editable receipt disclaimer

## Future PDF access rules

A future receipt PDF implementation must follow these access rules:

- President/Admin may generate or download receipt PDFs for members in their club
- Secretary may generate or download receipt PDFs when permission allows
- Treasurer may generate or download receipt PDFs when permission allows
- Normal Member may download only their own already-issued receipt PDFs
- Cross-club receipt PDF access must fail closed
- Unauthenticated users must not access receipt PDFs

## Storage decision boundary

A future milestone must explicitly decide whether PDF receipts are:

- Generated on demand without permanent file storage
- Stored after generation
- Regenerated from receipt data when requested

Milestone 3AB does not decide or implement storage.

No storage disk, file path, public URL, signed URL, or media table behavior is authorized in this milestone.

## Future implementation authorization requirements

Before implementation, a later milestone must authorize:

- PDF generation library or package choice
- PDF rendering method
- PDF template location
- Controller or service boundary
- Route boundary
- Download response behavior
- Storage or on-demand generation decision
- Permission and privacy checks
- Test coverage for role access and cross-club failure
- No duplicate receipt generation behavior

## Blocked scopes

Milestone 3AB blocks all implementation work.

Not allowed in this milestone:

- No PDF implementation
- No PDF package or library installation
- No PDF template
- No PDF route
- No PDF controller method
- No receipt controller change
- No receipt service change
- No payment service change
- No model change
- No migration change
- No storage change
- No public file URL
- No signed download URL
- No frontend receipt download button
- No WhatsApp sharing
- No SMS sharing
- No email sharing
- No reports
- No Excel import
- No receipt cancellation
- No payment reversal
- No payment correction
- No receipt reissue workflow
- No special collection allocation workflow
- No fellowship payment workflow

## Future milestone direction

The next milestone after this scope gate may be:

Milestone 3AC — Payment Receipt PDF Implementation Authorization

Milestone 3AC should remain docs/tests-only and authorize a narrow future implementation.

Actual PDF generation should not happen until a later implementation milestone after authorization.

## 3AB boundary confirmation

Milestone 3AB changes only documentation and validation tests.

It does not change production finance behavior.