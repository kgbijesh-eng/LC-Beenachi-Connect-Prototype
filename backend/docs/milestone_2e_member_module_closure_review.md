# Milestone 2E — Member Module Closure Review & Next Feature Decision

## Status

MEMBER_MODULE_FOUNDATION_CLOSED

This milestone closes the current member master foundation after Milestones 2A, 2B, 2C, and 2D.

Milestone 2E is a closure and decision checkpoint only. It does not add new backend routes, frontend screens, database tables, business modules, or permission behavior.

## Completed Member Module Scope

The member module foundation now includes:

- Member database foundation.
- Member model and club relationship foundation.
- Protected member list route.
- Protected member detail route.
- Protected member create route.
- Protected member update route.
- Role-based permission protection.
- Local test users for President/Admin, Secretary, Treasurer, and Member.
- API smoke tests using real seeded local role users.
- Frontend validation note for the first backend-connected member screen.

## Current Verified Member Permissions

President/Admin:

- Can view members.
- Can create members.
- Can update members.

Secretary:

- Can view members.
- Can create members.
- Can update members.

Treasurer:

- Can view members.
- Cannot create members.
- Cannot update members.

Normal Member:

- Can view permitted member information.
- Cannot create members.
- Cannot update members.

## Current Member Module Limitations

The following items are intentionally not included in the current member module foundation:

- No member delete route.
- No Excel import.
- No member account ledger.
- No dues tracking.
- No payments or receipts.
- No reports.
- No reminders.
- No real frontend auth redesign.
- No production-ready role switching UI.
- No advanced member profile fields beyond the current foundation.
- No public QR profile card.
- No NFC or digital card workflow.

These are future modules and should not be mixed into the closed member module foundation.

## Closure Decision

The member module foundation is ready to close at this level.

The current foundation is sufficient for moving to the next business-critical backend module without expanding member CRUD further.

## Next Recommended Module

The next recommended backend module is:

Milestone 3A — Member Account Ledger and Dues Foundation Scope Gate

Reason:

The club app’s next major value depends on tracking amounts due from members, including:

- Monthly dues.
- Fellowship amounts.
- Membership fees.
- Project share.
- Fines.
- Special collections.
- Other member-level dues.

This should be handled through a proper member account ledger instead of adding loose payment fields directly to the member table.

## Milestone 3A Boundary

Milestone 3A should be docs/tests-only first.

Allowed in Milestone 3A:

- Define ledger scope.
- Define debit and credit rules.
- Define member balance rules.
- Define role permissions for ledger visibility.
- Define privacy rules for normal members.
- Define future API route plan.
- Define future table plan.

Not allowed in Milestone 3A:

- No ledger table migration yet.
- No payment routes yet.
- No receipt routes yet.
- No frontend changes.
- No reminder sending.
- No reports.
- No Excel import.
- No fellowship automation changes.

## Scope Protection

Milestone 2E confirms that the member module should not continue expanding by default.

Further member work should only be opened if there is a specific need such as:

- Member delete/archive decision.
- Member import decision.
- Advanced profile fields.
- Frontend role-login testing convenience.

Otherwise, the project should move forward to the finance ledger and dues foundation.
