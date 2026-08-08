# PostgreSQL Accounting Operations Runbook

## Status and scope

This runbook records procedures proven on isolated local PostgreSQL 17.5 databases during Milestone 3IIZO-A. It is not production approval. Production use requires an independently approved target, credentials, change window, evidence plan, recovery decision-maker, and operational sign-off. Real accounting activation remains separately gated.

## Non-negotiable safety boundaries

- Never target `lc_beenachi_connect_reconstructed_20260727` with rehearsal, migration-reset, test, restore, or drop commands.
- Disposable rehearsal databases must begin with `lcb_3iizo_` and must equal the exact names approved for the run.
- Source and restore names must differ.
- Resolve the actual host, port, database, and role before every mutation; an environment filename is not proof of the target.
- Keep passwords only in process environment. Never print them, place them on a command line, or store them in Git.
- The application role must remain non-superuser with `CREATEDB=false`, `CREATEROLE=false`, `REPLICATION=false`, and `BYPASSRLS=false`.
- An administrator creates exact disposable databases with the application role as owner. Do not grant the application role server-wide database-creation authority.

Example guard:

```powershell
$protectedDb = "lc_beenachi_connect_reconstructed_20260727"
$allowed = @($sourceDb, $restoreDb)

function Assert-DisposableTarget([string] $database) {
    if (-not $database.StartsWith("lcb_3iizo_")) {
        throw "The database name is outside the disposable rehearsal namespace."
    }
    if ($database -eq $protectedDb -or $database -notin $allowed) {
        throw "The database is not an exact authorized rehearsal target."
    }
}
```

## Prerequisites and evidence header

Record before work begins:

- Git branch and full SHA;
- PostgreSQL server and client versions;
- exact source and restore names;
- host and port;
- database owner;
- role capability flags;
- protected-database existence;
- confirmation that both disposable names were previously absent or explicitly administrator-created for this run.

Use the `postgres` maintenance database for database-level verification. Query only metadata needed for the authorized targets.

```sql
SELECT datname, pg_catalog.pg_get_userbyid(datdba) AS owner
FROM pg_database
WHERE datname IN ('<SOURCE_DB>', '<RESTORE_DB>')
ORDER BY datname;

SELECT rolname, rolcreatedb, rolsuper, rolcreaterole, rolreplication, rolbypassrls
FROM pg_roles
WHERE rolname = '<APPLICATION_ROLE>';
```

## Administrator provisioning model

Run database creation only through an authorized administrator/operator:

```sql
CREATE DATABASE "<SOURCE_DB>"
    WITH OWNER = <APPLICATION_ROLE>
    ENCODING = 'UTF8'
    TEMPLATE = template0;

CREATE DATABASE "<RESTORE_DB>"
    WITH OWNER = <APPLICATION_ROLE>
    ENCODING = 'UTF8'
    TEMPLATE = template0;
```

Verify both databases exist, have the expected owner, accept the application-role connection, and contain no application tables. Unexpected contents are a hard stop; do not erase them to continue.

## Process-only Laravel configuration

Set `APP_ENV=testing`, `DB_CONNECTION=pgsql`, local host/port, the exact target database, and the application username/password only in the current process. Clear a Laravel configuration cache only if one actually exists. Bootstrap Laravel and print only non-secret resolved values before mutation.

Required target proof:

```text
APP_ENV
DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
```

## Migration preflight and execution

1. Assert the exact source target.
2. Verify the source has no unexpected application schema.
3. Verify Laravel resolves the source name.
4. Run forward migrations:

```powershell
& $php artisan migrate --force
& $php artisan migrate:status
```

5. Record migration count, duration, status, required-table presence, constraints, and indexes.

Do not use `migrate:fresh` routinely. It is acceptable only for an exact disposable target after a separately recorded reason and immediate target re-verification. Do not manually edit schema to conceal a migration failure.

## PostgreSQL concurrency proof

Run only against the exact source database. Confirm worker processes inherit the same `DB_CONNECTION=pgsql` and `DB_DATABASE` values.

