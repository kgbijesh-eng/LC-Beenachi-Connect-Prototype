# Milestone 3GR - Finance Collections and Receipt Workflows

Base: verified 3GQ commit `d590768ff55edcda95330abd225e64adcc472b12`.

3GR implements the approved charge and payment mutation routes through `FinanceWriteService`.

Draft charges can be created and edited by President/Admin, Secretary, and Treasurer using stable active same-club member IDs. Issue creates immutable MemberCharges transactionally. Close requires every obligation paid. Draft cancellation is allowed; Issued cancellation is blocked after any recorded allocation. Terminal and stale transitions return conflict and never write success audit.

Payment recording locks all target obligations, validates exact member/club ownership and integer-paise reconciliation, requires the controlled mode/reference rules and a club-scoped UUID idempotency key, then creates MemberPayment, allocations, an immutable sequential receipt, and audit in one transaction. Identical replay returns the original result; changed payload conflicts.

President/Admin and Treasurer may void. Secretary and Normal Member cannot. Void keeps payment, allocations, and receipt, marks payment/receipt voided, restores computed outstanding through non-voided allocation rules, requires a reason/version, and supports a same-member replacement link.

There is no delete, refund, export, PDF activation, gateway, unallocated credit, JavaScript money calculation, frontend change, or Financial Reports activation.

GO - 3GS may begin isolated database preparation only after 3GR tests, regressions, commit, push, remote match, and clean tracking.
