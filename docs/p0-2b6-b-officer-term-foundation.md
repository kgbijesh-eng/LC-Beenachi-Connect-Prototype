# B6-B officer-term foundation

Slice A adds inert term storage, an explicit-time resolver and a read-only legacy candidate report. The baseline is `6e3ad4fe0bdcc1922de413c94ae7d0a2a14af72b`. Existing authorization remains independent of terms.

## Constraint design

PostgreSQL stores UTC instants as second-precision `timestamptz`. Both exclusion constraints compare `tstzrange(starts_at, least(ends_at, coalesce(ended_at, ends_at)), '[)')`. An early end therefore shortens the interval immediately. Each equality key is represented as an inclusive singleton `numrange(n, n, '[]')`, with a stable numeric code for each office. Singleton ranges overlap only when their values are equal. Built-in GiST range operators enforce the club/office and club/membership exclusions without `btree_gist`. The migrations neither provision nor depend on that extension.

Any future PostgreSQL writer must bind an explicit UTC offset, so a session time zone cannot shift an input instant.

SQLite writable fixtures use canonical UTC `YYYY-MM-DD HH:MM:SS` text and a composite foreign key. Insert overlap triggers apply the same strict half-open comparisons. The only permitted update is a first early end, which can only shrink an interval, so it cannot introduce overlap. Database guards reject changes to appointment evidence and deletion of term rows on both drivers. PostgreSQL also rejects truncation of populated term history and permits fixture cleanup when the table is empty. Empty intervals are prohibited. The resolver accepts any explicitly supplied instant and normalizes it to UTC.

The report emits a single read snapshot of legacy memberships grouped by club and office. It reports candidates, eligibility and exclusions, with no appointment dates or writes. A unique match is evidence for review.

## Verification boundary

Schema and resolver tests run on in-memory SQLite and disposable PostgreSQL. PostgreSQL additionally tests exclusion behavior across independent transactions. The officer foundation does not create governance endpoints, switch consumers or perform backfill. Live migration, data retention procedures, recovery operators and the later authority switch require separate approval.
