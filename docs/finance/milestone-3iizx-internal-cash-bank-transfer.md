# Milestone 3IIZX — Internal Cash/Bank Transfer

Authorized Presidents/Admins and Treasurers can move INR between two distinct active same-club asset accounts classified `CASH_AND_BANK`. Secretary and Member roles do not receive `finance.club.transfers.create`.

The durable `ClubInternalTransfer` source stores public identity, date, source, destination, one approved fund, integer-paise amount, description/reference, tenant-scoped idempotency identity, creator and resulting journal. Posted sources are immutable and cannot be deleted. There is no reversal, PATCH, or DELETE surface in this milestone.

DR-012 is implemented as one atomic canonical journal: debit destination and credit source for the same amount and fund. The existing posting engine enforces INR, active same-club accounts, open period, per-fund balance and immutable posting. The workflow does not check source balance and creates no income, expense, liability, receivable, equity, cross-fund, cross-club, external-recipient or multi-currency effect.

Club-row locking plus database uniqueness on `(club_id, idempotency_key)` serializes retries. Exact canonical replay returns the original transfer; changed replay conflicts. Source creation, journal posting and source-to-journal binding share one database transaction, so failure leaves no partial source or journal.

List and detail are tenant-scoped, deterministic and read-only. Public IDs are exposed; internal numeric IDs are not. The existing Club Account Statement consumes the journal naturally: source cash/bank decreases, destination cash/bank increases, overall net assets and economic fund ownership are unchanged, and the ledger snapshot fingerprint changes.
