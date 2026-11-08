[CmdletBinding()]
param(
    [ValidateSet('Cutover', 'Guard', 'Finance', 'C', 'D', 'Audit', 'Integration', 'Opening', 'F1')][string]$Suite = 'Cutover',
    [switch]$BootstrapOnly,
    [switch]$COnly,
    [switch]$CRegressionOnly,
    [string]$PostgresBin = 'C:/Program Files/PostgreSQL/17/bin',
    [string]$PhpPath = 'C:/Users/dell/.config/herd/bin/php84/php.exe',
    [ValidateRange(1024, 65535)][int]$Port = 55494
)

# Fresh cluster only. Never reads .env credentials or contacts an existing server.
$ErrorActionPreference = 'Stop'
if ($BootstrapOnly -and $Suite -ne 'Cutover') { throw '-BootstrapOnly requires -Suite Cutover.' }
if ($COnly -and $Suite -ne 'C') { throw '-COnly requires -Suite C.' }
if ($CRegressionOnly -and ($Suite -ne 'C' -or $COnly)) { throw '-CRegressionOnly requires -Suite C without -COnly.' }
$repository = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$output = Join-Path ([IO.Path]::GetTempPath()) ('lc-p02b2-'+$Suite.ToLower()+'-'+[guid]::NewGuid().ToString('N'))
$data = Join-Path $output 'data'
$admin = 'p02b2_isolated_admin'
$appRole = 'lc_beenachi_app'
$databases = @(switch ($Suite) {
    'Cutover' { @('lcb_p02b2_cutover_test') }
    'C' { @('lcb_p02b2_cutover_test') }
    'D' { @('lcb_p02b2_cutover_test') }
    'Audit' { @('lcb_p02b2_cutover_test') }
    'Integration' { @('lcb_p02b2_cutover_test') }
    'Opening' { @('lcb_p02b2_cutover_test') }
    'F1' { @('lcb_p02b2_cutover_test') }
    'Guard' { @('lcb_p02b1_transaction_guard_test') }
    'Finance' { @('lcb_3ijae_finance_p02b2ab', 'lcb_3iizt_statement_snapshot_concurrency_test') }
})
$evidence = @{ suite=$Suite; directory=$output; host='127.0.0.1'; port=$Port; databases=$databases; status='STARTING'; results=@() }
$evidence.bootstrap_only=[bool]$BootstrapOnly
$evidence.c_only=[bool]$COnly
$evidence.c_regression_only=[bool]$CRegressionOnly
$names = @('APP_ENV','DB_CONNECTION','DB_URL','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_SSLMODE','PGPASSWORD','PGOPTIONS','PGSERVICE','PGSERVICEFILE','APP_CONFIG_CACHE','CACHE_STORE','SESSION_DRIVER','QUEUE_CONNECTION','INSTANCE_ORGANISATION_CODE','RUN_P02B2_CUTOVER_POSTGRES','RUN_P02B1_POSTGRES_TESTS','RUN_3IIZG_POSTGRES_CONCURRENCY','RUN_3IJAE_POSTGRES_CONCURRENCY','RUN_3IIZT_POSTGRES_CONCURRENCY')
$saved = @{}
foreach ($name in $names) { $saved[$name] = @{ exists=(Test-Path "Env:$name"); value=[Environment]::GetEnvironmentVariable($name) } }
$started = $false

function Query-Fresh([string]$Sql, [string]$Database='postgres', [switch]$Application) {
    $role = if ($Application) { $appRole } else { $admin }
    $env:PGPASSWORD = if ($Application) { $appPassword } else { $adminPassword }
    $value = & "$PostgresBin/psql.exe" --host=127.0.0.1 --port=$Port --username=$role --dbname=$Database --no-password -X -qAt -v ON_ERROR_STOP=1 -c $Sql
    if ($LASTEXITCODE -ne 0) { throw 'Fresh-cluster query failed.' }
    return ($value -join "`n").Trim()
}

