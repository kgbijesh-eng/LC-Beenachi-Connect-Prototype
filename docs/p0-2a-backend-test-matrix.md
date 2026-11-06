# P0.2A backend test matrix

Base: `eee782adde5c37895cb016b300069ac262a78f31`. Evidence date: 6 November 2026.

Each of the 261 test files was invoked separately with forced in-memory SQLite. Raw outcomes are retained as passed/failed/errors/skipped. Final outcomes select the appropriate PostgreSQL lane or the clean-commit rerun of worktree-scope assertions; they are a per-file reconciliation, not one all-green command. Existing tests were not changed.

Raw: 2,144 cases; 2,096 passed, 27 failed, nine errors, 12 skipped. Final coverage: 2,143 passed, zero unresolved application failures/errors/skips, one BLOCKED PostgreSQL expense-reversal case. The blocker is not a pass: its required database had an unexpected owner and was not migrated or tested.

See [implementation and validation notes](p0-2a-central-organisation-eligibility.md) for commands, controls, retained logs, disposable targets and limitations.

| Test class | Cases | Raw P/F/E/S | Final P/F/E/S | Blocked | Selected lane |
| --- | ---: | --- | --- | ---: | --- |
| AccountActivationTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| AnnouncementMutationApiTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| AuthApiTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| AuthMeRoleAwareResponseTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| ClubPermissionMiddlewareTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| ClubPermissionServiceTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| ClubRolePermissionAuditFoundationTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| DefaultRolesAndPermissionsSeederTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| ExampleTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| FullProjectDocumentationAndDeploymentStrategyVerificationThrough3azTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| HealthEndpointTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| InstanceOrganisationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| LocalClubAdminBootstrapSeederTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| LocalRoleApiPermissionSmokeTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| LocalRoleTestUsersSeederTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| MemberMasterFoundationTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| MemberSelfProfileAuthorizationTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone2eMemberModuleClosureReviewTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3aaFinanceModuleClosureReviewNextScopeDecisionTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3abPaymentReceiptPdfScopeGateTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3acPaymentReceiptPdfImplementationAuthorizationTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3adPaymentReceiptPdfNarrowImplementationTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3aePaymentReceiptPdfValidationAndClosureTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| Milestone3afFinanceModulePostReceiptClosureReviewTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3agOutstandingDuesMemberFinanceSummaryScopeGateTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3ahOutstandingDuesFinanceSummaryReadApiAuthorizationTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3aiOutstandingDuesFinanceSummaryReadApiNarrowImplementationTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3ajOutstandingDuesFinanceSummaryReadApiValidationAndClosureTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3akFinanceModulePostSummaryClosureReviewNextScopeDecisionTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3alMemberFinanceSummaryFrontendScopeGateTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3aMemberAccountLedgerDuesScopeGateTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3anMemberFinanceSummaryFrontendValidationAndClosureTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| Milestone3aoFinanceModulePostFrontendClosureReviewNextScopeDecisionTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3apPaymentRecordingFrontendScopeGateTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3aqPaymentRecordingFrontendNarrowImplementationAuthorizationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3arPaymentRecordingFrontendNarrowImplementationTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| Milestone3asPaymentRecordingFrontendValidationAndClosureTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3atFinanceModulePostPaymentRecordingFrontendClosureReviewTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3auPaymentReceiptFrontendScopeGateTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3avPaymentReceiptFrontendNarrowImplementationAuthorizationTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3axPaymentReceiptFrontendValidationAndClosureTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3ayFinanceModulePostReceiptFrontendClosureReviewTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3azClubFinanceReportsBalanceSheetScopeGateTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3baDeploymentSaasStrategyReadinessReviewTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3bbClubFinanceReportsBalanceSheetReadApiAuthorizationTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3bcClubFinanceReportsBalanceSheetReadApiNarrowImplementationTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3bdClubFinanceReportsBalanceSheetReadApiValidationAndClosureTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3beClubFinanceReportsBalanceSheetFrontendDisplayScopeGateTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3bfRoadmapCodexDevelopmentGovernanceAuditTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3bgClubFinanceReportsBalanceSheetFrontendDisplayImplementationAuthorizationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3bhClubFinanceReportsBalanceSheetFrontendDisplayNarrowImplementationTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3biClubFinanceReportsBalanceSheetFrontendDisplayValidationAndClosureTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3bjFinanceModulePostReportsFrontendClosureReviewNextScopeDecisionTest | 13 | 13/0/0/0 | 13/0/0/0 | 0 | SQLite |
| Milestone3bkPaymentHistoryReadOnlyScopeGateTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3blPaymentHistoryReadOnlyImplementationAuthorizationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3bMemberAccountLedgerDatabaseFoundationTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3bmPaymentHistoryReadOnlyNarrowImplementationTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3bnPaymentHistoryReadOnlyValidationAndClosureTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3boPaymentHistoryFrontendScopeGateTest | 13 | 13/0/0/0 | 13/0/0/0 | 0 | SQLite |
| Milestone3bpPaymentHistoryFrontendNarrowImplementationTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3bqPaymentHistoryFrontendValidationAndClosureTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3brPaymentHistoryLaneClosureReviewTest | 16 | 16/0/0/0 | 16/0/0/0 | 0 | SQLite |
| Milestone3bsMemberDataImportExcelOnboardingScopeGateTest | 16 | 16/0/0/0 | 16/0/0/0 | 0 | SQLite |
| Milestone3btMemberDataImportExcelOnboardingImplementationAuthorizationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3buMemberDataImportExcelOnboardingNarrowImplementationTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3bvMemberDataImportExcelOnboardingValidationAndClosureTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3bwMemberDataImportApiScopeGateTest | 13 | 13/0/0/0 | 13/0/0/0 | 0 | SQLite |
| Milestone3bxMemberDataImportApiImplementationAuthorizationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3byMemberDataImportApiNarrowImplementationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3bzMemberDataImportApiValidationAndClosureTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3caMemberDataImportFrontendScopeGateTest | 13 | 13/0/0/0 | 13/0/0/0 | 0 | SQLite |
| Milestone3cbMemberDataImportFrontendImplementationAuthorizationTest | 13 | 13/0/0/0 | 13/0/0/0 | 0 | SQLite |
| Milestone3ccMemberDataImportFrontendNarrowImplementationTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3cdMemberDataImportFrontendValidationAndClosureTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3ceMemberDataImportPreviewModuleClosureReviewTest | 15 | 15/0/0/0 | 15/0/0/0 | 0 | SQLite |
| Milestone3cfMemberDataImportCommitScopeGateTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3cgMemberDataImportCommitImplementationAuthorizationTest | 16 | 16/0/0/0 | 16/0/0/0 | 0 | SQLite |
| Milestone3chMemberDataImportCommitBackendScopeAndAuthorizationClosureTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3ciMemberDataImportCommitBackendNarrowImplementationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3cjMemberDataImportCommitBackendValidationAndClosureTest | 17 | 17/0/0/0 | 17/0/0/0 | 0 | SQLite |
| Milestone3ckMemberDataImportCommitFrontendScopeGateTest | 15 | 15/0/0/0 | 15/0/0/0 | 0 | SQLite |
| Milestone3clMemberDataImportCommitFrontendImplementationAuthorizationTest | 15 | 15/0/0/0 | 15/0/0/0 | 0 | SQLite |
| Milestone3cMemberLedgerBalanceCalculationFoundationTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3cmMemberDataImportCommitFrontendNarrowImplementationTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3cnMemberDataImportCommitFrontendValidationAndClosureTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3coMemberDataImportCommitFlowClosureReviewTest | 15 | 15/0/0/0 | 15/0/0/0 | 0 | SQLite |
| Milestone3cpMemberDataImportModuleClosureReviewTest | 17 | 17/0/0/0 | 17/0/0/0 | 0 | SQLite |
| Milestone3cqPostMemberDataImportModuleNextScopeDecisionTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3crMemberProfileDocumentsScopeGateTest | 17 | 17/0/0/0 | 17/0/0/0 | 0 | SQLite |
| Milestone3csMemberProfileDocumentsImplementationAuthorizationTest | 20 | 20/0/0/0 | 20/0/0/0 | 0 | SQLite |
| Milestone3ctMemberProfileDocumentsDatabaseFoundationTest | 15 | 15/0/0/0 | 15/0/0/0 | 0 | SQLite |
| Milestone3cuMemberProfileDocumentsDatabaseFoundationValidationAndClosureTest | 16 | 16/0/0/0 | 16/0/0/0 | 0 | SQLite |
| Milestone3cvMemberProfileDocumentsReadApiScopeGateTest | 18 | 18/0/0/0 | 18/0/0/0 | 0 | SQLite |
| Milestone3cwMemberProfileDocumentsReadApiImplementationAuthorizationTest | 19 | 19/0/0/0 | 19/0/0/0 | 0 | SQLite |
| Milestone3cxMemberProfileDocumentsReadApiNarrowImplementationTest | 15 | 15/0/0/0 | 15/0/0/0 | 0 | SQLite |
| Milestone3cyMemberProfileDocumentsReadApiValidationAndClosureTest | 18 | 18/0/0/0 | 18/0/0/0 | 0 | SQLite |
| Milestone3czMemberProfileDocumentsCreateUpdateScopeGateTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3daMemberProfileDocumentsCreateUpdateImplementationAuthorizationTest | 16 | 16/0/0/0 | 16/0/0/0 | 0 | SQLite |
| Milestone3dbMemberProfileDocumentsCreateUpdateNarrowImplementationTest | 13 | 13/0/0/0 | 13/0/0/0 | 0 | SQLite |
| Milestone3dcMemberProfileDocumentsCreateUpdateValidationAndClosureTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3ddMemberProfileDocumentsFrontendScopeGateTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3deMemberProfileDocumentsFrontendImplementationAuthorizationTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3dfMemberProfileDocumentsFrontendNarrowImplementationTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3dgMemberProfileDocumentsFrontendValidationAndClosureTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3dhMvpFrontendUsabilityReadinessScopeGateTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3diMvpFrontendUsabilityImplementationAuthorizationTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3djMvpFrontendUsabilityNarrowImplementationTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3dkMvpFrontendUsabilityValidationAndClosureTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3dlInternalMvpManualAcceptanceScopeGateTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3dMemberLedgerBalanceReadApiScopeGateTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3dmInternalMvpManualAcceptanceAuthorizationTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3dnInternalMvpManualAcceptanceExecutionTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3doInternalMvpDefectFixScopeGateTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3dpLogout500DefectFixScopeGateTest | 10 | 9/1/0/0 | 10/0/0/0 | 0 | SQLite, clean commit |
| Milestone3dqLogout500DefectFixImplementationTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3drSeededPaymentPermissionDefectScopeGateTest | 9 | 8/1/0/0 | 9/0/0/0 | 0 | SQLite, clean commit |
| Milestone3dsSeededPaymentPermissionDefectImplementationTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3dtPaymentRefreshRetestScopeGateTest | 9 | 8/1/0/0 | 9/0/0/0 | 0 | SQLite, clean commit |
| Milestone3duPaymentRefreshRetestExecutionTest | 4 | 3/1/0/0 | 4/0/0/0 | 0 | SQLite, clean commit |
| Milestone3dvMemberEditFlowDefectScopeGateTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3dwMemberEditFlowInvestigationRetestExecutionTest | 5 | 4/1/0/0 | 5/0/0/0 | 0 | SQLite, clean commit |
| Milestone3dxMemberEditFlowDefectImplementationScopeGateTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3dyMemberEditFlowFrontendImplementationTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3dzMemberEditFlowFrontendRetestClosureTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3eaMemberEditFlowRetestBlockerResolutionScopeGateTest | 4 | 3/1/0/0 | 4/0/0/0 | 0 | SQLite, clean commit |
| Milestone3ebMemberEditFlowManualBrowserRetestExecutionTest | 4 | 3/1/0/0 | 4/0/0/0 | 0 | SQLite, clean commit |
| Milestone3ecMemberEditFlowManualRetestFailureFixScopeGateTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3edMemberEditFlowFrontendPostSaveConsistencyFixTest | 4 | 3/1/0/0 | 4/0/0/0 | 0 | SQLite, clean commit |
| Milestone3eeInternalMvpDefectClosureGoNoGoReviewTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3efInternalMvpControlledUseHandoffOperatingChecklistTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3egInternalMvpControlledUseExecutionScopeGateTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3ehInternalMvpControlledUseExecutionTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3eiControlledUseExecutionEvidenceCorrectionHumanOperatorRetestScopeGateTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3ejHumanOperatorControlledUseExecutionTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3ekHumanOperatorControlledUseFailureReviewScopeGateTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3elMemberDirectorySelectedMemberReadFailureFixAuthorizationTest | 3 | 2/1/0/0 | 3/0/0/0 | 0 | SQLite, clean commit |
| Milestone3eMemberLedgerBalanceReadApiNarrowImplementationTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3emMemberDirectorySelectedMemberReadFailureNarrowImplementationTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3enMemberDirectorySelectedMemberReadFailureFixClosureReviewTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3eoMemberDirectorySelectedMemberReadHumanOperatorRetestScopeGateTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3fbMemberProfileSchemaAndImportContractScopeGateTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3fcMemberProfileSchemaAndImportContractImplementationTest | 15 | 15/0/0/0 | 15/0/0/0 | 0 | SQLite |
| Milestone3fdMemberProfileSchemaAndImportContractValidationAndClosureTest | 16 | 16/0/0/0 | 16/0/0/0 | 0 | SQLite |
| Milestone3feCurrent47MemberImportExecutionTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3fMemberLedgerBalanceReadApiValidationAndClosureTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3fmEventsMeetingsBackendActivationScopeGateTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3fnEventsMeetingsBackendAuthorizationContractApprovalTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3foEventsMeetingsDatabaseReadApiFoundationTest | 13 | 13/0/0/0 | 13/0/0/0 | 0 | SQLite |
| Milestone3fpaEventsMeetingsIsolatedBrowserPermissionAlignmentTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| Milestone3fpEventsMeetingsLifecycleBackendWorkflowTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3fqa3fpaGovernanceTestLifecycleRepairTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3frEventsMeetingsBrowserRoleVerificationTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3fsEventsMeetingsClosureReviewTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3ftProjectsActivitiesBackendActivationScopeGateTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3fuProjectsActivitiesAuthorizationContractApprovalTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3fvProjectsActivitiesDatabaseReadApiFoundationTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3fwProjectsActivitiesLifecycleBackendWorkflowTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3fxProjectsActivitiesFrontendIntegrationTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3fyProjectsActivitiesBrowserRoleVerificationTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3fzProjectsActivitiesClosureReviewTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3gaRemindersBackendActivationScopeGateTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| Milestone3gbRemindersAuthorizationContractApprovalTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3gcRemindersDatabaseReadApiFoundationTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| Milestone3gdRemindersLifecycleBackendWorkflowTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3geRemindersFrontendIntegrationTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3gfRemindersBrowserRoleVerificationTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3ggaRemindersAuthorizationPermissionRegressionRepairTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3ggRemindersClosureReviewTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3ghReportsScopeGateDataProvenanceReviewTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3giReportsAuthorizationApiContractApprovalTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3gjReportsCatalogSharedQueryFoundationTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3gkVerifiedReportSetBackendActivationTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3glReportsFrontendIntegrationTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3gmReportsBrowserRoleVerificationTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3gnReportsClosureReviewTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3goFinanceBackendActivationScopeGateTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3gPaymentRecordingScopeGateTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3gpFinanceAuthorizationApiContractTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3gqFinanceDatabaseReadApiFoundationTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3grFinanceCollectionsReceiptsWorkflowsTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3gsFinanceFrontendIntegrationTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3gtFinanceBrowserRoleAccuracyVerificationTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3guFinanceClosureReviewTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3gvFinancialReportsContractActivationTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3gwFinancialReportsImplementationBrowserVerificationTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3gxFinancialReportsClosureReviewTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3gyProfileUpdateRequestScopeGateTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3gzProfileUpdateRequestAuthorizationContractTest | 3 | 3/0/0/0 | 3/0/0/0 | 0 | SQLite |
| Milestone3haProfileUpdateRequestDatabaseReadFoundationTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3hbProfileUpdateRequestLifecycleWorkflowTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3hcProfileUpdateRequestFrontendIntegrationTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3hdProfileUpdateRequestBrowserRoleVerificationTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3heProfileUpdateRequestClosureReviewTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3hfNormalMemberIdentityTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| Milestone3hmMemberProfileSelfServiceContextFixTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3hPaymentRecordingDatabaseFoundationAuthorizationTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3ibEventsMeetingsReportingConsistencyTest | 2 | 2/0/0/0 | 2/0/0/0 | 0 | SQLite |
| Milestone3icProjectsActivitiesConsistencyTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3iibClubJournalSchemaFoundationTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3iicCoreJournalPostingServiceTest | 20 | 20/0/0/0 | 20/0/0/0 | 0 | SQLite |
| Milestone3iidLedgerAccountPeriodProvisioningTest | 17 | 17/0/0/0 | 17/0/0/0 | 0 | SQLite |
| Milestone3iieInternalJournalReversalServiceTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3iifInternalAccountingPeriodCloseServiceTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3iigInternalJournalAccountActivityQueryTest | 12 | 12/0/0/0 | 12/0/0/0 | 0 | SQLite |
| Milestone3iihInternalJournalPeriodMovementSummaryTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3iikApprovedChartTemplateFoundationTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3iilControlledInactiveAccountProvisioningPlanTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3iimOpeningBalanceValidationFoundationTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3iioBloodDonorConsentSelfServiceAlignmentTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3iipPersonalProfileDocumentsNavigationAlignmentTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
| Milestone3iisAnnouncementsDatabaseReadApiFoundationTest | 13 | 13/0/0/0 | 13/0/0/0 | 0 | SQLite |
| Milestone3iiuAnnouncementDecisionPermissionFoundationTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3iiwAnnouncementMutationServiceFoundationTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3iizbAnnouncementReadStateFoundationTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3iizeCutoverPackagePersistenceAndPreviewTest | 16 | 16/0/0/0 | 16/0/0/0 | 0 | SQLite |
| Milestone3iizfCutoverAuthorizationAndLifecycleContractTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3iizgOpeningJournalAndOneTimeActivationTest | 25 | 25/0/0/0 | 25/0/0/0 | 0 | SQLite |
| Milestone3iizgPostgresConcurrencyTest | 7 | 0/0/0/7 | 7/0/0/0 | 0 | PostgreSQL supplementary |
| Milestone3iizhChargePaymentAccountingIntegrationTest | 33 | 33/0/0/0 | 33/0/0/0 | 0 | SQLite |
| Milestone3iizhPostgresConcurrencyTest | 4 | 0/0/0/4 | 4/0/0/0 | 0 | PostgreSQL supplementary |
| Milestone3iiziProductionAccountingIntegrationAuthorityAndDeliveryContractTest | 6 | 5/1/0/0 | 6/0/0/0 | 0 | SQLite, clean commit |
| Milestone3iizjProductionAccountingIntegrationConfigurationAndActivationNarrowImplementationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3iizkACanonicalAccountingMetadataFundAttributionTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| Milestone3iizkClubAccountStatementProjectionTest | 21 | 21/0/0/0 | 21/0/0/0 | 0 | SQLite |
| Milestone3iizlClubAccountStatementProjectionValidationAndClosureTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3iizsCutoverAdministrationInternalEnforcementFoundationTest | 18 | 18/0/0/0 | 18/0/0/0 | 0 | SQLite |
| Milestone3iiztPostgresStatementSnapshotConcurrencyTest | 1 | 0/0/0/1 | 1/0/0/0 | 0 | PostgreSQL canonical |
| Milestone3iizvClubExpenseRecordingTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3iizvPostgresExpenseConcurrencyTest | 3 | 0/3/0/0 | 3/0/0/0 | 0 | PostgreSQL supplementary |
| Milestone3iizwExpenseHistoryCorrectionTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3iizwPostgresExpenseReversalConcurrencyTest | 1 | 0/1/0/0 | 0/0/0/0 | 1 | PostgreSQL ownership BLOCKED |
| Milestone3iizxInternalCashBankTransferTest | 4 | 4/0/0/0 | 4/0/0/0 | 0 | SQLite |
| Milestone3iizxPostgresTransferConcurrencyTest | 2 | 0/2/0/0 | 2/0/0/0 | 0 | PostgreSQL supplementary |
| Milestone3iizzOrdinaryIncomeReceiptTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3iizzPostgresOrdinaryIncomeReceiptConcurrencyTest | 1 | 0/1/0/0 | 1/0/0/0 | 0 | PostgreSQL supplementary |
| Milestone3ijaeOrdinaryIncomeReceiptCorrectionTest | 10 | 1/0/9/0 | 10/0/0/0 | 0 | PostgreSQL canonical |
| Milestone3ijaePostgresOrdinaryIncomeReceiptCorrectionConcurrencyTest | 1 | 0/1/0/0 | 1/0/0/0 | 0 | PostgreSQL canonical |
| Milestone3iPaymentRecordingDatabaseFoundationImplementationTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3jPaymentRecordingDatabaseFoundationValidationAndClosureTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3kPaymentRecordingServiceScopeGateTest | 13 | 13/0/0/0 | 13/0/0/0 | 0 | SQLite |
| Milestone3lPaymentRecordingServiceImplementationAuthorizationTest | 14 | 14/0/0/0 | 14/0/0/0 | 0 | SQLite |
| Milestone3mPaymentRecordingServiceNarrowImplementationTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| Milestone3nPaymentRecordingServiceValidationAndClosureTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3oPaymentRecordingApiScopeGateTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| Milestone3pPaymentRecordingApiImplementationAuthorizationTest | 11 | 11/0/0/0 | 11/0/0/0 | 0 | SQLite |
| Milestone3qPaymentRecordingApiNarrowImplementationTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3rPaymentRecordingApiValidationAndClosureTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3sPaymentReceiptGenerationScopeGateTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3tPaymentReceiptGenerationImplementationAuthorizationTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3uPaymentReceiptGenerationNarrowImplementationTest | 8 | 8/0/0/0 | 8/0/0/0 | 0 | SQLite |
| Milestone3vPaymentReceiptGenerationValidationAndClosureTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3wPaymentReceiptApiScopeGateTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| Milestone3xPaymentReceiptApiImplementationAuthorizationTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| Milestone3yPaymentReceiptApiNarrowImplementationTest | 10 | 10/0/0/0 | 10/0/0/0 | 0 | SQLite |
| Milestone3zPaymentReceiptApiValidationAndClosureTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| OrganisationAccessTest | 15 | 15/0/0/0 | 15/0/0/0 | 0 | SQLite |
| ProtectedClubFoundationRoutesTest | 5 | 5/0/0/0 | 5/0/0/0 | 0 | SQLite |
| ProtectedMemberCreateRouteTest | 7 | 7/0/0/0 | 7/0/0/0 | 0 | SQLite |
| ProtectedMemberReadRoutesTest | 6 | 6/0/0/0 | 6/0/0/0 | 0 | SQLite |
| ProtectedMemberUpdateRouteTest | 9 | 9/0/0/0 | 9/0/0/0 | 0 | SQLite |
| ExampleTest | 1 | 1/0/0/0 | 1/0/0/0 | 0 | SQLite |
