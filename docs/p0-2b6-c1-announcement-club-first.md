# P0.2B6-C1 announcement transactions

The nine management writes and the personal read marker each use one B1-owned PostgreSQL transaction. The verified club lock precedes announcement, membership, decision and marker work. Replacement graph locks remain ordered by announcement ID. Decision locks use the verified club, announcement and version. Management mutations keep their existing audit rows in the same transaction.

The marker checks current view permission and audience after admission. It samples one UTC time after locking the announcement and uses it for eligibility and the initial insert. An eligible replay returns the first timestamp. List and detail use the same audience policy and continue to read marker state without taking the mutation lock.

Publication continues to use the stored current-version approval and fingerprint. An officer change alone does not invalidate the approval. B6-B owns any future policy for authority changes, shared permission catalog writes and existing approvals. The membership contenders in this verification are test controls. Credential lifecycle and the other operational writers remain outside this increment.

Run `scripts/verify-cutover-bootstrap-postgres.ps1 -Suite B6C1` for announcement HTTP, service and marker contracts. Run `-Suite B6C1Races` for disposable PostgreSQL blocking evidence. The race suite covers both suspension winner orders for all ten writes and verifies actor, maker and checker changes, B6-A member updates, duplicate decisions and markers, replacement races, expiry after waiting, foreign lock avoidance and GETs while the club row is held. The existing B6-A historical deadlock diagnostic stays in its separate runner suite.
