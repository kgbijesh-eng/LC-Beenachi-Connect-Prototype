# P0.2A backend test matrix

Reviewed implementation: `9228c21ebe8987acee1d08b8e08db77803b260aa`. Evidence date: 6 November 2026.

All 261 files were initially invoked separately with forced in-memory SQLite. Raw outcomes remain passed/failed/errors/skipped. Final outcomes select a correct PostgreSQL lane or the clean-commit scope rerun, with one execution per unique class/method/data-set identity. Existing tests and workers were not changed.

**Final: 2,144 unique cases (2,129 historical + 15 new P0.2A), 2,144 passed, 27,863 assertions, zero failures/errors/skips/blocked cases.** This is a reconciliation, not one all-green command.

Raw SQLite: 2,144 cases, 2,096 passed, 27 failed, nine errors, 12 skipped, 26,848 assertions. All retained backend executions including controls/retries: 2,632 executions, 2,548 passed, 54 failures, 18 errors, 12 skips, 35,373 assertions. Do not add these overlapping rows to the unique-case total.

The expense-reversal lane passed on a fresh PostgreSQL cluster at `127.0.0.1:55492` with exact database name and a non-elevated owner/login. The original database and ownership remain untouched. Both ExampleTest files are identified by full paths; a retained JUnit filename collision was repaired with a one-case Feature audit replay.

See [implementation and validation notes](p0-2a-central-organisation-eligibility.md) for safety checks, commands, full execution ledger, original failures, retained logs and P0.2B–D limitations.