```powershell
$env:RUN_3IIZG_POSTGRES_CONCURRENCY = "1"
& $php artisan test --compact --filter Milestone3iizgPostgresConcurrencyTest
Remove-Item Env:RUN_3IIZG_POSTGRES_CONCURRENCY

$env:RUN_3IIZH_POSTGRES_CONCURRENCY = "1"
& $php artisan test --compact --filter Milestone3iizhPostgresConcurrencyTest
Remove-Item Env:RUN_3IIZH_POSTGRES_CONCURRENCY
```

Record passed, failed, skipped, and assertion counts separately. A skip is not a pass. Preserve any locking, idempotency, constraint, deadlock, or multiple-winner failure as a genuine technical finding.

## Deterministic backup evidence

After concurrency tests, re-establish current migrations and create only synthetic markers through canonical application mechanisms. Record public identifiers and a small deterministic set of table counts.

Before backup, assert that the target equals the exact source and differs from the protected database. Write the custom-format dump outside the repository:

```powershell
& $pgDump -Fc -w -h $dbHost -p $dbPort -U $dbUser -d $sourceDb -f $dumpPath
```

Record the exit code, tool version, elapsed time, file size, and SHA-256. Validate the dump rather than relying only on exit status:

```powershell
& $pgRestore --list $dumpPath
```

The table of contents must be non-empty and include migrations, application tables, and selected accounting tables.

## Restore and verification

1. Assert the exact restore target and verify it is empty.
2. Restore without applying source ownership or privileges:

```powershell
& $pgRestore -w -h $dbHost -p $dbPort -U $dbUser -d $restoreDb `
    --no-owner --no-privileges $dumpPath
```

3. Point Laravel process-only configuration at the restore database.
4. Verify connectivity, application bootstrap, migration status, expected tables, synthetic public identifiers, and selected counts.
5. Produce an explicit source/restore comparison. Do not claim byte-for-byte equivalence unless separately proven.
6. Verify accounting/readiness behavior remains fail-closed where activation was not performed.

## Recovery decision and proof

The proven recovery pattern is:

```text
verified pre-change backup
→ forward change
→ recovery decision
→ isolated restore
→ deterministic application and accounting verification
```

A harmless source-only synthetic change may prove that the restored database represents the earlier recovery point. Migration rollback is not the primary recovery method merely because `down()` methods exist.

Production recovery additionally requires approved outage handling, retention, encryption, recovery-time and recovery-point objectives, infrastructure replacement/rename procedures, traffic isolation, and decision authority.

## Cleanup

Capture all evidence before cleanup. Close application/test connections. For each database, rerun the exact-name guard and drop only the two authorized targets. Never use wildcards or enumerate other databases for deletion.

```powershell
Assert-DisposableTarget $sourceDb
& $dropDb -w -h $dbHost -p $dbPort -U $dbUser $sourceDb

Assert-DisposableTarget $restoreDb
& $dropDb -w -h $dbHost -p $dbPort -U $dbUser $restoreDb
```

Verify candidate count is zero and the protected database still exists. Delete only the exact dump file after restore evidence and database cleanup are confirmed. Reverify all role-isolation flags.

## Evidence checklist

- [ ] Git SHA and exact targets recorded
- [ ] local server, owners, emptiness, and role isolation proven
- [ ] Laravel target printed without secrets
- [ ] migrations and schema verified
- [ ] dedicated PostgreSQL concurrency tests executed without skips
- [ ] synthetic public identifiers and counts recorded
- [ ] custom dump created, hashed, and table-of-contents validated
- [ ] restore completed without owner/privilege replay
- [ ] deterministic source/restore comparison matched
- [ ] recovery-point divergence demonstrated
- [ ] application bootstrap and fail-closed accounting behavior checked
- [ ] both exact databases removed
- [ ] exact dump removed
- [ ] protected database and role isolation reverified
- [ ] credentials, dumps, real member data, and protected financial evidence absent from Git
