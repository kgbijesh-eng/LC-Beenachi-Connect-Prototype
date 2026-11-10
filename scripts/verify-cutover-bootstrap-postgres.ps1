[CmdletBinding()]
param(
    [ValidateSet('Cutover', 'Guard', 'Finance', 'C', 'D', 'Audit', 'Integration', 'Opening', 'F1', 'B3Foundation', 'B3', 'B3Historical', 'B3Repair', 'B3Fixtures', 'B3Poison', 'B3PoisonCommit', 'B3Active', 'B3Direct', 'B3Contexts', 'B3Races', 'B3Workers', 'B3Retries', 'B3Resources', 'B3Cross', 'B3OpeningPeer', 'B4', 'B4Races', 'B4Resources', 'B4Reactivation', 'Coverage', 'CoverageRaces', 'B5', 'B5Outcomes', 'B5Races', 'B5Retries', 'B5Opening', 'B6A', 'B6ADiagnostic', 'B6C1', 'B6C1Races', 'B6BCredentials', 'B6BCredentialRaces', 'B6BOfficerFoundation')][string]$Suite = 'Cutover',
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
    'B6A' { @('lcb_p02b3_finance_test') }
    'B6C1' { @('lcb_p02b3_finance_test') }
    'B6BCredentials' { @('lcb_p02b3_finance_test') }
    'B6BOfficerFoundation' { @('lcb_p02b3_finance_test') }
    'B6BCredentialRaces' { @('lcb_p02b3_finance_test') }
    'B6C1Races' { @('lcb_p02b3_finance_test') }
    'B6ADiagnostic' { @('lcb_p02b3_finance_test') }
    'B5Opening' { @('lcb_p02b2_cutover_test') }
    'B5' { @('lcb_p02b3_finance_test') }
    'B5Outcomes' { @('lcb_p02b3_finance_test') }
    'B5Races' { @('lcb_p02b3_finance_test') }
    'B5Retries' { @('lcb_p02b3_finance_test') }
    'Coverage' { @('lcb_p02b3_finance_test') }
    'CoverageRaces' { @('lcb_p02b3_finance_test') }
    'B4' { @('lcb_p02b3_finance_test') }
    'B4Races' { @('lcb_p02b3_finance_test') }
    'B4Resources' { @('lcb_p02b3_finance_test') }
    'B4Reactivation' { @('lcb_p02b3_finance_test') }
    'B3Foundation' { @('lcb_p02b3_finance_test') }
    'B3' { @('lcb_p02b3_finance_test') }
    'B3Historical' { @('lcb_p02b3_finance_test') }
    'B3Repair' { @('lcb_p02b3_finance_test') }
    'B3Fixtures' { @('lcb_p02b3_finance_test') }
    'B3Poison' { @('lcb_p02b3_finance_test') }
    'B3PoisonCommit' { @('lcb_p02b3_finance_test') }
    'B3Active' { @('lcb_p02b3_finance_test') }
    'B3Direct' { @('lcb_p02b3_finance_test') }
    'B3Contexts' { @('lcb_p02b3_finance_test') }
    'B3Races' { @('lcb_p02b3_finance_test') }
    'B3Retries' { @('lcb_p02b3_finance_test') }
    'B3Resources' { @('lcb_p02b3_finance_test') }
    'B3Cross' { @('lcb_p02b3_finance_test') }
    'B3OpeningPeer' { @('lcb_p02b2_cutover_test') }
    'B3Workers' { @('lcb_3iizh_concurrency_test','lcb_3iizv_expense_concurrency_test','lcb_3iizw_expense_reversal_concurrency_test') }
    'Guard' { @('lcb_p02b1_transaction_guard_test') }
    'Finance' { @('lcb_3ijae_finance_p02b2ab', 'lcb_3iizt_statement_snapshot_concurrency_test') }
})
$evidence = @{ suite=$Suite; directory=$output; host='127.0.0.1'; port=$Port; databases=$databases; status='STARTING'; results=@() }
$evidence.bootstrap_only=[bool]$BootstrapOnly
$evidence.c_only=[bool]$COnly
$evidence.c_regression_only=[bool]$CRegressionOnly
$names = @('APP_ENV','DB_CONNECTION','DB_URL','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_SSLMODE','PGPASSWORD','PGOPTIONS','PGSERVICE','PGSERVICEFILE','APP_CONFIG_CACHE','CACHE_STORE','SESSION_DRIVER','QUEUE_CONNECTION','INSTANCE_ORGANISATION_CODE','RUN_B6A_PROFILE_DIAGNOSTIC','RUN_P02B2_CUTOVER_POSTGRES','RUN_P02B1_POSTGRES_TESTS','RUN_P02B3_FINANCE_POSTGRES','RUN_3IIZG_POSTGRES_CONCURRENCY','RUN_3IJAE_POSTGRES_CONCURRENCY','RUN_3IIZT_POSTGRES_CONCURRENCY')
$names += @('RUN_3IIZH_POSTGRES_CONCURRENCY','RUN_3IIZV_POSTGRES_CONCURRENCY','RUN_3IIZW_POSTGRES_CONCURRENCY','RUN_3IJAB_POSTGRES_CONCURRENCY')
$names += @('B3_WORKER_PORT','B3_WORKER_STARTED','B3_WORKER_DATABASE_OID')
$names += @('B3_FIXTURE_PORT','B3_FIXTURE_STARTED','B3_FIXTURE_DATABASE_OID','B3_FIXTURE_POISON_FILE')
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
    if ($env:B3_FIXTURE_POISON_FILE -and (Test-Path -LiteralPath $env:B3_FIXTURE_POISON_FILE)) { throw 'Fixture cluster already discarded; no further database operations.' }
    $null = Assert-Fresh
    $env:DB_DATABASE=$Database
    $env:B3_WORKER_PORT=[string]$Port
    $env:B3_WORKER_STARTED=Query-Fresh 'SELECT extract(epoch FROM pg_postmaster_start_time())::text;' $Database -Application
    $env:B3_WORKER_DATABASE_OID=Query-Fresh 'SELECT oid::text FROM pg_database WHERE datname=current_database();' $Database -Application
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
    if ($env:B3_FIXTURE_POISON_FILE -and (Test-Path -LiteralPath $env:B3_FIXTURE_POISON_FILE)) {
        $evidence.fixture_discard_requested=$true
        if ($Suite -notin @('B3Poison','B3PoisonCommit')) { throw 'Fixture restoration/outcome uncertain; discard fresh cluster immediately.' }
    }
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
    foreach ($name in @('B3_FIXTURE_PORT','B3_FIXTURE_STARTED','B3_FIXTURE_DATABASE_OID','B3_FIXTURE_POISON_FILE')) { Remove-Item "Env:$name" -ErrorAction SilentlyContinue }
    if ($databases.Count -eq 1 -and $databases[0] -ceq 'lcb_p02b3_finance_test') {
        $env:B3_FIXTURE_PORT=[string]$Port
        $env:B3_FIXTURE_STARTED=Query-Fresh 'SELECT extract(epoch FROM pg_postmaster_start_time())::text;' $databases[0] -Application
        $env:B3_FIXTURE_DATABASE_OID=Query-Fresh 'SELECT oid::text FROM pg_database WHERE datname=current_database();' $databases[0] -Application
        $env:B3_FIXTURE_POISON_FILE=Join-Path $output 'fixture-discard.json'
        $evidence.fixture_attestation=@{port=$env:B3_FIXTURE_PORT;postmaster_started=$env:B3_FIXTURE_STARTED;database_oid=$env:B3_FIXTURE_DATABASE_OID;system_identifier=$evidence.system_identifier}
    }
    $env:APP_ENV='testing';$env:DB_CONNECTION='pgsql';$env:DB_URL='';$env:DB_HOST='127.0.0.1';$env:DB_PORT=[string]$Port;$env:DB_USERNAME=$appRole;$env:DB_PASSWORD=$appPassword;$env:DB_SSLMODE='disable'
    $env:CACHE_STORE='array';$env:SESSION_DRIVER='array';$env:QUEUE_CONNECTION='sync';$env:INSTANCE_ORGANISATION_CODE='UNASSIGNED-SYNTHETIC'
    $env:RUN_3IIZH_POSTGRES_CONCURRENCY='1';$env:RUN_3IIZV_POSTGRES_CONCURRENCY='1';$env:RUN_3IIZW_POSTGRES_CONCURRENCY='1';$env:RUN_3IJAB_POSTGRES_CONCURRENCY='0';$env:RUN_P02B3_FINANCE_POSTGRES='1';$env:RUN_P02B2_CUTOVER_POSTGRES='1';$env:RUN_P02B1_POSTGRES_TESTS='1';$env:RUN_3IIZG_POSTGRES_CONCURRENCY='1';$env:RUN_3IJAE_POSTGRES_CONCURRENCY='1';$env:RUN_3IIZT_POSTGRES_CONCURRENCY='1'
    Push-Location "$repository/backend"
    try {
        switch ($Suite) {
            'F1' {
                Run-Tests 'f1-real-outcomes' @('tests/Feature/CutoverOpeningTransactionOutcomeTest.php') $databases[0]
                Run-Tests 'f1-real-outcomes-stability' @('tests/Feature/CutoverOpeningTransactionOutcomeTest.php') $databases[0]
            }
            'B6BOfficerFoundation' {
                foreach ($file in @('OfficerTermSchemaTest','OfficerTermResolverTest','LegacyOfficerCandidateReportTest','OfficerTermPostgresConcurrencyTest')) {
                    Run-Tests ('b6b-' + $file) @("tests/Feature/$file.php") $databases[0] -RecordFailures
                }
            }
            'B6BCredentials' {
                foreach ($file in @('tests/Feature/AccountActivationTest.php','tests/Feature/AuthApiTest.php','tests/Feature/OrganisationAccessTest.php','tests/Feature/InstanceOrganisationTest.php','tests/Feature/LocalRoleApiPermissionSmokeTest.php','tests/Feature/AuthMeRoleAwareResponseTest.php','tests/Feature/Milestone3hfNormalMemberIdentityTest.php','tests/Feature/Milestone3dqLogout500DefectFixImplementationTest.php','tests/Feature/Milestone3dsSeededPaymentPermissionDefectImplementationTest.php','tests/Feature/Milestone3duPaymentRefreshRetestExecutionTest.php')) {
                    Run-Tests ('b6b-' + [IO.Path]::GetFileNameWithoutExtension($file)) @($file) $databases[0] -RecordFailures
                }
            }
            'B6BCredentialRaces' {
                Run-Tests 'b6b-credential-races' @('tests/Feature/CredentialTransactionConcurrencyTest.php') $databases[0]
            }
            'B6C1' {
                Run-Tests 'b6c1-api' @('tests/Feature/AnnouncementMutationApiTest.php') $databases[0] -RecordFailures
                Run-Tests 'b6c1-management' @('tests/Feature/Milestone3iiwAnnouncementMutationServiceFoundationTest.php') $databases[0] -RecordFailures
                Run-Tests 'b6c1-markers' @('tests/Feature/Milestone3iizbAnnouncementReadStateFoundationTest.php') $databases[0] -RecordFailures
            }
            'B6C1Races' {
                Run-Tests 'b6c1-races' @('tests/Feature/AnnouncementTransactionConcurrencyTest.php') $databases[0]
            }
            'B6ADiagnostic' {
                $env:RUN_B6A_PROFILE_DIAGNOSTIC='1'
                Run-Tests 'historical-profile-inversion' @('tests/Feature/FinancialCrossServiceConcurrencyTest.php','--filter','test_historical_profile_lock_inversion_reports_40p01') $databases[0]
            }
            'B6A' {
                Run-Tests 'b6a-profile-lifecycle' @('tests/Feature/Milestone3hbProfileUpdateRequestLifecycleWorkflowTest.php') $databases[0]
                Run-Tests 'b6a-actor' @('tests/Feature/FinancialActorAdmissionTest.php') $databases[0]
                Run-Tests 'b6a-finance-races' @('tests/Feature/FinancialCrossServiceConcurrencyTest.php','--exclude-group','historical-profile-diagnostic') $databases[0]

            }
            'B5Opening' { Run-Tests 'b5-opening-close' @('tests/Feature/AccountingAdministrationOpeningConcurrencyTest.php') $databases[0] -RecordFailures }
            'B5' { Run-Tests 'b5-historical-provisioning' @('tests/Feature/Milestone3iidLedgerAccountPeriodProvisioningTest.php') $databases[0] -RecordFailures; Run-Tests 'b5-enforcement' @('tests/Feature/AccountingAdministrationEnforcementTest.php') $databases[0] -RecordFailures }
            'B5Outcomes' { Run-Tests 'b5-outcomes' @('tests/Feature/AccountingAdministrationOutcomeTest.php') $databases[0] -RecordFailures }
            'B5Races' { Run-Tests 'b5-races' @('tests/Feature/AccountingAdministrationConcurrencyTest.php') $databases[0] -RecordFailures }
            'B5Retries' { Run-Tests 'b5-retries' @('tests/Feature/AccountingAdministrationRetryTest.php') $databases[0] -RecordFailures }
            'Coverage' { Run-Tests 'coverage-selection' @('tests/Feature/SourceCoverageActivationSelectionTest.php') $databases[0] -RecordFailures }
            'CoverageRaces' { Run-Tests 'coverage-races' @('tests/Feature/SourceCoverageActivationConcurrencyTest.php') $databases[0] -RecordFailures }
            'B4' { Run-Tests 'b4-enforcement' @('tests/Feature/IntegrationConfigurationEnforcementTest.php') $databases[0] -RecordFailures }
            'B4Races' { Run-Tests 'b4-races' @('tests/Feature/IntegrationConfigurationConcurrencyTest.php') $databases[0] -RecordFailures }
            'B4Reactivation' { Run-Tests 'b4-reactivation-blocker' @('tests/Feature/IntegrationConfigurationConcurrencyTest.php','--filter','activate-config-first-1') $databases[0] -RecordFailures }
            'B4Resources' { Run-Tests 'b4-resources' @('tests/Feature/IntegrationConfigurationResourceScopeTest.php') $databases[0] -RecordFailures }
            'B3Foundation' { Run-Tests 'b3-foundation' @('tests/Feature/FinancialTransactionFoundationTest.php') $databases[0] }
            'B3' { Run-Tests 'b3-core' @('tests/Feature/FinancialOrganisationEnforcementTest.php','tests/Feature/FinancialTransactionFoundationTest.php','tests/Feature/Milestone3iicCoreJournalPostingServiceTest.php','tests/Feature/Milestone3iieInternalJournalReversalServiceTest.php','tests/Feature/Milestone3iizkACanonicalAccountingMetadataFundAttributionTest.php') $databases[0] }
            'B3Historical' {
                Run-Tests 'historical-0' @('tests/Feature/Milestone3adPaymentReceiptPdfNarrowImplementationTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-1' @('tests/Feature/Milestone3dsSeededPaymentPermissionDefectImplementationTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-2' @('tests/Feature/Milestone3duPaymentRefreshRetestExecutionTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-3' @('tests/Feature/Milestone3iifInternalAccountingPeriodCloseServiceTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-4' @('tests/Feature/Milestone3iizhChargePaymentAccountingIntegrationTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-5' @('tests/Feature/Milestone3iizjProductionAccountingIntegrationConfigurationAndActivationNarrowImplementationTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-6' @('tests/Feature/Milestone3iizvClubExpenseRecordingTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-7' @('tests/Feature/Milestone3iizwExpenseHistoryCorrectionTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-8' @('tests/Feature/Milestone3iizxInternalCashBankTransferTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-9' @('tests/Feature/Milestone3iizzOrdinaryIncomeReceiptTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-10' @('tests/Feature/Milestone3mPaymentRecordingServiceNarrowImplementationTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-11' @('tests/Feature/Milestone3nPaymentRecordingServiceValidationAndClosureTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-12' @('tests/Feature/Milestone3qPaymentRecordingApiNarrowImplementationTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-13' @('tests/Feature/Milestone3rPaymentRecordingApiValidationAndClosureTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-14' @('tests/Feature/Milestone3uPaymentReceiptGenerationNarrowImplementationTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-15' @('tests/Feature/Milestone3vPaymentReceiptGenerationValidationAndClosureTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-16' @('tests/Feature/Milestone3yPaymentReceiptApiNarrowImplementationTest.php') $databases[0] -RecordFailures
                Run-Tests 'historical-17' @('tests/Feature/Milestone3zPaymentReceiptApiValidationAndClosureTest.php') $databases[0] -RecordFailures
            }
            'B3Repair' {
                Run-Tests 'source-repair' @('tests/Feature/Milestone3iizhChargePaymentAccountingIntegrationTest.php') $databases[0] -RecordFailures
                Run-Tests 'transfer-repair' @('tests/Feature/Milestone3iizxInternalCashBankTransferTest.php') $databases[0] -RecordFailures
                Run-Tests 'receipt-repair' @('tests/Feature/Milestone3zPaymentReceiptApiValidationAndClosureTest.php') $databases[0] -RecordFailures
                Run-Tests 'statement-repair' @('tests/Feature/Milestone3iizzOrdinaryIncomeReceiptTest.php','tests/Feature/Milestone3iizkClubAccountStatementProjectionTest.php','tests/Feature/Milestone3iizlClubAccountStatementProjectionValidationAndClosureTest.php') $databases[0] -RecordFailures
            }
            'B3Fixtures' {
                Run-Tests 'fixture-helper' @('tests/Feature/DisposableCorruptionFixtureTest.php') $databases[0] -RecordFailures
                Run-Tests 'source-corruption' @('tests/Feature/Milestone3iizhChargePaymentAccountingIntegrationTest.php') $databases[0] -RecordFailures
            }
            'B3Poison' { Run-Tests 'fixture-uncertain' @('tests/Feature/DisposableCorruptionFixtureUncertainTest.php','--filter','test_unprovable_restoration') $databases[0] }
            'B3PoisonCommit' { Run-Tests 'fixture-committed' @('tests/Feature/DisposableCorruptionFixtureUncertainTest.php','--filter','test_post_commit_failure') $databases[0] }
            'B3Active' { Run-Tests 'b3-active' @('tests/Feature/FinancialBoundaryMatrixTest.php','--filter','test_active_direct') $databases[0] -RecordFailures }
            'B3Direct' { Run-Tests 'b3-direct' @('tests/Feature/FinancialBoundaryMatrixTest.php') $databases[0] -RecordFailures }
            'B3Contexts' { Run-Tests 'b3-contexts' @('tests/Feature/SourceTransactionContextMatrixTest.php') $databases[0] -RecordFailures }
            'B3Races' { Run-Tests 'b3-suspension-races' @('tests/Feature/FinancialSuspensionConcurrencyTest.php') $databases[0] -RecordFailures }
            'B3Workers' {
                Run-Tests 'collections-workers' @('tests/Feature/Milestone3iizhPostgresConcurrencyTest.php') $databases[0] -RecordFailures
                Run-Tests 'expense-workers' @('tests/Feature/Milestone3iizvPostgresExpenseConcurrencyTest.php') $databases[1] -RecordFailures
                Run-Tests 'expense-reversal-workers' @('tests/Feature/Milestone3iizwPostgresExpenseReversalConcurrencyTest.php') $databases[2] -RecordFailures
            }
            'B3Retries' { Run-Tests 'b3-genuine-retries' @('tests/Feature/FinancialTransactionRetryTest.php') $databases[0] -RecordFailures }
            'B3Resources' { Run-Tests 'b3-resource-lock-scope' @('tests/Feature/FinancialResourceLockScopeTest.php') $databases[0] -RecordFailures }
            'B3Cross' { Run-Tests 'b3-cross-service' @('tests/Feature/FinancialCrossServiceConcurrencyTest.php','--exclude-group','historical-profile-diagnostic') $databases[0] -RecordFailures }
            'B3OpeningPeer' { Run-Tests 'b3-opening-peer' @('tests/Feature/FinancialOpeningPeerConcurrencyTest.php') $databases[0] -RecordFailures }
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
                Run-Tests 'acceptance' @('tests/Feature/Milestone3ijaeOrdinaryIncomeReceiptCorrectionTest.php') $databases[0] -RecordFailures
                Run-Tests 'finance-concurrency' @('tests/Feature/Milestone3ijaePostgresOrdinaryIncomeReceiptCorrectionConcurrencyTest.php') $databases[0] -RecordFailures
                Run-Tests 'transactional' @('tests/Feature/DefaultRolesAndPermissionsSeederTest.php','tests/Feature/ClubPermissionServiceTest.php','tests/Feature/ClubPermissionMiddlewareTest.php','tests/Feature/Milestone3iicCoreJournalPostingServiceTest.php','tests/Feature/Milestone3iieInternalJournalReversalServiceTest.php','tests/Feature/Milestone3iizkACanonicalAccountingMetadataFundAttributionTest.php') $databases[0] -RecordFailures
                Run-Tests 'receipts-statements' @('tests/Feature/Milestone3iizzOrdinaryIncomeReceiptTest.php','tests/Feature/Milestone3iizkClubAccountStatementProjectionTest.php','tests/Feature/Milestone3iizlClubAccountStatementProjectionValidationAndClosureTest.php') $databases[0] -RecordFailures
                Run-Tests 'snapshot-concurrency' @('tests/Feature/Milestone3iiztPostgresStatementSnapshotConcurrencyTest.php') $databases[1] -RecordFailures
            }
        }
    } finally { Pop-Location }
    if ($evidence.fixture_discard_requested) {
        if ($Suite -notin @('B3Poison','B3PoisonCommit') -or $evidence.recorded_test_failure) { throw 'Unexpected fixture discard.' }
        $evidence.status='EXPECTED_DISCARD'
    } else { $evidence.after=Assert-Fresh }
    $afterPaths = @((Get-ChildItem "$repository/backend/app" -Recurse -File -Filter '*.php').FullName) + @((Get-ChildItem "$repository/backend/tests" -Recurse -File -Filter '*.php').FullName)
    if (@(Compare-Object ($sourcePaths.FullName | Sort-Object) ($afterPaths | Sort-Object)).Count -ne 0) { throw 'Source file inventory changed during validation.' }
    foreach ($file in $sourcePaths) { if ((Get-FileHash -LiteralPath $file.FullName -Algorithm SHA256).Hash -cne $before[$file.FullName]) { throw 'Source changed during validation.' } }
    $evidence.source_hashes_unchanged=$true
    if (-not $evidence.fixture_discard_requested) { $evidence.status='PASS' }
    if ($evidence.recorded_test_failure) { $evidence.status='FAILED_OR_BLOCKED'; $evidence.reason='Recorded test failures, errors or skips prevent acceptance. Merge NO-GO.' }
    if ($Suite -in @('C','Integration','B3Races','B3Workers','B3Cross','B3OpeningPeer','B4Races','B4Resources','B4Reactivation','Coverage','CoverageRaces','B5','B5Outcomes','B5Races','B5Opening','B6A','B6C1','B6C1Races', 'B6BCredentials', 'B6BCredentialRaces', 'B6BOfficerFoundation') -and (Select-String -LiteralPath "$output/server.log" -Pattern 'deadlock detected' -Quiet)) {
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
if ($evidence.status -notin @('PASS','EXPECTED_DISCARD')) { throw 'Validation or fresh-cluster shutdown did not fully pass; inspect evidence.' }
