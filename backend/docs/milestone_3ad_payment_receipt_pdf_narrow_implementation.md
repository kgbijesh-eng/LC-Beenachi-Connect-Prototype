# Milestone 3AD — Payment Receipt PDF Narrow Implementation

## Status

Milestone 3AD implements narrow backend-only payment receipt PDF download support.

## Implemented scope

Milestone 3AD adds:

- Approved PDF package: barryvdh/laravel-dompdf
- Backend PDF download route for already-issued receipts
- PaymentReceiptPdfController
- PaymentReceiptPdfService
- One receipt PDF Blade template
- Focused PDF access and non-mutation tests

## Route added

GET /api/v1/clubs/{club}/members/{member}/payments/{payment}/receipt/pdf

## Rules

The PDF route:

- Requires authentication
- Requires club access
- Uses already-issued receipt data
- Generates PDF on demand
- Does not permanently store PDF files
- Does not create payments
- Does not create duplicate receipts
- Does not mutate ledger entries
- Fails closed for cross-club access
- Fails closed for unauthenticated access
- Allows normal members to download only their own issued receipt PDF

## Not implemented

Milestone 3AD does not add:

- Frontend receipt download button
- Stored PDF files
- Public PDF URLs
- Signed PDF URLs
- WhatsApp sharing
- SMS sharing
- Email sharing
- Reports
- Excel import/export
- Receipt cancellation
- Receipt correction
- Receipt reissue
- Payment reversal
- Special collection allocation workflow
- Fellowship payment workflow