function Assert-Fresh {
    $identity = Query-Fresh "SELECT current_setting('data_directory')||'|'||host(inet_server_addr())||'|'||inet_server_port();"
    if ($identity.Replace('\','/') -cne ($data.Replace('\','/')+'|127.0.0.1|'+$Port)) { throw 'Fresh server identity mismatch.' }
    $identifier = Query-Fresh 'SELECT system_identifier FROM pg_control_system();'
    if ($identifier -cne $evidence.system_identifier) { throw 'Cluster system identifier changed.' }
    $inventory = Query-Fresh "SELECT string_agg(datname,',' ORDER BY datname) FROM pg_database;"
    $expected = (@($databases)+@('postgres','template0','template1') | Sort-Object) -join ','
    if ($inventory -cne $expected) { throw 'Unexpected database inventory; protected or non-allowlisted database rejected.' }
    foreach ($database in $databases) {
        $session = Query-Fresh "SELECT current_database()||'|'||current_user||'|'||host(inet_server_addr())||'|'||inet_server_port();" $database -Application
        if ($session -cne "$database|$appRole|127.0.0.1|$Port") { throw 'Application session identity mismatch.' }
        $safe = Query-Fresh "SELECT current_user=pg_get_userbyid(d.datdba) AND NOT r.rolsuper AND NOT r.rolcreatedb AND NOT r.rolcreaterole AND NOT r.rolreplication AND NOT r.rolbypassrls AND NOT r.rolinherit AND NOT EXISTS (SELECT 1 FROM pg_auth_members WHERE member=r.oid) FROM pg_database d JOIN pg_roles r ON r.rolname=current_user WHERE d.datname=current_database();" $database -Application
        if ($safe -cne 't') { throw 'Database ownership, non-elevated role or membership check failed.' }
    }
    $env:PGPASSWORD = $appPassword
    return @{ identity=$identity; inventory=$inventory; owner=$appRole; elevated=$false; memberships=0; protected_names_present=0 }
}

function Run-Tests([string]$Name, [string[]]$Paths, [string]$Database, [switch]$RecordFailures) {
    $null = Assert-Fresh
    $env:DB_DATABASE=$Database
    $env:PGPASSWORD=$appPassword
    & $PhpPath artisan migrate:fresh --force --no-ansi *> "$output/$Name-migrations.log"
    if ($LASTEXITCODE -ne 0) { throw "Migration failed for $Name." }
    & $PhpPath vendor/phpunit/phpunit/phpunit @Paths --colors=never --log-junit "$output/$Name.xml" *> "$output/$Name.log"
    $testExit = $LASTEXITCODE
    [xml]$xml = Get-Content "$output/$Name.xml" -Raw
    $root = $xml.testsuites.testsuite
    $result = @{name=$Name;tests=[int]$root.tests;assertions=[int]$root.assertions;failures=[int]$root.failures;errors=[int]$root.errors;skipped=[int]$root.skipped;exit_code=$testExit}
    $evidence.results += $result
    $result | ConvertTo-Json -Compress | Write-Host
    if ($testExit -ne 0 -or $result.failures -ne 0 -or $result.errors -ne 0 -or $result.skipped -ne 0) {
        if ($RecordFailures) { $evidence.recorded_test_failure=$true; return }
        throw "Test group $Name did not fully pass."
    }
}

New-Item -ItemType Directory -Path $output | Out-Null
try {
    if (Test-Path "$repository/backend/bootstrap/cache/config.php") { throw 'Cached application configuration rejected.' }
    if (Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue) { throw 'Fresh port is occupied.' }
    foreach ($name in @('PGOPTIONS','PGSERVICE','PGSERVICEFILE','APP_CONFIG_CACHE')) { Remove-Item "Env:$name" -ErrorAction SilentlyContinue }
    $evidence.head = (git -C $repository rev-parse HEAD).Trim()
    $sourcePaths = @(Get-ChildItem "$repository/backend/app" -Recurse -File -Filter '*.php') + @(Get-ChildItem "$repository/backend/tests" -Recurse -File -Filter '*.php')
    $before = @{}; foreach ($file in $sourcePaths) { $before[$file.FullName]=(Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash }
    $adminPassword=[Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    $appPassword=[Convert]::ToHexString([Security.Cryptography.RandomNumberGenerator]::GetBytes(32))
    $passwordFile=Join-Path $output 'init-password.txt'
    [IO.File]::WriteAllText($passwordFile,$adminPassword)
    & "$PostgresBin/initdb.exe" -D $data -U $admin --encoding=UTF8 --locale=C --auth-host=scram-sha-256 --auth-local=scram-sha-256 --pwfile=$passwordFile *> "$output/initdb.log"
    if ($LASTEXITCODE -ne 0) { throw 'Fresh initdb failed.' }
    Remove-Item -LiteralPath $passwordFile
    $startArguments=@('-D',('"'+$data+'"'),'-l',('"'+$output+'/server.log"'),'-w','-t','30','-o',('"-h 127.0.0.1 -p '+$Port+'"'),'start')
    $starter=Start-Process -FilePath "$PostgresBin/pg_ctl.exe" -ArgumentList $startArguments -WindowStyle Hidden -PassThru -RedirectStandardOutput "$output/start.log" -RedirectStandardError "$output/start-error.log"
    if (-not $starter.WaitForExit(35000) -or $starter.ExitCode -ne 0) { throw 'Fresh server failed to start.' }
    $started=$true
    $initialIdentity=Query-Fresh "SELECT current_setting('data_directory')||'|'||host(inet_server_addr())||'|'||inet_server_port();"
    if ($initialIdentity.Replace('\','/') -cne ($data.Replace('\','/')+'|127.0.0.1|'+$Port)) { throw 'Fresh identity rejected before role/database creation.' }
    $evidence.system_identifier=Query-Fresh 'SELECT system_identifier FROM pg_control_system();'
    if ((Query-Fresh "SELECT count(*) FROM pg_database WHERE datname NOT IN ('postgres','template0','template1');") -cne '0') { throw 'Cluster is not fresh.' }
    $roleFile=Join-Path $output 'create-role.sql'
    [IO.File]::WriteAllText($roleFile,"CREATE ROLE $appRole LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS NOINHERIT PASSWORD '$appPassword';")
    $env:PGPASSWORD=$adminPassword
    & "$PostgresBin/psql.exe" --host=127.0.0.1 --port=$Port --username=$admin --dbname=postgres --no-password -X -q -v ON_ERROR_STOP=1 -f $roleFile *> "$output/create-role.log"
    if ($LASTEXITCODE -ne 0) { throw 'Fresh role creation failed.' }
    Remove-Item -LiteralPath $roleFile
    foreach ($database in $databases) { $null=Query-Fresh "CREATE DATABASE $database OWNER $appRole TEMPLATE template0 ENCODING 'UTF8';" }
    $evidence.before=Assert-Fresh
    $env:APP_ENV='testing';$env:DB_CONNECTION='pgsql';$env:DB_URL='';$env:DB_HOST='127.0.0.1';$env:DB_PORT=[string]$Port;$env:DB_USERNAME=$appRole;$env:DB_PASSWORD=$appPassword;$env:DB_SSLMODE='disable'
    $env:CACHE_STORE='array';$env:SESSION_DRIVER='array';$env:QUEUE_CONNECTION='sync';$env:INSTANCE_ORGANISATION_CODE='UNASSIGNED-SYNTHETIC'
    $env:RUN_P02B2_CUTOVER_POSTGRES='1';$env:RUN_P02B1_POSTGRES_TESTS='1';$env:RUN_3IIZG_POSTGRES_CONCURRENCY='1';$env:RUN_3IJAE_POSTGRES_CONCURRENCY='1';$env:RUN_3IIZT_POSTGRES_CONCURRENCY='1'
    Push-Location "$repository/backend"
    try {
        switch ($Suite) {
            'F1' { Run-Tests 'f1-real-outcomes' @('tests/Feature/CutoverOpeningTransactionOutcomeTest.php') $databases[0] }
            'Opening' { Run-Tests 'opening-focused' @('tests/Feature/CutoverOpeningEnforcementTest.php') $databases[0] }
            'Integration' {
                Run-Tests 'de-focused' @('tests/Feature/CutoverLifecycleEligibilityTest.php','tests/Feature/CutoverExecutionAuditRecorderTest.php','tests/Feature/CutoverOpeningEnforcementTest.php') $databases[0]
                Run-Tests 'c-focused' @('tests/Feature/CutoverOrganisationEnforcementTest.php') $databases[0]
                Run-Tests 'bootstrap' @('tests/Feature/CutoverBootstrapOrganisationTest.php') $databases[0]
                Run-Tests 'historical' @('tests/Feature/Milestone3iizeCutoverPackagePersistenceAndPreviewTest.php','tests/Feature/Milestone3iizfCutoverAuthorizationAndLifecycleContractTest.php','tests/Feature/Milestone3iizgOpeningJournalAndOneTimeActivationTest.php','tests/Feature/Milestone3iizsCutoverAdministrationInternalEnforcementFoundationTest.php') $databases[0]
                Run-Tests 'concurrency' @('tests/Feature/Milestone3iizgPostgresConcurrencyTest.php') $databases[0]
            }
            'Audit' { Run-Tests 'audit-focused' @('tests/Feature/CutoverExecutionAuditRecorderTest.php') $databases[0] }
            'D' {
                Run-Tests 'd-focused' @('tests/Feature/CutoverLifecycleEligibilityTest.php') $databases[0]
                Run-Tests 'd-historical' @('tests/Feature/Milestone3iizeCutoverPackagePersistenceAndPreviewTest.php','tests/Feature/Milestone3iizfCutoverAuthorizationAndLifecycleContractTest.php','tests/Feature/Milestone3iizgOpeningJournalAndOneTimeActivationTest.php','tests/Feature/Milestone3iizsCutoverAdministrationInternalEnforcementFoundationTest.php') $databases[0]
            }
            'C' {
                if (-not $CRegressionOnly) {
                Run-Tests 'c-focused' @('tests/Feature/CutoverOrganisationEnforcementTest.php') $databases[0]
                }
                if (-not $COnly) {
                    if (-not $CRegressionOnly) {
                    Run-Tests 'bootstrap' @('tests/Feature/CutoverBootstrapOrganisationTest.php') $databases[0]
                    }
                    Run-Tests 'historical' @('tests/Feature/Milestone3iizeCutoverPackagePersistenceAndPreviewTest.php','tests/Feature/Milestone3iizfCutoverAuthorizationAndLifecycleContractTest.php','tests/Feature/Milestone3iizgOpeningJournalAndOneTimeActivationTest.php','tests/Feature/Milestone3iizsCutoverAdministrationInternalEnforcementFoundationTest.php') $databases[0]
                    # Keep original assertions, preserve failure status, and retain all evidence.
                    Run-Tests 'c-mixed-concurrency' @('tests/Feature/Milestone3iizgPostgresConcurrencyTest.php') $databases[0] -RecordFailures
                }
            }
            'Cutover' {
                Run-Tests 'bootstrap' @('tests/Feature/CutoverBootstrapOrganisationTest.php') $databases[0]
                if (-not $BootstrapOnly) {
                Run-Tests 'historical' @('tests/Feature/Milestone3iizeCutoverPackagePersistenceAndPreviewTest.php','tests/Feature/Milestone3iizfCutoverAuthorizationAndLifecycleContractTest.php','tests/Feature/Milestone3iizgOpeningJournalAndOneTimeActivationTest.php','tests/Feature/Milestone3iizsCutoverAdministrationInternalEnforcementFoundationTest.php') $databases[0]
                Run-Tests 'concurrency' @('tests/Feature/Milestone3iizgPostgresConcurrencyTest.php') $databases[0]
                }
            }
            'Guard' { Run-Tests 'guard' @('tests/Feature/OrganisationTransactionGuardTest.php') $databases[0] }
            'Finance' {
                Run-Tests 'acceptance' @('tests/Feature/Milestone3ijaeOrdinaryIncomeReceiptCorrectionTest.php') $databases[0]
                Run-Tests 'finance-concurrency' @('tests/Feature/Milestone3ijaePostgresOrdinaryIncomeReceiptCorrectionConcurrencyTest.php') $databases[0]
                Run-Tests 'transactional' @('tests/Feature/DefaultRolesAndPermissionsSeederTest.php','tests/Feature/ClubPermissionServiceTest.php','tests/Feature/ClubPermissionMiddlewareTest.php','tests/Feature/Milestone3iicCoreJournalPostingServiceTest.php','tests/Feature/Milestone3iieInternalJournalReversalServiceTest.php','tests/Feature/Milestone3iizkACanonicalAccountingMetadataFundAttributionTest.php') $databases[0]
                Run-Tests 'receipts-statements' @('tests/Feature/Milestone3iizzOrdinaryIncomeReceiptTest.php','tests/Feature/Milestone3iizkClubAccountStatementProjectionTest.php','tests/Feature/Milestone3iizlClubAccountStatementProjectionValidationAndClosureTest.php') $databases[0]
                Run-Tests 'snapshot-concurrency' @('tests/Feature/Milestone3iiztPostgresStatementSnapshotConcurrencyTest.php') $databases[1]
            }
        }
    } finally { Pop-Location }
    $evidence.after=Assert-Fresh
    foreach ($file in $sourcePaths) { if ((Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash -cne $before[$file.FullName]) { throw 'Source changed during validation.' } }
    $evidence.source_hashes_unchanged=$true
    $evidence.status='PASS'
    if ($evidence.recorded_test_failure) { $evidence.status='FAILED_OR_BLOCKED'; $evidence.reason='Mixed-order concurrency assertions did not fully pass. Merge NO-GO.' }
    if ($Suite -in @('C','Integration') -and (Select-String -LiteralPath "$output/server.log" -Pattern 'deadlock detected' -Quiet)) {
        $evidence.mixed_order_deadlock_observed=$true
        $evidence.status='FAILED_OR_BLOCKED';$evidence.reason='PostgreSQL observed a mixed-order deadlock. Merge NO-GO regardless of victim outcomes.'
    }
} catch {
    $evidence.status='FAILED_OR_BLOCKED';$evidence.reason=$_.Exception.Message
    throw
} finally {
    if ($started) {
        # Windows fsync of repeated disposable migrations can exceed sixty seconds.
        # This changes teardown patience only, never business retries or worker timeouts.
        & "$PostgresBin/pg_ctl.exe" -D $data -w -t 120 -m fast stop *> "$output/stop.log"
        $evidence.stop_exit_code=$LASTEXITCODE
        if ($LASTEXITCODE -ne 0) { $evidence.status='FAILED_OR_BLOCKED' }
    }
    foreach ($file in @('init-password.txt','create-role.sql')) { $path=Join-Path $output $file; if (Test-Path -LiteralPath $path) { Remove-Item -LiteralPath $path } }
    foreach ($name in $names) { if ($saved[$name].exists) { Set-Item "Env:$name" $saved[$name].value } else { Remove-Item "Env:$name" -ErrorAction SilentlyContinue } }
    $evidence | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath "$output/evidence.json" -Encoding utf8
    Write-Host "Evidence: $output/evidence.json"
    Write-Host "Result: $($evidence.status). Shutdown exit: $($evidence.stop_exit_code). No existing server accessed."
}
if ($evidence.status -cne 'PASS') { throw 'Validation or fresh-cluster shutdown did not fully pass; inspect evidence.' }