| Test file | Cases | Raw P/F/E/S | Final P/F/E/S | Final assertions | Selected lane |
| --- | ---: | --- | --- | ---: | --- |
| tests/Feature/AccountActivationTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 64 | SQLite |
| tests/Feature/AnnouncementMutationApiTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 102 | SQLite |
| tests/Feature/AuthApiTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 19 | SQLite |
| tests/Feature/AuthMeRoleAwareResponseTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 20 | SQLite |
| tests/Feature/ClubPermissionMiddlewareTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 7 | SQLite |
| tests/Feature/ClubPermissionServiceTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 38 | SQLite |
| tests/Feature/ClubRolePermissionAuditFoundationTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 14 | SQLite |
| tests/Feature/DefaultRolesAndPermissionsSeederTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 172 | SQLite |
| tests/Feature/ExampleTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 1 | SQLite |
| tests/Feature/FullProjectDocumentationAndDeploymentStrategyVerificationThrough3azTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 103 | SQLite |
| tests/Feature/HealthEndpointTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 2 | SQLite |
| tests/Feature/InstanceOrganisationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 349 | SQLite |
| tests/Feature/LocalClubAdminBootstrapSeederTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 13 | SQLite |
| tests/Feature/LocalRoleApiPermissionSmokeTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 64 | SQLite |
| tests/Feature/LocalRoleTestUsersSeederTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 12 | SQLite |
| tests/Feature/MemberMasterFoundationTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 16 | SQLite |
| tests/Feature/MemberSelfProfileAuthorizationTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 26 | SQLite |
| tests/Feature/Milestone2eMemberModuleClosureReviewTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 27 | SQLite |
| tests/Feature/Milestone3aaFinanceModuleClosureReviewNextScopeDecisionTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 39 | SQLite |
| tests/Feature/Milestone3abPaymentReceiptPdfScopeGateTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 60 | SQLite |
| tests/Feature/Milestone3acPaymentReceiptPdfImplementationAuthorizationTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 64 | SQLite |
| tests/Feature/Milestone3adPaymentReceiptPdfNarrowImplementationTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 39 | SQLite |
| tests/Feature/Milestone3aePaymentReceiptPdfValidationAndClosureTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 54 | SQLite |
| tests/Feature/Milestone3afFinanceModulePostReceiptClosureReviewTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 91 | SQLite |
| tests/Feature/Milestone3agOutstandingDuesMemberFinanceSummaryScopeGateTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 133 | SQLite |
| tests/Feature/Milestone3ahOutstandingDuesFinanceSummaryReadApiAuthorizationTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 106 | SQLite |
| tests/Feature/Milestone3aiOutstandingDuesFinanceSummaryReadApiNarrowImplementationTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 90 | SQLite |
| tests/Feature/Milestone3ajOutstandingDuesFinanceSummaryReadApiValidationAndClosureTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 96 | SQLite |
| tests/Feature/Milestone3akFinanceModulePostSummaryClosureReviewNextScopeDecisionTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 101 | SQLite |
| tests/Feature/Milestone3alMemberFinanceSummaryFrontendScopeGateTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 99 | SQLite |
| tests/Feature/Milestone3aMemberAccountLedgerDuesScopeGateTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 52 | SQLite |
| tests/Feature/Milestone3anMemberFinanceSummaryFrontendValidationAndClosureTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 33 | SQLite |
| tests/Feature/Milestone3aoFinanceModulePostFrontendClosureReviewNextScopeDecisionTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 75 | SQLite |
| tests/Feature/Milestone3apPaymentRecordingFrontendScopeGateTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 83 | SQLite |
| tests/Feature/Milestone3aqPaymentRecordingFrontendNarrowImplementationAuthorizationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 113 | SQLite |
| tests/Feature/Milestone3arPaymentRecordingFrontendNarrowImplementationTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 47 | SQLite |
| tests/Feature/Milestone3asPaymentRecordingFrontendValidationAndClosureTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 68 | SQLite |
| tests/Feature/Milestone3atFinanceModulePostPaymentRecordingFrontendClosureReviewTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 79 | SQLite |
| tests/Feature/Milestone3auPaymentReceiptFrontendScopeGateTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 27 | SQLite |
| tests/Feature/Milestone3avPaymentReceiptFrontendNarrowImplementationAuthorizationTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 42 | SQLite |
| tests/Feature/Milestone3axPaymentReceiptFrontendValidationAndClosureTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 75 | SQLite |
| tests/Feature/Milestone3ayFinanceModulePostReceiptFrontendClosureReviewTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 94 | SQLite |
| tests/Feature/Milestone3azClubFinanceReportsBalanceSheetScopeGateTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 463 | SQLite |
| tests/Feature/Milestone3baDeploymentSaasStrategyReadinessReviewTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 979 | SQLite |
| tests/Feature/Milestone3bbClubFinanceReportsBalanceSheetReadApiAuthorizationTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 52 | SQLite |
| tests/Feature/Milestone3bcClubFinanceReportsBalanceSheetReadApiNarrowImplementationTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 88 | SQLite |
| tests/Feature/Milestone3bdClubFinanceReportsBalanceSheetReadApiValidationAndClosureTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 36 | SQLite |
| tests/Feature/Milestone3beClubFinanceReportsBalanceSheetFrontendDisplayScopeGateTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 69 | SQLite |
| tests/Feature/Milestone3bfRoadmapCodexDevelopmentGovernanceAuditTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 92 | SQLite |
| tests/Feature/Milestone3bgClubFinanceReportsBalanceSheetFrontendDisplayImplementationAuthorizationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 95 | SQLite |
| tests/Feature/Milestone3bhClubFinanceReportsBalanceSheetFrontendDisplayNarrowImplementationTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 156 | SQLite |
| tests/Feature/Milestone3biClubFinanceReportsBalanceSheetFrontendDisplayValidationAndClosureTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 168 | SQLite |
| tests/Feature/Milestone3bjFinanceModulePostReportsFrontendClosureReviewNextScopeDecisionTest.php | 13 | 13/0/0/0 | 13/0/0/0 | 147 | SQLite |
| tests/Feature/Milestone3bkPaymentHistoryReadOnlyScopeGateTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 169 | SQLite |
| tests/Feature/Milestone3blPaymentHistoryReadOnlyImplementationAuthorizationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 162 | SQLite |
| tests/Feature/Milestone3bMemberAccountLedgerDatabaseFoundationTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 24 | SQLite |
| tests/Feature/Milestone3bmPaymentHistoryReadOnlyNarrowImplementationTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 86 | SQLite |
| tests/Feature/Milestone3bnPaymentHistoryReadOnlyValidationAndClosureTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 164 | SQLite |
| tests/Feature/Milestone3boPaymentHistoryFrontendScopeGateTest.php | 13 | 13/0/0/0 | 13/0/0/0 | 141 | SQLite |
| tests/Feature/Milestone3bpPaymentHistoryFrontendNarrowImplementationTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 155 | SQLite |
| tests/Feature/Milestone3bqPaymentHistoryFrontendValidationAndClosureTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 181 | SQLite |
| tests/Feature/Milestone3brPaymentHistoryLaneClosureReviewTest.php | 16 | 16/0/0/0 | 16/0/0/0 | 183 | SQLite |
| tests/Feature/Milestone3bsMemberDataImportExcelOnboardingScopeGateTest.php | 16 | 16/0/0/0 | 16/0/0/0 | 209 | SQLite |
| tests/Feature/Milestone3btMemberDataImportExcelOnboardingImplementationAuthorizationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 195 | SQLite |
| tests/Feature/Milestone3buMemberDataImportExcelOnboardingNarrowImplementationTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 120 | SQLite |
| tests/Feature/Milestone3bvMemberDataImportExcelOnboardingValidationAndClosureTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 152 | SQLite |
| tests/Feature/Milestone3bwMemberDataImportApiScopeGateTest.php | 13 | 13/0/0/0 | 13/0/0/0 | 209 | SQLite |
| tests/Feature/Milestone3bxMemberDataImportApiImplementationAuthorizationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 245 | SQLite |
| tests/Feature/Milestone3byMemberDataImportApiNarrowImplementationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 137 | SQLite |
| tests/Feature/Milestone3bzMemberDataImportApiValidationAndClosureTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 187 | SQLite |
| tests/Feature/Milestone3caMemberDataImportFrontendScopeGateTest.php | 13 | 13/0/0/0 | 13/0/0/0 | 179 | SQLite |
| tests/Feature/Milestone3cbMemberDataImportFrontendImplementationAuthorizationTest.php | 13 | 13/0/0/0 | 13/0/0/0 | 204 | SQLite |
| tests/Feature/Milestone3ccMemberDataImportFrontendNarrowImplementationTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 164 | SQLite |
| tests/Feature/Milestone3cdMemberDataImportFrontendValidationAndClosureTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 186 | SQLite |
| tests/Feature/Milestone3ceMemberDataImportPreviewModuleClosureReviewTest.php | 15 | 15/0/0/0 | 15/0/0/0 | 213 | SQLite |
| tests/Feature/Milestone3cfMemberDataImportCommitScopeGateTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 165 | SQLite |
| tests/Feature/Milestone3cgMemberDataImportCommitImplementationAuthorizationTest.php | 16 | 16/0/0/0 | 16/0/0/0 | 164 | SQLite |
| tests/Feature/Milestone3chMemberDataImportCommitBackendScopeAndAuthorizationClosureTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 162 | SQLite |
| tests/Feature/Milestone3ciMemberDataImportCommitBackendNarrowImplementationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 104 | SQLite |
| tests/Feature/Milestone3cjMemberDataImportCommitBackendValidationAndClosureTest.php | 17 | 17/0/0/0 | 17/0/0/0 | 736 | SQLite |
| tests/Feature/Milestone3ckMemberDataImportCommitFrontendScopeGateTest.php | 15 | 15/0/0/0 | 15/0/0/0 | 141 | SQLite |
| tests/Feature/Milestone3clMemberDataImportCommitFrontendImplementationAuthorizationTest.php | 15 | 15/0/0/0 | 15/0/0/0 | 184 | SQLite |
| tests/Feature/Milestone3cMemberLedgerBalanceCalculationFoundationTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 34 | SQLite |
| tests/Feature/Milestone3cmMemberDataImportCommitFrontendNarrowImplementationTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 139 | SQLite |
| tests/Feature/Milestone3cnMemberDataImportCommitFrontendValidationAndClosureTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 142 | SQLite |
| tests/Feature/Milestone3coMemberDataImportCommitFlowClosureReviewTest.php | 15 | 15/0/0/0 | 15/0/0/0 | 159 | SQLite |
| tests/Feature/Milestone3cpMemberDataImportModuleClosureReviewTest.php | 17 | 17/0/0/0 | 17/0/0/0 | 225 | SQLite |
| tests/Feature/Milestone3cqPostMemberDataImportModuleNextScopeDecisionTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 151 | SQLite |
| tests/Feature/Milestone3crMemberProfileDocumentsScopeGateTest.php | 17 | 17/0/0/0 | 17/0/0/0 | 158 | SQLite |
| tests/Feature/Milestone3csMemberProfileDocumentsImplementationAuthorizationTest.php | 20 | 20/0/0/0 | 20/0/0/0 | 183 | SQLite |
| tests/Feature/Milestone3ctMemberProfileDocumentsDatabaseFoundationTest.php | 15 | 15/0/0/0 | 15/0/0/0 | 165 | SQLite |
| tests/Feature/Milestone3cuMemberProfileDocumentsDatabaseFoundationValidationAndClosureTest.php | 16 | 16/0/0/0 | 16/0/0/0 | 240 | SQLite |
| tests/Feature/Milestone3cvMemberProfileDocumentsReadApiScopeGateTest.php | 18 | 18/0/0/0 | 18/0/0/0 | 245 | SQLite |
| tests/Feature/Milestone3cwMemberProfileDocumentsReadApiImplementationAuthorizationTest.php | 19 | 19/0/0/0 | 19/0/0/0 | 249 | SQLite |
| tests/Feature/Milestone3cxMemberProfileDocumentsReadApiNarrowImplementationTest.php | 15 | 15/0/0/0 | 15/0/0/0 | 199 | SQLite |
| tests/Feature/Milestone3cyMemberProfileDocumentsReadApiValidationAndClosureTest.php | 18 | 18/0/0/0 | 18/0/0/0 | 276 | SQLite |
| tests/Feature/Milestone3czMemberProfileDocumentsCreateUpdateScopeGateTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 199 | SQLite |
| tests/Feature/Milestone3daMemberProfileDocumentsCreateUpdateImplementationAuthorizationTest.php | 16 | 16/0/0/0 | 16/0/0/0 | 275 | SQLite |
| tests/Feature/Milestone3dbMemberProfileDocumentsCreateUpdateNarrowImplementationTest.php | 13 | 13/0/0/0 | 13/0/0/0 | 317 | SQLite |
| tests/Feature/Milestone3dcMemberProfileDocumentsCreateUpdateValidationAndClosureTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 308 | SQLite |
| tests/Feature/Milestone3ddMemberProfileDocumentsFrontendScopeGateTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 148 | SQLite |
| tests/Feature/Milestone3deMemberProfileDocumentsFrontendImplementationAuthorizationTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 162 | SQLite |
| tests/Feature/Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 225 | SQLite |
| tests/Feature/Milestone3dgMemberProfileDocumentsFrontendValidationAndClosureTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 233 | SQLite |
| tests/Feature/Milestone3dhMvpFrontendUsabilityReadinessScopeGateTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 146 | SQLite |
| tests/Feature/Milestone3diMvpFrontendUsabilityImplementationAuthorizationTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 163 | SQLite |
| tests/Feature/Milestone3djMvpFrontendUsabilityNarrowImplementationTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 129 | SQLite |
| tests/Feature/Milestone3dkMvpFrontendUsabilityValidationAndClosureTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 131 | SQLite |
| tests/Feature/Milestone3dlInternalMvpManualAcceptanceScopeGateTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 134 | SQLite |
| tests/Feature/Milestone3dMemberLedgerBalanceReadApiScopeGateTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 59 | SQLite |
| tests/Feature/Milestone3dmInternalMvpManualAcceptanceAuthorizationTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 159 | SQLite |
| tests/Feature/Milestone3dnInternalMvpManualAcceptanceExecutionTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 169 | SQLite |
| tests/Feature/Milestone3doInternalMvpDefectFixScopeGateTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 86 | SQLite |
| tests/Feature/Milestone3dpLogout500DefectFixScopeGateTest.php | 10 | 9/1/0/0 | 10/0/0/0 | 129 | SQLite, clean commit |
| tests/Feature/Milestone3dqLogout500DefectFixImplementationTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 43 | SQLite |
| tests/Feature/Milestone3drSeededPaymentPermissionDefectScopeGateTest.php | 9 | 8/1/0/0 | 9/0/0/0 | 120 | SQLite, clean commit |
| tests/Feature/Milestone3dsSeededPaymentPermissionDefectImplementationTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 39 | SQLite |
| tests/Feature/Milestone3dtPaymentRefreshRetestScopeGateTest.php | 9 | 8/1/0/0 | 9/0/0/0 | 127 | SQLite, clean commit |
| tests/Feature/Milestone3duPaymentRefreshRetestExecutionTest.php | 4 | 3/1/0/0 | 4/0/0/0 | 86 | SQLite, clean commit |
| tests/Feature/Milestone3dvMemberEditFlowDefectScopeGateTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 74 | SQLite, clean commit |
| tests/Feature/Milestone3dwMemberEditFlowInvestigationRetestExecutionTest.php | 5 | 4/1/0/0 | 5/0/0/0 | 85 | SQLite, clean commit |
| tests/Feature/Milestone3dxMemberEditFlowDefectImplementationScopeGateTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 58 | SQLite |
| tests/Feature/Milestone3dyMemberEditFlowFrontendImplementationTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 49 | SQLite |
| tests/Feature/Milestone3dzMemberEditFlowFrontendRetestClosureTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 60 | SQLite |
| tests/Feature/Milestone3eaMemberEditFlowRetestBlockerResolutionScopeGateTest.php | 4 | 3/1/0/0 | 4/0/0/0 | 106 | SQLite, clean commit |
| tests/Feature/Milestone3ebMemberEditFlowManualBrowserRetestExecutionTest.php | 4 | 3/1/0/0 | 4/0/0/0 | 73 | SQLite, clean commit |
| tests/Feature/Milestone3ecMemberEditFlowManualRetestFailureFixScopeGateTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 78 | SQLite, clean commit |
| tests/Feature/Milestone3edMemberEditFlowFrontendPostSaveConsistencyFixTest.php | 4 | 3/1/0/0 | 4/0/0/0 | 85 | SQLite, clean commit |
| tests/Feature/Milestone3eeInternalMvpDefectClosureGoNoGoReviewTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 64 | SQLite, clean commit |
| tests/Feature/Milestone3efInternalMvpControlledUseHandoffOperatingChecklistTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 88 | SQLite, clean commit |
| tests/Feature/Milestone3egInternalMvpControlledUseExecutionScopeGateTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 74 | SQLite, clean commit |
| tests/Feature/Milestone3ehInternalMvpControlledUseExecutionTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 78 | SQLite, clean commit |
| tests/Feature/Milestone3eiControlledUseExecutionEvidenceCorrectionHumanOperatorRetestScopeGateTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 61 | SQLite, clean commit |
| tests/Feature/Milestone3ejHumanOperatorControlledUseExecutionTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 77 | SQLite, clean commit |
| tests/Feature/Milestone3ekHumanOperatorControlledUseFailureReviewScopeGateTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 69 | SQLite, clean commit |
| tests/Feature/Milestone3elMemberDirectorySelectedMemberReadFailureFixAuthorizationTest.php | 3 | 2/1/0/0 | 3/0/0/0 | 80 | SQLite, clean commit |
| tests/Feature/Milestone3eMemberLedgerBalanceReadApiNarrowImplementationTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 44 | SQLite |
| tests/Feature/Milestone3emMemberDirectorySelectedMemberReadFailureNarrowImplementationTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 50 | SQLite |
| tests/Feature/Milestone3enMemberDirectorySelectedMemberReadFailureFixClosureReviewTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 82 | SQLite |
| tests/Feature/Milestone3eoMemberDirectorySelectedMemberReadHumanOperatorRetestScopeGateTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 70 | SQLite |
| tests/Feature/Milestone3fbMemberProfileSchemaAndImportContractScopeGateTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 148 | SQLite |
| tests/Feature/Milestone3fcMemberProfileSchemaAndImportContractImplementationTest.php | 15 | 15/0/0/0 | 15/0/0/0 | 163 | SQLite |
| tests/Feature/Milestone3fdMemberProfileSchemaAndImportContractValidationAndClosureTest.php | 16 | 16/0/0/0 | 16/0/0/0 | 195 | SQLite |
| tests/Feature/Milestone3feCurrent47MemberImportExecutionTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 99 | SQLite |
| tests/Feature/Milestone3fMemberLedgerBalanceReadApiValidationAndClosureTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 51 | SQLite |
| tests/Feature/Milestone3fmEventsMeetingsBackendActivationScopeGateTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 111 | SQLite |
| tests/Feature/Milestone3fnEventsMeetingsBackendAuthorizationContractApprovalTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 157 | SQLite |
| tests/Feature/Milestone3foEventsMeetingsDatabaseReadApiFoundationTest.php | 13 | 13/0/0/0 | 13/0/0/0 | 129 | SQLite |
| tests/Feature/Milestone3fpaEventsMeetingsIsolatedBrowserPermissionAlignmentTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 315 | SQLite |
| tests/Feature/Milestone3fpEventsMeetingsLifecycleBackendWorkflowTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 81 | SQLite |
| tests/Feature/Milestone3fqa3fpaGovernanceTestLifecycleRepairTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 86 | SQLite |
| tests/Feature/Milestone3frEventsMeetingsBrowserRoleVerificationTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 75 | SQLite |
| tests/Feature/Milestone3fsEventsMeetingsClosureReviewTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 86 | SQLite |
| tests/Feature/Milestone3ftProjectsActivitiesBackendActivationScopeGateTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 100 | SQLite |
| tests/Feature/Milestone3fuProjectsActivitiesAuthorizationContractApprovalTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 121 | SQLite |
| tests/Feature/Milestone3fvProjectsActivitiesDatabaseReadApiFoundationTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 136 | SQLite |
| tests/Feature/Milestone3fwProjectsActivitiesLifecycleBackendWorkflowTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 82 | SQLite |
| tests/Feature/Milestone3fxProjectsActivitiesFrontendIntegrationTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 48 | SQLite |
| tests/Feature/Milestone3fyProjectsActivitiesBrowserRoleVerificationTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 59 | SQLite |
| tests/Feature/Milestone3fzProjectsActivitiesClosureReviewTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 62 | SQLite |
| tests/Feature/Milestone3gaRemindersBackendActivationScopeGateTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 75 | SQLite |
| tests/Feature/Milestone3gbRemindersAuthorizationContractApprovalTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 61 | SQLite |
| tests/Feature/Milestone3gcRemindersDatabaseReadApiFoundationTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 93 | SQLite |
| tests/Feature/Milestone3gdRemindersLifecycleBackendWorkflowTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 41 | SQLite |
| tests/Feature/Milestone3geRemindersFrontendIntegrationTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 14 | SQLite |
| tests/Feature/Milestone3gfRemindersBrowserRoleVerificationTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 15 | SQLite |
| tests/Feature/Milestone3ggaRemindersAuthorizationPermissionRegressionRepairTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 13 | SQLite |
| tests/Feature/Milestone3ggRemindersClosureReviewTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 27 | SQLite |
| tests/Feature/Milestone3ghReportsScopeGateDataProvenanceReviewTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 29 | SQLite |
| tests/Feature/Milestone3giReportsAuthorizationApiContractApprovalTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 23 | SQLite |
| tests/Feature/Milestone3gjReportsCatalogSharedQueryFoundationTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 15 | SQLite |
| tests/Feature/Milestone3gkVerifiedReportSetBackendActivationTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 16 | SQLite |
| tests/Feature/Milestone3glReportsFrontendIntegrationTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 15 | SQLite |
| tests/Feature/Milestone3gmReportsBrowserRoleVerificationTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 50 | SQLite |
| tests/Feature/Milestone3gnReportsClosureReviewTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 41 | SQLite |
| tests/Feature/Milestone3goFinanceBackendActivationScopeGateTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 31 | SQLite |
| tests/Feature/Milestone3gPaymentRecordingScopeGateTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 73 | SQLite |
| tests/Feature/Milestone3gpFinanceAuthorizationApiContractTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 33 | SQLite |
| tests/Feature/Milestone3gqFinanceDatabaseReadApiFoundationTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 40 | SQLite |
| tests/Feature/Milestone3grFinanceCollectionsReceiptsWorkflowsTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 44 | SQLite |
| tests/Feature/Milestone3gsFinanceFrontendIntegrationTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 21 | SQLite |
| tests/Feature/Milestone3gtFinanceBrowserRoleAccuracyVerificationTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 32 | SQLite |
| tests/Feature/Milestone3guFinanceClosureReviewTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 44 | SQLite |
| tests/Feature/Milestone3gvFinancialReportsContractActivationTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 24 | SQLite |
| tests/Feature/Milestone3gwFinancialReportsImplementationBrowserVerificationTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 76 | SQLite |
| tests/Feature/Milestone3gxFinancialReportsClosureReviewTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 51 | SQLite |
| tests/Feature/Milestone3gyProfileUpdateRequestScopeGateTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 36 | SQLite |
| tests/Feature/Milestone3gzProfileUpdateRequestAuthorizationContractTest.php | 3 | 3/0/0/0 | 3/0/0/0 | 43 | SQLite |
| tests/Feature/Milestone3haProfileUpdateRequestDatabaseReadFoundationTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 38 | SQLite |
| tests/Feature/Milestone3hbProfileUpdateRequestLifecycleWorkflowTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 59 | SQLite |
| tests/Feature/Milestone3hcProfileUpdateRequestFrontendIntegrationTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 15 | SQLite |
| tests/Feature/Milestone3hdProfileUpdateRequestBrowserRoleVerificationTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 14 | SQLite |
| tests/Feature/Milestone3heProfileUpdateRequestClosureReviewTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 13 | SQLite |
| tests/Feature/Milestone3hfNormalMemberIdentityTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 70 | SQLite |
| tests/Feature/Milestone3hmMemberProfileSelfServiceContextFixTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 8 | SQLite |
| tests/Feature/Milestone3hPaymentRecordingDatabaseFoundationAuthorizationTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 113 | SQLite |
| tests/Feature/Milestone3ibEventsMeetingsReportingConsistencyTest.php | 2 | 2/0/0/0 | 2/0/0/0 | 13 | SQLite |
| tests/Feature/Milestone3icProjectsActivitiesConsistencyTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 6 | SQLite |
| tests/Feature/Milestone3iibClubJournalSchemaFoundationTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 110 | SQLite |
| tests/Feature/Milestone3iicCoreJournalPostingServiceTest.php | 20 | 20/0/0/0 | 20/0/0/0 | 88 | SQLite |
| tests/Feature/Milestone3iidLedgerAccountPeriodProvisioningTest.php | 17 | 17/0/0/0 | 17/0/0/0 | 87 | SQLite |
| tests/Feature/Milestone3iieInternalJournalReversalServiceTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 60 | SQLite |
| tests/Feature/Milestone3iifInternalAccountingPeriodCloseServiceTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 23 | SQLite |
| tests/Feature/Milestone3iigInternalJournalAccountActivityQueryTest.php | 12 | 12/0/0/0 | 12/0/0/0 | 95 | SQLite |
| tests/Feature/Milestone3iihInternalJournalPeriodMovementSummaryTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 72 | SQLite |
| tests/Feature/Milestone3iikApprovedChartTemplateFoundationTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 490 | SQLite |
| tests/Feature/Milestone3iilControlledInactiveAccountProvisioningPlanTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 69 | SQLite |
| tests/Feature/Milestone3iimOpeningBalanceValidationFoundationTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 71 | SQLite |
| tests/Feature/Milestone3iioBloodDonorConsentSelfServiceAlignmentTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 11 | SQLite |
| tests/Feature/Milestone3iipPersonalProfileDocumentsNavigationAlignmentTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 14 | SQLite |
| tests/Feature/Milestone3iisAnnouncementsDatabaseReadApiFoundationTest.php | 13 | 13/0/0/0 | 13/0/0/0 | 226 | SQLite |
| tests/Feature/Milestone3iiuAnnouncementDecisionPermissionFoundationTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 124 | SQLite |
| tests/Feature/Milestone3iiwAnnouncementMutationServiceFoundationTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 77 | SQLite |
| tests/Feature/Milestone3iizbAnnouncementReadStateFoundationTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 66 | SQLite |
| tests/Feature/Milestone3iizeCutoverPackagePersistenceAndPreviewTest.php | 16 | 16/0/0/0 | 16/0/0/0 | 93 | SQLite |
| tests/Feature/Milestone3iizfCutoverAuthorizationAndLifecycleContractTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 47 | SQLite |
| tests/Feature/Milestone3iizgOpeningJournalAndOneTimeActivationTest.php | 25 | 25/0/0/0 | 25/0/0/0 | 123 | SQLite |
| tests/Feature/Milestone3iizgPostgresConcurrencyTest.php | 7 | 0/0/0/7 | 7/0/0/0 | 219 | PostgreSQL supplementary |
| tests/Feature/Milestone3iizhChargePaymentAccountingIntegrationTest.php | 33 | 33/0/0/0 | 33/0/0/0 | 173 | SQLite |
| tests/Feature/Milestone3iizhPostgresConcurrencyTest.php | 4 | 0/0/0/4 | 4/0/0/0 | 61 | PostgreSQL supplementary |
| tests/Feature/Milestone3iiziProductionAccountingIntegrationAuthorityAndDeliveryContractTest.php | 6 | 5/1/0/0 | 6/0/0/0 | 61 | SQLite, clean commit |
| tests/Feature/Milestone3iizjProductionAccountingIntegrationConfigurationAndActivationNarrowImplementationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 108 | SQLite |
| tests/Feature/Milestone3iizkACanonicalAccountingMetadataFundAttributionTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 90 | SQLite |
| tests/Feature/Milestone3iizkClubAccountStatementProjectionTest.php | 21 | 21/0/0/0 | 21/0/0/0 | 218 | SQLite |
| tests/Feature/Milestone3iizlClubAccountStatementProjectionValidationAndClosureTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 51 | SQLite |
| tests/Feature/Milestone3iizsCutoverAdministrationInternalEnforcementFoundationTest.php | 18 | 18/0/0/0 | 18/0/0/0 | 85 | SQLite |
| tests/Feature/Milestone3iiztPostgresStatementSnapshotConcurrencyTest.php | 1 | 0/0/0/1 | 1/0/0/0 | 11 | PostgreSQL canonical |
| tests/Feature/Milestone3iizvClubExpenseRecordingTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 58 | SQLite |
| tests/Feature/Milestone3iizvPostgresExpenseConcurrencyTest.php | 3 | 0/3/0/0 | 3/0/0/0 | 46 | PostgreSQL supplementary |
| tests/Feature/Milestone3iizwExpenseHistoryCorrectionTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 70 | SQLite |
| tests/Feature/Milestone3iizwPostgresExpenseReversalConcurrencyTest.php | 1 | 0/1/0/0 | 1/0/0/0 | 27 | Fresh isolated PostgreSQL :55492 |
| tests/Feature/Milestone3iizxInternalCashBankTransferTest.php | 4 | 4/0/0/0 | 4/0/0/0 | 52 | SQLite |
| tests/Feature/Milestone3iizxPostgresTransferConcurrencyTest.php | 2 | 0/2/0/0 | 2/0/0/0 | 26 | PostgreSQL supplementary |
| tests/Feature/Milestone3iizzOrdinaryIncomeReceiptTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 65 | SQLite |
| tests/Feature/Milestone3iizzPostgresOrdinaryIncomeReceiptConcurrencyTest.php | 1 | 0/1/0/0 | 1/0/0/0 | 28 | PostgreSQL supplementary |
| tests/Feature/Milestone3ijaeOrdinaryIncomeReceiptCorrectionTest.php | 10 | 1/0/9/0 | 10/0/0/0 | 288 | PostgreSQL canonical |
| tests/Feature/Milestone3ijaePostgresOrdinaryIncomeReceiptCorrectionConcurrencyTest.php | 1 | 0/1/0/0 | 1/0/0/0 | 77 | PostgreSQL canonical |
| tests/Feature/Milestone3iPaymentRecordingDatabaseFoundationImplementationTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 112 | SQLite |
| tests/Feature/Milestone3jPaymentRecordingDatabaseFoundationValidationAndClosureTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 104 | SQLite |
| tests/Feature/Milestone3kPaymentRecordingServiceScopeGateTest.php | 13 | 13/0/0/0 | 13/0/0/0 | 111 | SQLite |
| tests/Feature/Milestone3lPaymentRecordingServiceImplementationAuthorizationTest.php | 14 | 14/0/0/0 | 14/0/0/0 | 131 | SQLite |
| tests/Feature/Milestone3mPaymentRecordingServiceNarrowImplementationTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 44 | SQLite |
| tests/Feature/Milestone3nPaymentRecordingServiceValidationAndClosureTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 87 | SQLite |
| tests/Feature/Milestone3oPaymentRecordingApiScopeGateTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 84 | SQLite |
| tests/Feature/Milestone3pPaymentRecordingApiImplementationAuthorizationTest.php | 11 | 11/0/0/0 | 11/0/0/0 | 100 | SQLite |
| tests/Feature/Milestone3qPaymentRecordingApiNarrowImplementationTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 66 | SQLite |
| tests/Feature/Milestone3rPaymentRecordingApiValidationAndClosureTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 103 | SQLite |
| tests/Feature/Milestone3sPaymentReceiptGenerationScopeGateTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 45 | SQLite |
| tests/Feature/Milestone3tPaymentReceiptGenerationImplementationAuthorizationTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 84 | SQLite |
| tests/Feature/Milestone3uPaymentReceiptGenerationNarrowImplementationTest.php | 8 | 8/0/0/0 | 8/0/0/0 | 52 | SQLite |
| tests/Feature/Milestone3vPaymentReceiptGenerationValidationAndClosureTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 56 | SQLite |
| tests/Feature/Milestone3wPaymentReceiptApiScopeGateTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 55 | SQLite |
| tests/Feature/Milestone3xPaymentReceiptApiImplementationAuthorizationTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 60 | SQLite |
| tests/Feature/Milestone3yPaymentReceiptApiNarrowImplementationTest.php | 10 | 10/0/0/0 | 10/0/0/0 | 75 | SQLite |
| tests/Feature/Milestone3zPaymentReceiptApiValidationAndClosureTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 82 | SQLite |
| tests/Feature/OrganisationAccessTest.php | 15 | 15/0/0/0 | 15/0/0/0 | 683 | SQLite |
| tests/Feature/ProtectedClubFoundationRoutesTest.php | 5 | 5/0/0/0 | 5/0/0/0 | 27 | SQLite |
| tests/Feature/ProtectedMemberCreateRouteTest.php | 7 | 7/0/0/0 | 7/0/0/0 | 28 | SQLite |
| tests/Feature/ProtectedMemberReadRoutesTest.php | 6 | 6/0/0/0 | 6/0/0/0 | 22 | SQLite |
| tests/Feature/ProtectedMemberUpdateRouteTest.php | 9 | 9/0/0/0 | 9/0/0/0 | 30 | SQLite |
| tests/Unit/ExampleTest.php | 1 | 1/0/0/0 | 1/0/0/0 | 1 | SQLite |
