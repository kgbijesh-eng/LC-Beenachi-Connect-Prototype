# B6-B credential coordination

Activation issuance and completion and login enter through B1. Logout owns a PostgreSQL transaction without acquiring a club lock. Each coordinates on the same transaction-scoped advisory key, the negated full positive user ID in the single-bigint key space. Issuance locks the scoped member before the user key. Activation rows are then locked in ID order. Issuance, login and logout use one attempt. Completion retains three.

Completion's unlocked token lookup only identifies the candidate club and account. The guarded transaction revalidates the grant and current eligibility. A foreign or stale grant produces the same generic completion failure. Successful completion retains same-club grant revocation and user-wide API-token invalidation. Two valid grants for the same account in different clubs can still be consumed serially, with the later credential change prevailing.

Use the disposable verifier suites `B6BCredentials` and `B6BCredentialRaces`. The latter records blocker and waiter PIDs and the awaited lock category. Race workers use synthetic credentials and omit request values and response bodies from the evidence. Run retained organisation, finance actor, announcement and privacy cases before publishing a draft.

The application diff is limited to the approved five paths. Live recovery, operator and external writer attestations, and external advisory namespace verification remain release blockers. Membership and officer governance and finance release or cutover remain separate.
