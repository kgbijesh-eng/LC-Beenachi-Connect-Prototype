# Milestone 3AR — Payment Recording Frontend Narrow Implementation

## Status

Implemented.

## Purpose

Milestone 3AR connects the existing frontend Record Member Payment screen to the already-approved backend payment recording API.

This milestone is intentionally narrow. It does not create new backend payment behavior. It only replaces the dummy payment UI with a frontend form that submits to the existing API.

## Allowed Files

- app/page.tsx
- backend/docs/milestone_3ar_payment_recording_frontend_narrow_implementation.md
- backend/tests/Feature/Milestone3arPaymentRecordingFrontendNarrowImplementationTest.php

## Backend API Used

Payment recording uses the existing route:

POST /api/v1/clubs/{club}/members/{member}/payments

The frontend obtains the local role token through the existing local login flow, resolves the active club from /auth/me, loads real backend members from /clubs/{club}/members, and submits payment for the selected backend member ID.

## Submitted Fields

The frontend submits only the backend-approved payment fields:

- amount
- payment_date
- payment_mode
- reference_number
- notes

## Explicitly Not Submitted

The frontend does not submit backend-controlled or prohibited fields:

- club_id
- member_id
- received_by_user_id
- ledger_entry_id
- status
- cancelled_at
- cancelled_by_user_id
- cancellation_reason
- receipt_number
- receipt_id

## Role Behavior

Payment recording controls are available only for authorized PST finance roles:

- President
- Secretary
- Treasurer

Member role is blocked from payment recording controls and can only view member-facing dues information.

Secretary now has a Finance navigation entry so the authorized payment screen is reachable from the UI.

## Frontend Behavior

The payment recording screen now:

- Logs in with the matching local PST role account.
- Loads the active club from /auth/me.
- Loads active backend members from the club member API.
- Allows selecting a real backend member.
- Loads the selected member finance summary.
- Submits payment to the existing backend payment recording API.
- Shows loading, success, and backend failure states.
- Shows validation or authorization failure messages returned by the backend.
- Refreshes the selected member finance summary after successful backend payment recording.

## Source of Truth

The backend remains the source of truth for:

- payment creation
- ledger credit creation
- payment status
- received_by_user_id
- ledger_entry_id
- member balance calculation

The frontend does not locally mutate ledger entries or locally recalculate balance as authoritative truth.

## Explicitly Out of Scope

This milestone does not implement:

- receipt generation frontend
- receipt list implementation
- receipt PDF download frontend
- backend route/controller/service/request/resource/model changes
- migrations or seeders
- local ledger mutation from frontend
- special collection allocation workflow
- fellowship allocation workflow
- reminders
- finance reports
- Excel import

## Validation

Frontend build passed after app/page.tsx implementation.

Backend guard tests verify that the frontend uses the existing payment API, exposes authorized PST role access, submits only the allowed request fields, blocks member role controls, and does not include the removed dummy receipt preview/payment ledger button inside the Record Member Payment screen.
