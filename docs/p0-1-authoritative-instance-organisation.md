# P0.1 — Authoritative Instance Organisation

Implementation base: clean `main`, `8baa515fbb197fd3e16f6f0ad9d0dfb6dfe30827`.
Branch: `codex/p0-1-authoritative-instance-organisation`.
Status: implementation for draft review; no deployment or pilot approval.

## Contract

Each isolated application has one backend deployment setting,
`INSTANCE_ORGANISATION_CODE`, resolved by `config/instance.php` against the existing
unique `clubs.code`. The code must be a nonempty exact string with no surrounding
whitespace and must resolve to exactly one record. There is no default, request
override, first-club fallback, or membership-order fallback. The existing unique
constraint is preserved, and the resolver additionally rejects a non-singleton
result. No database migration or new organisation identifier column is introduced.

The setting is immutable to application users: there is no API setter. Treat it
as deployment identity, not an organisation switch. Changing it requires a later
reviewed deployment decision and must never retarget a populated pilot instance.
Additional club records do not become selectable.

## Backend enforcement

- `EnsureInstanceOrganisation` runs in the API group, before model substitution,
  on every route containing a `club` parameter and on login, session verification
  and activation. Route coverage and ordering are tested against the actual route
  inventory.
- A foreign organisation URL returns generic 404 before controller/domain work.
  Authentication ordering and existing membership, permission, validation and
  resource-ownership checks remain in place. The guard grants no permissions.
- Missing/invalid/unknown configuration returns 503 for organisation-dependent
  requests; authenticated session data and tokens are not returned. An
  unauthenticated protected request can still fail earlier with 401.
- Login requires an active membership with a role in the configured organisation
  before issuing a token.
- `/auth/me` returns `instance_organisation: {id, code, name}` and exactly one
  matching active `club_memberships` entry, including its existing role,
  permissions and linked-member representation. Foreign memberships/member
  details are not serialized. Existing duplicate linked-member checks remain.
- Activation service target validation also enforces instance identity, covering
  both public completion and the existing bootstrap command. Hashing, expiry,
  single-use, password strength, revocation and audit behavior remain intact.
- Health and authenticated logout remain available if instance configuration is
  broken. P0.1 does not implement suspension, subscription access or a support
  bypass.

## Frontend contract and lifecycle

The auth provider requires the backend organisation identity and exactly one
matching active membership. It rejects missing, inconsistent or duplicate
matching context. No `LCB` preference remains.

All modules consume the provider's verified membership. Member loading now uses
`session.organisation.id` directly instead of reconstructing and selecting a
first-membership list. The existing module permission checks remain.

The provider owns a session generation and cancels pending provider requests on
logout. A login response from an obsolete generation cannot restore a session.
The entire child subtree remounts per verified session and unmounts on logout,
discarding organisation-owned state, including results from direct-fetch legacy
components. Tests also cover response bodies completing after a new login.

`authenticatedFetch` rejects foreign club paths and non-API/external origins,
overwrites caller-supplied authorization with the verified in-memory token, and
rejects obsolete request responses. The backend remains the authority for direct
requests. Known tokens from failed/obsolete verification are revoked best-effort;
network failure cannot prevent local logout. Tokens are not stored in browser
storage. Server-side session expiry policy remains a separate follow-up.

## Test adaptations

Existing security fixtures explicitly configure their synthetic organisation.
Finance API tests explicitly select the synthetic deployment before requests for
each fixture; multi-club tests simulate separate deployments rather than adding
a runtime selection fallback. Original finance assertions, fixture accounting,
permissions, concurrency flags and service code are preserved. A synthetic viewer
reassigned to the owner's club must use the owner's instance context.

The historical document test for a foreign-instance URL now expects the earlier
404 boundary instead of permission-layer 403. Same-instance ownership and role
denial tests remain. Auth API user fixtures now have an explicit instance
membership rather than expecting a membership-less production login to succeed.

## Validation

Security suite uses process-only `APP_ENV=testing`, `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:` and empty `DB_URL`, followed by the installed PHP 8.4.23
runtime running `vendor/phpunit/phpunit/phpunit` from `backend` with:

```text
tests/Feature/InstanceOrganisationTest.php
tests/Feature/AuthApiTest.php
tests/Feature/AuthMeRoleAwareResponseTest.php
tests/Feature/AccountActivationTest.php
tests/Feature/ClubPermissionServiceTest.php
tests/Feature/ClubPermissionMiddlewareTest.php
tests/Feature/MemberSelfProfileAuthorizationTest.php
tests/Feature/LocalRoleApiPermissionSmokeTest.php
tests/Feature/Milestone3cxMemberProfileDocumentsReadApiNarrowImplementationTest.php
```

Final security result: 64 passed, 786 assertions, zero failures/errors/skips.
This includes positive assigned-club access, membership-order independence,
invalid configuration, matching inactive/missing membership, request spoofing,
foreign resource and activation rejection, no mutation on denial, actual route
middleware coverage, and logout with broken configuration.

