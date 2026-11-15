[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$baseline = 'a2ab08c6cf7ea50e2b2db3058a93b552a5836ee1'
$allowlist = @(
    'app/ClubAccountStatementBackend.tsx'
    'app/ClubExpenseBackend.tsx'
    'app/ClubExpenseHistoryBackend.tsx'
    'app/ClubInternalTransferBackend.tsx'
    'app/ClubOrdinaryIncomeReceiptBackend.tsx'
    'app/ClubOrdinaryIncomeReceiptHistoryBackend.tsx'
    'app/DevAuthSession.tsx'
    'app/FinanceBackend.tsx'
    'app/page.tsx'
    'app/ProfileUpdatesBackend.tsx'
    'app/RemindersBackend.tsx'
    'app/ReportsBackend.tsx'
    'backend/app/Http/Controllers/Api/V1/Auth/AuthController.php'
    'backend/app/Http/Controllers/Api/V1/Club/ClubController.php'
    'backend/app/Http/Controllers/Api/V1/Events/ClubEventController.php'
    'backend/app/Http/Controllers/Api/V1/Finance/ClubFinanceReportController.php'
    'backend/app/Http/Controllers/Api/V1/Finance/MemberFinanceSummaryController.php'
    'backend/app/Http/Controllers/Api/V1/Finance/MemberLedgerBalanceController.php'
    'backend/app/Http/Controllers/Api/V1/Finance/PaymentHistoryController.php'
    'backend/app/Http/Controllers/Api/V1/Member/MemberController.php'
    'backend/app/Http/Controllers/Api/V1/Members/MemberProfileDocumentController.php'
    'backend/app/Http/Controllers/Api/V1/Projects/ClubProjectController.php'
    'backend/app/Http/Controllers/Api/V1/Reminders/ClubReminderController.php'
    'backend/app/Http/Requests/Members/MemberDataImportCommitRequest.php'
    'backend/app/Http/Requests/Members/MemberDataImportPreviewRequest.php'
    'backend/app/Services/Access/ClubPermissionService.php'
    'backend/app/Services/Announcements/ClubAnnouncementReadStateService.php'
    'backend/app/Services/Announcements/ClubAnnouncementWriteService.php'
    'backend/app/Services/Events/ClubEventWriteService.php'
    'backend/app/Services/Finance/AccountingIntegrationProposalService.php'
    'backend/app/Services/Finance/ClubAccountingCutoverPackageService.php'
    'backend/app/Services/Finance/ClubCutoverAuthorityAdministrationService.php'
    'backend/app/Services/Finance/ClubCutoverAuthorityReadService.php'
    'backend/app/Services/Finance/ClubCutoverAuthorityResolver.php'
    'backend/app/Services/Finance/ClubCutoverEvidenceVerificationService.php'
    'backend/app/Services/Finance/ClubCutoverLifecycleService.php'
    'backend/app/Services/Finance/ClubCutoverPackageBootstrapService.php'
    'backend/app/Services/Finance/ClubOpeningJournalActivationService.php'
    'backend/app/Services/Finance/ClubOrdinaryIncomeReceiptCorrectionService.php'
    'backend/app/Services/Finance/ClubOrdinaryIncomeReceiptService.php'
    'backend/app/Services/Finance/ReceiptGenerationService.php'
    'backend/app/Services/Members/MemberDataImportCommitService.php'
    'backend/app/Services/ProfileUpdates/ProfileUpdateWriteService.php'
    'backend/app/Services/Projects/ClubProjectWriteService.php'
    'backend/app/Services/Reminders/ClubReminderWriteService.php'
)
function Read-GitPaths {
    param([string[]]$Arguments)
    $paths = & git -C (Join-Path $PSScriptRoot '..') @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Git scope check failed: $($Arguments -join ' ')" }
    $paths
}
$base = Read-GitPaths @('merge-base', $baseline, 'HEAD')
if ($base -cne $baseline) { throw 'Slice B baseline is not an ancestor of HEAD.' }
$changed = @(Read-GitPaths @('diff', '--name-only', '--no-renames', $baseline, 'HEAD'))
$changed += @(Read-GitPaths @('diff', '--cached', '--name-only', '--no-renames'))
$changed += @(Read-GitPaths @('diff', '--name-only', '--no-renames'))
$changed += @(Read-GitPaths @('ls-files', '--others', '--exclude-standard'))
$outside = @($changed | Sort-Object -Unique | Where-Object {
    $_ -match '^backend/database/(migrations|schema)/' -or
    ($_ -match '^(app|backend/app)/' -and $_ -cnotmatch '^app/__tests__/' -and $_ -cnotin $allowlist)
})
if ($outside.Count) { throw "Out-of-scope Slice B paths: $($outside -join ', ')" }
