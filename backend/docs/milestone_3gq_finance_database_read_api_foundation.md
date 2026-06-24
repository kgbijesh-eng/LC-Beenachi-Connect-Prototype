# Milestone 3GQ - Finance Database and Read API Foundation

Base: verified 3GP commit `1a81e959ba5bee963bf97baae7b0d9f6bd79c089`.

3GQ adds the isolated collection schema, controlled models, approved Finance permissions, club/own GET routes, exact paise summaries, obligation/payment/receipt reads, filters, stable IDs, pagination, and privacy tests. It adds no Finance mutation route.

Tables are `club_charges`, `member_charges`, `member_payments`, `payment_allocations`, and `payment_receipts`. Foreign keys use restrictive deletion, receipt/payment and assignment identities are unique, idempotency is club-scoped unique, and all monetary columns are unsigned integer paise. No new table uses decimal or float money.

President/Admin, Secretary, and Treasurer have club reads; Normal Member has own reads only. The management payment representation may include internal note, actor and idempotency metadata. Self responses omit those management-only fields.

Summary totals are backend-calculated from MemberCharges and non-voided allocations. Voided payments remain readable but do not contribute to paid/outstanding calculations. Duplicate titles and member names do not affect stable-ID ownership. Cross-club nested IDs return no data.

Financial Reports and `collections-summary` remain disabled. Legacy `/finance/reports`, balance-sheet and PDF functionality is not expanded or presented as part of this activation.

GO - 3GR may implement the exact approved mutation workflows only after tests, commit, push, remote match, and clean tracking.