Final frontend result: 21 files, 210 passed, zero failures/skips. The 12 new tests
exercise order independence, inconsistent/ambiguous session rejection, external
and foreign request rejection, token-header enforcement, interrupted login,
pending-request cancellation and stale response-body isolation.

TypeScript and production build passed. ESLint: zero errors, 87 existing
warnings. The React review covered hook dependencies, session subtree lifetime,
asynchronous cancellation, in-memory token handling and request boundaries.

The canonical finance verifier is run using:

```powershell
./scripts/verify-finance-release-gate.ps1 `
  -CreateDisposableDatabase -AllowFreshExistingDisposableDatabase `
  -DatabaseAdministratorUsername postgres `
  -DatabaseName lcb_3ijae_finance_p01_20261106 `
  -PhpPath <installed-PHP-8.4.23> `
  -ReportPath <outside-repository>/lc-p01-release-gate-reviewed.json
```

Its only reset/test targets are the named disposable database and the existing
designated `lcb_3iizt_statement_snapshot_concurrency_test`. It checks the two
protected databases and five non-elevated application-role flags. No database is
dropped. Reports/logs remain outside Git and contain no credentials. See the
final validation table below for every canonical lane and audit result.

Canonical verifier result on 6 November 2026: exit code 0. PostgreSQL server and
client: 17.5. The tested implementation working tree is committed on this branch;
the report's repository identity records its starting HEAD, before the commit.
The report is retained locally at
`C:\Users\dell\AppData\Local\Temp\lc-p01-release-gate-reviewed.json` and its
corresponding `.log`.

| Gate | Final result |
| --- | --- |
| Locked Composer audit | PASS; zero advisories and abandoned packages |
| Root and backend npm audits | PASS; zero findings |
| Frontend tests | 210 passed across 21 files; zero failures/skips |
| TypeScript / production build | PASS / PASS |
| ESLint | Zero errors; 87 existing warnings |
| Disposable PostgreSQL migrations | PASS on both designated targets |
| 3IJAE acceptance | 10 passed; 288 assertions; zero failures/errors/skips |
| 3IJAE genuine PostgreSQL concurrency | 1 passed; 77 assertions; zero failures/errors/skips |
| Transactional finance regression | 47 passed; 455 assertions; zero failures/errors/skips |
| Receipt and statement regression | 35 passed; 334 assertions; zero failures/errors/skips |
| 3IIZT genuine snapshot concurrency | 1 passed; 11 assertions; zero failures/errors/skips |
| PostgreSQL protection/privilege checks | PASS before and after; protected databases present and five elevated role flags false |
| Disposable database retention | PASS; neither target dropped |

Canonical PHP total: 94 passed, 1,165 assertions, zero failures/errors/skips.
The additional in-memory security suite above is a separate run; its counts are
not presented as disjoint from the canonical groups.

Earlier failed runs were retained as evidence: frontend 170/198 (28 failures due
to old context fixtures/header-shape assertions), then 76/78 (two header-shape
assertions); security 61/62 (old cross-instance status expectation), then 63/64
(synthetic club's unrefreshed default activity flag); first canonical acceptance
9/10 (synthetic viewer assigned to owner club but configured for its old club).
The next canonical run passed acceptance/concurrency/transactional groups but
returned 32/35 in receipt/statement regressions: two unactivated-club fixtures
still selected their original club and one permission test targeted a foreign
instance. Those tests now explicitly select the intended synthetic deployment;
the original 409 accounting and 403 membership assertions remain unchanged.
Fixtures/assertions were corrected without disabling guards or changing finance
semantics. PHP initially failed the sandbox autoloader readability check; the
same tests succeeded outside that restriction with explicit in-memory settings.

## Review and rollback

- This is a schema-free, coordinated frontend/backend contract change. Older
  frontend/backend combinations are not supported; review both together.
- A future approved deployment must set and independently verify the exact code
  before accepting traffic, rebuild/cache configuration correctly, and retain
  separate app/database/configuration/credentials/application keys/storage per
  pilot organisation. `.env.example` remains blank intentionally.
- No current environment file was changed. No organisation was provisioned and
  no finance cutover, hosting purchase, deployment or merge was performed.
- Rollback in disposable development is a code rollback; no down migration is
  needed. For a future pilot, isolate traffic first and restore only a release
  retaining an equivalent instance boundary. Do not expose the old membership
  fallback as a rollback workaround. Preserve configuration/keys and financial
  data; P0.1 never requires restoring or reversing accounting transactions.
- Full historical backend-suite execution is not claimed. Reviewed security
  groups and canonical finance groups are the validation scope. Other legacy API
  fixtures may need explicit instance configuration when those suites are run;
  a missing configuration must never be bypassed to make them pass.
- Physical cross-instance credential/storage isolation, hosting recovery, browser
  acceptance with real pilot users, generic branding, and the existing fixed
  47-member imported-data assumptions remain later milestone work. No live
  browser or production-host validation is claimed.
- P0.2 suspension, P0.3 provisioning, P0.4 hosting/recovery and P0.5 pilot
  acceptance require separate approval. P0.1 test success is not pilot GO.
