[CmdletBinding()]
param(
    [switch]$SkipPostgres,
    [switch]$CreateDisposableDatabase,
    [switch]$AllowFreshExistingDisposableDatabase,
    [string]$DatabaseName,
    [string]$DatabaseAdministratorUsername,
    [string]$PhpPath,
    [string]$ReportPath,
    [switch]$EnvironmentRestorationProbe,
    [switch]$ForceProbeFailure
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$Repository = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$Backend = Join-Path $Repository 'backend'
$ProtectedDatabases = @('lc_beenachi_connect', 'lc_beenachi_connect_reconstructed_20260727')
$RequiredPrefix = 'lcb_3ijae_finance_'
$SnapshotDatabase = 'lcb_3iizt_statement_snapshot_concurrency_test'
$Results = [System.Collections.Generic.List[object]]::new()
$CreatedDatabases = [System.Collections.Generic.List[string]]::new()
$EnvironmentNames = @(
    'PATH', 'PGPASSWORD', 'APP_ENV', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT',
    'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
    'RUN_3IJAE_POSTGRES_CONCURRENCY', 'RUN_3IIZT_POSTGRES_CONCURRENCY'
)
$OriginalEnvironment = @{}
foreach ($Name in $EnvironmentNames) {
    $Exists = Test-Path "Env:$Name"
    $OriginalEnvironment[$Name] = @{
        exists = $Exists
        value = if ($Exists) { [string](Get-Item "Env:$Name").Value } else { $null }
    }
}

function Add-Result([string]$Gate, [string]$Status, [hashtable]$Evidence) {
    $Results.Add([pscustomobject]@{ gate = $Gate; status = $Status; evidence = $Evidence })
}

function Get-PropertyValue($Object, [string]$Name, $Default = 0) {
    $Property = $Object.PSObject.Properties[$Name]
    if ($null -eq $Property) { return $Default }
    return $Property.Value
}

function Resolve-Executable([string[]]$Names, [string[]]$Candidates = @()) {
    foreach ($Name in $Names) {
        $Command = Get-Command $Name -ErrorAction SilentlyContinue
        if ($Command -and $Command.Source -and (Test-Path -LiteralPath $Command.Source -PathType Leaf)) {
            return (Resolve-Path -LiteralPath $Command.Source).Path
        }
    }
    foreach ($Candidate in $Candidates) {
        if ($Candidate -and (Test-Path -LiteralPath $Candidate -PathType Leaf)) {
            return (Resolve-Path -LiteralPath $Candidate).Path
        }
    }
    return $null
}

function Get-DotEnvValue([string]$Path, [string]$Name) {
    foreach ($Line in Get-Content -LiteralPath $Path -ErrorAction SilentlyContinue) {
        if ($Line -match '^\s*([^#=]+)=(.*)$' -and $Matches[1].Trim() -ceq $Name) {
            return $Matches[2].Trim().Trim('"').Trim("'")
        }
    }
    return $null
}

function Assert-ReportLocation([string]$Path) {
    $Parent = Split-Path -Parent $Path
    if (-not $Parent -or -not (Test-Path -LiteralPath $Parent -PathType Container)) {
        throw 'Report parent directory must already exist.'
    }
    $ResolvedParent = (Resolve-Path -LiteralPath $Parent).Path.TrimEnd('\')
    if ($ResolvedParent -eq $Repository -or $ResolvedParent.StartsWith("$Repository\", [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Finance release-gate reports must be written outside the repository.'
    }
}

function Assert-DisposableDatabase([string]$Name, [switch]$Snapshot) {
    $Expected = if ($Snapshot) { $SnapshotDatabase } else { $null }
    if ($Snapshot) {
        if ($Name -cne $Expected) { throw "Snapshot database must be exactly '$Expected'." }
    } elseif ([string]::IsNullOrWhiteSpace($Name) -or -not $Name.StartsWith($RequiredPrefix, [StringComparison]::Ordinal)) {
        throw "Database must use the '$RequiredPrefix' disposable namespace."
    }
    if ($Name -notmatch '^[a-z0-9_]+$' -or $Name.Length -gt 63 -or $Name -in $ProtectedDatabases) {
        throw 'Database name is unsafe or protected.'
    }
}

function Invoke-Captured([scriptblock]$Command) {
    $PreviousErrorActionPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $Output = @(& $Command 2>&1)
        $ExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $PreviousErrorActionPreference
    }
    $Output | ForEach-Object { Write-Host $_ }
    return @{ output = @($Output | ForEach-Object { [string]$_ }); exit_code = $ExitCode }
}

function Invoke-SimpleGate([string]$Name, [scriptblock]$Command) {
    Write-Host "`n===== $Name ====="
    $Run = Invoke-Captured $Command
    $Status = if ($Run.exit_code -eq 0) { 'PASS' } else { 'FAIL' }
    Add-Result $Name $Status @{ exit_code = $Run.exit_code }
    if ($Run.exit_code -ne 0) { throw "$Name failed with exit code $($Run.exit_code)." }
}

function Invoke-PhpTestGroup([string]$Name, [string[]]$Files, [switch]$AllowSkipped) {
    Write-Host "`n===== $Name ====="
    $Run = Invoke-Captured { & $script:Php artisan test @Files --colors=never }
    $SummaryLine = $Run.output | Where-Object { $_.Trim().StartsWith('{') -and $_ -match '"tool"\s*:\s*"phpunit"' } | Select-Object -Last 1
    if (-not $SummaryLine) { throw "$Name did not emit the required structured PHPUnit summary." }
    $Summary = $SummaryLine | ConvertFrom-Json
    $Skipped = [int](Get-PropertyValue $Summary 'skipped' 0)
    $Evidence = @{
        exit_code = $Run.exit_code
        tests = [int](Get-PropertyValue $Summary 'tests' 0)
        passed = [int](Get-PropertyValue $Summary 'passed' 0)
        failed = [int](Get-PropertyValue $Summary 'failed' 0)
        errors = [int](Get-PropertyValue $Summary 'errors' 0)
        assertions = [int](Get-PropertyValue $Summary 'assertions' 0)
        skipped = $Skipped
        duration_ms = [int](Get-PropertyValue $Summary 'duration_ms' 0)
    }
    $Status = if ($Run.exit_code -eq 0 -and ($AllowSkipped -or $Skipped -eq 0)) { 'PASS' } else { if ($Skipped -gt 0) { 'BLOCKED' } else { 'FAIL' } }
    Add-Result $Name $Status $Evidence
    if ($Status -ne 'PASS') { throw "$Name failed, errored, or skipped required coverage." }
}

function Invoke-PsqlScalar([string]$Database, [string]$Sql, [string]$Username) {
    $Output = @(& $script:Psql --host=$script:PgHostName --port=$script:PgPort --username=$Username --dbname=$Database --no-password --no-psqlrc --tuples-only --no-align --set=ON_ERROR_STOP=1 --command=$Sql 2>&1)
    if ($LASTEXITCODE -ne 0) { throw "PostgreSQL metadata query failed: $($Output -join ' ')" }
    return (($Output | ForEach-Object { [string]$_ }) -join "`n").Trim()
}

function Get-PostgresSafetyEvidence([string]$Phase, [string[]]$Targets) {
    $TargetRows = foreach ($Target in $Targets) {
        Assert-DisposableDatabase $Target -Snapshot:($Target -eq $SnapshotDatabase)
        $Row = Invoke-PsqlScalar 'postgres' "SELECT datname || '|' || pg_catalog.pg_get_userbyid(datdba) FROM pg_database WHERE datname = '$Target';" $script:PgUser
        if (-not $Row) { throw "Disposable target '$Target' does not exist." }
        $Parts = $Row.Split('|')
        if ($Parts[1] -cne $script:PgUser) { throw "Disposable target '$Target' has an unexpected owner." }
        @{ database = $Parts[0]; owner = $Parts[1] }
    }
    $ProtectedRows = foreach ($Protected in $ProtectedDatabases) {
        $Exists = Invoke-PsqlScalar 'postgres' "SELECT count(*) FROM pg_database WHERE datname = '$Protected';" $script:PgUser
        if ($Exists -ne '1') { throw "Protected database '$Protected' is not positively present." }
        @{ database = $Protected; exists = $true }
    }
    $Role = Invoke-PsqlScalar 'postgres' "SELECT rolname || '|' || rolcreatedb || '|' || rolsuper || '|' || rolcreaterole || '|' || rolreplication || '|' || rolbypassrls FROM pg_roles WHERE rolname = current_user;" $script:PgUser
    $RoleParts = $Role.Split('|')
    if ($RoleParts.Count -ne 6 -or @($RoleParts[1..5] | Where-Object { $_ -notin @('f', 'false') }).Count -gt 0) {
        throw 'Application-role isolation proof failed.'
    }
    $ServerVersion = Invoke-PsqlScalar 'postgres' 'SHOW server_version;' $script:PgUser
    Add-Result "PostgreSQL safety $Phase" 'PASS' @{
        client_version = $script:PostgresClientVersion
        server_version = $ServerVersion
        targets = @($TargetRows)
        protected_databases = @($ProtectedRows)
        application_role = @{
            name = $RoleParts[0]; createdb = $false; superuser = $false; createrole = $false
            replication = $false; bypassrls = $false
        }
    }
}

function Set-ApplicationDatabaseEnvironment([string]$Target) {
    $env:APP_ENV = 'testing'
    $env:DB_CONNECTION = 'pgsql'
    $env:DB_HOST = $script:PgHostName
    $env:DB_PORT = $script:PgPort
    $env:DB_DATABASE = $Target
    $env:DB_USERNAME = $script:PgUser
    $env:DB_PASSWORD = $script:PgPassword
    if ($script:PgPassword) { $env:PGPASSWORD = $script:PgPassword } else { Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue }
}

function New-OrValidateDatabase([string]$Target, [switch]$Snapshot) {
    Assert-DisposableDatabase $Target -Snapshot:$Snapshot
    $Exists = Invoke-PsqlScalar 'postgres' "SELECT count(*) FROM pg_database WHERE datname = '$Target';" $script:PgUser
    if ($CreateDisposableDatabase) {
        if ($Exists -eq '1') {
            if (-not $AllowFreshExistingDisposableDatabase) { throw "Create was requested but '$Target' already exists." }
        } else {
            if ([string]::IsNullOrWhiteSpace($DatabaseAdministratorUsername)) { throw 'Database creation requires -DatabaseAdministratorUsername.' }
            Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
            & $script:Createdb --host=$script:PgHostName --port=$script:PgPort --username=$DatabaseAdministratorUsername --no-password --encoding=UTF8 --owner=$script:PgUser $Target
            if ($LASTEXITCODE -ne 0) { throw "Disposable database creation failed for '$Target'." }
            $CreatedDatabases.Add($Target)
            if ($script:PgPassword) { $env:PGPASSWORD = $script:PgPassword }
        }
    } else {
        if ($Exists -ne '1') { throw "Disposable database '$Target' does not exist." }
        if (-not $AllowFreshExistingDisposableDatabase) { throw 'Refusing migrate:fresh on an existing target without -AllowFreshExistingDisposableDatabase.' }
    }
}

if (-not $ReportPath) { $ReportPath = Join-Path ([IO.Path]::GetTempPath()) 'finance-release-gate-report.json' }
Assert-ReportLocation $ReportPath
Set-Location $Repository

try {
    if ($EnvironmentRestorationProbe) {
        foreach ($Name in $EnvironmentNames) { Set-Item "Env:$Name" "finance-probe-$Name" }
        Add-Result 'environment restoration probe' 'PASS' @{ forced_failure = [bool]$ForceProbeFailure }
        if ($ForceProbeFailure) { throw 'Forced environment-restoration probe failure.' }
        return
    }
    Invoke-SimpleGate 'git diff check' { git diff --check }
    Add-Result 'repository identity' 'PASS' @{ branch = (git branch --show-current).Trim(); head = (git rev-parse HEAD).Trim() }

    $Npm = Resolve-Executable @('npm.cmd', 'npm')
    if (-not $Npm) { throw 'npm was not found.' }
    if (-not $PhpPath) {
        $PhpPath = Resolve-Executable @('php.exe', 'php')
        if (-not $PhpPath) {
            $PhpPath = Get-ChildItem "$env:USERPROFILE\.config\herd*","$env:LOCALAPPDATA\Herd" -Filter php.exe -File -Recurse -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty FullName
        }
    }
    if (-not $PhpPath -or -not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'PHP was not found; pass -PhpPath.' }
    $script:Php = (Resolve-Path -LiteralPath $PhpPath).Path
    $env:PATH = "$(Split-Path -Parent $script:Php);$env:PATH"
    $Composer = Resolve-Executable @('composer.bat', 'composer.cmd', 'composer.exe', 'composer') @('C:\ProgramData\ComposerSetup\bin\composer.bat')
    if (-not $Composer) { throw 'Composer was not found.' }

    Invoke-SimpleGate 'Composer manifest validation' { Push-Location $Backend; try { & $Composer validate --strict --no-check-publish } finally { Pop-Location } }
    Invoke-SimpleGate 'root npm manifest validation' { & $Npm pkg get name }

    Write-Host "`n===== Composer locked dependency audit ====="
    $ComposerAudit = Invoke-Captured { Push-Location $Backend; try { & $Composer audit --locked --format=json } finally { Pop-Location } }
    $ComposerJson = ($ComposerAudit.output -join "`n") | ConvertFrom-Json
    $ComposerPackages = @($ComposerJson.advisories.PSObject.Properties)
    $ComposerAdvisories = @($ComposerPackages | ForEach-Object { $Package = $_.Name; @($_.Value | ForEach-Object { @{ package = $Package; advisory_id = $_.advisoryId; severity = $_.severity; affected_versions = $_.affectedVersions; title = $_.title; cve = $_.cve } }) })
    $ComposerSeverity = @{}; foreach ($Item in $ComposerAdvisories) { $Key = if ($Item.severity) { $Item.severity } else { 'unknown' }; $ComposerSeverity[$Key] = 1 + [int]$ComposerSeverity[$Key] }
    Add-Result 'Composer locked dependency audit' $(if ($ComposerAudit.exit_code -eq 0) { 'PASS' } else { 'FAIL' }) @{ exit_code = $ComposerAudit.exit_code; advisory_count = $ComposerAdvisories.Count; package_count = $ComposerPackages.Count; severity = $ComposerSeverity; advisories = $ComposerAdvisories; abandoned = @($ComposerJson.abandoned) }

    Write-Host "`n===== root npm locked dependency audit ====="
    $NpmAudit = Invoke-Captured { & $Npm audit --json }
    $NpmJson = ($NpmAudit.output -join "`n") | ConvertFrom-Json
    $NpmFindings = @($NpmJson.vulnerabilities.PSObject.Properties | ForEach-Object { $Finding = $_.Value; @{ package = $_.Name; severity = $Finding.severity; direct = [bool]$Finding.isDirect; fix_available = $Finding.fixAvailable } })
    Add-Result 'root npm locked dependency audit' $(if ($NpmAudit.exit_code -eq 0) { 'PASS' } else { 'FAIL' }) @{ exit_code = $NpmAudit.exit_code; metadata = $NpmJson.metadata; findings = $NpmFindings }

    if (Test-Path (Join-Path $Backend 'package-lock.json')) {
        Write-Host "`n===== backend npm locked dependency audit ====="
        $BackendAudit = Invoke-Captured { Push-Location $Backend; try { & $Npm audit --json } finally { Pop-Location } }
        $BackendJson = ($BackendAudit.output -join "`n") | ConvertFrom-Json
        Add-Result 'backend npm locked dependency audit' $(if ($BackendAudit.exit_code -eq 0) { 'PASS' } else { 'FAIL' }) @{ exit_code = $BackendAudit.exit_code; metadata = $BackendJson.metadata; vulnerabilities = $BackendJson.vulnerabilities }
    } else {
        Add-Result 'backend npm locked dependency audit' 'BLOCKED' @{ error_code = 'ENOLOCK'; manifest = 'backend/package.json'; lockfile = 'backend/package-lock.json'; lockfile_exists = $false }
    }

    Invoke-SimpleGate 'frontend tests' { & $Npm run test:frontend -- --run }
    Invoke-SimpleGate 'frontend typecheck' { & $Npm exec tsc -- --noEmit }
    Write-Host "`n===== frontend lint ====="
    $Lint = Invoke-Captured { & $Npm run lint }
    $LintText = $Lint.output -join "`n"
    $Errors = 0; $Warnings = 0
    if ($LintText -match '(?m)\d+\s+problems?\s+\((\d+)\s+errors?,\s+(\d+)\s+warnings?\)') { $Errors = [int]$Matches[1]; $Warnings = [int]$Matches[2] }
    $LintStatus = if ($Lint.exit_code -ne 0 -or $Errors -gt 0) { 'FAIL' } elseif ($Warnings -gt 0) { 'PASS WITH EXISTING WARNINGS' } else { 'PASS' }
    Add-Result 'frontend lint' $LintStatus @{ exit_code = $Lint.exit_code; errors = $Errors; warnings = $Warnings }
    if ($Lint.exit_code -ne 0 -or $Errors -gt 0) { throw 'Frontend lint failed.' }
    Invoke-SimpleGate 'frontend production build' { & $Npm run build }

    if ($SkipPostgres) {
        Add-Result 'disposable PostgreSQL finance proof' 'BLOCKED' @{ reason = 'explicitly skipped' }
    } else {
        Assert-DisposableDatabase $DatabaseName
        $EnvFile = Join-Path $Backend '.env'
        $script:PgHostName = Get-DotEnvValue $EnvFile 'DB_HOST'
        $script:PgPort = Get-DotEnvValue $EnvFile 'DB_PORT'
        $script:PgUser = Get-DotEnvValue $EnvFile 'DB_USERNAME'
        $script:PgPassword = Get-DotEnvValue $EnvFile 'DB_PASSWORD'
        $script:Psql = Resolve-Executable @('psql.exe', 'psql') @('C:\Program Files\PostgreSQL\17\bin\psql.exe')
        $script:Createdb = Resolve-Executable @('createdb.exe', 'createdb') @('C:\Program Files\PostgreSQL\17\bin\createdb.exe')
        if (-not $script:Psql -or ($CreateDisposableDatabase -and -not $script:Createdb)) { throw 'Required PostgreSQL client tools were not found.' }
        $script:PostgresClientVersion = ((& $script:Psql --version) -join ' ').Trim()
        if ($script:PgPassword) { $env:PGPASSWORD = $script:PgPassword }

        New-OrValidateDatabase $DatabaseName
        New-OrValidateDatabase $SnapshotDatabase -Snapshot
        Get-PostgresSafetyEvidence 'before' @($DatabaseName, $SnapshotDatabase)

        Push-Location $Backend
        try {
            Set-ApplicationDatabaseEnvironment $DatabaseName
            Invoke-SimpleGate 'primary PostgreSQL migrations' { & $script:Php artisan migrate:fresh --force --no-ansi }
            Invoke-PhpTestGroup '3IJAE acceptance' @('tests/Feature/Milestone3ijaeOrdinaryIncomeReceiptCorrectionTest.php')
            $env:RUN_3IJAE_POSTGRES_CONCURRENCY = '1'
            Invoke-PhpTestGroup '3IJAE PostgreSQL concurrency' @('tests/Feature/Milestone3ijaePostgresOrdinaryIncomeReceiptCorrectionConcurrencyTest.php')
            Remove-Item Env:RUN_3IJAE_POSTGRES_CONCURRENCY -ErrorAction SilentlyContinue
            Invoke-PhpTestGroup 'transactional finance regression' @(
                'tests/Feature/DefaultRolesAndPermissionsSeederTest.php',
                'tests/Feature/ClubPermissionServiceTest.php',
                'tests/Feature/ClubPermissionMiddlewareTest.php',
                'tests/Feature/Milestone3iicCoreJournalPostingServiceTest.php',
                'tests/Feature/Milestone3iieInternalJournalReversalServiceTest.php',
                'tests/Feature/Milestone3iizkACanonicalAccountingMetadataFundAttributionTest.php'
            )
            Invoke-PhpTestGroup 'non-transactional receipt and statement regression' @(
                'tests/Feature/Milestone3iizzOrdinaryIncomeReceiptTest.php',
                'tests/Feature/Milestone3iizkClubAccountStatementProjectionTest.php',
                'tests/Feature/Milestone3iizlClubAccountStatementProjectionValidationAndClosureTest.php'
            )

            Set-ApplicationDatabaseEnvironment $SnapshotDatabase
            Invoke-SimpleGate '3IIZT PostgreSQL migrations' { & $script:Php artisan migrate:fresh --force --no-ansi }
            $env:RUN_3IIZT_POSTGRES_CONCURRENCY = '1'
            Invoke-PhpTestGroup '3IIZT PostgreSQL snapshot concurrency' @('tests/Feature/Milestone3iiztPostgresStatementSnapshotConcurrencyTest.php')
            Remove-Item Env:RUN_3IIZT_POSTGRES_CONCURRENCY -ErrorAction SilentlyContinue
        } finally {
            Pop-Location
        }
        if ($script:PgPassword) { $env:PGPASSWORD = $script:PgPassword }
        Get-PostgresSafetyEvidence 'after' @($DatabaseName, $SnapshotDatabase)
        Add-Result 'disposable database retention' 'PASS' @{ databases = @($DatabaseName, $SnapshotDatabase); created_by_verifier = @($CreatedDatabases); dropped = @() }
    }

    if ($Results.Where({ $_.status -in @('FAIL', 'BLOCKED') }).Count -gt 0) { throw 'One or more required finance release gates failed or were blocked.' }
} catch {
    Add-Result 'verifier execution' 'FAIL' @{ message = $_.Exception.Message }
    throw
} finally {
    foreach ($Name in $EnvironmentNames) {
        $Original = $OriginalEnvironment[$Name]
        if ($Original.exists) { Set-Item "Env:$Name" $Original.value } else { Remove-Item "Env:$Name" -ErrorAction SilentlyContinue }
    }
    $Results | ConvertTo-Json -Depth 12 | Set-Content -LiteralPath $ReportPath -Encoding utf8
    $Results | Select-Object gate, status | Format-Table -AutoSize
    Write-Host "Report: $ReportPath"
    Write-Host 'Reports are written outside the repository. The verifier never drops a database.'
}
