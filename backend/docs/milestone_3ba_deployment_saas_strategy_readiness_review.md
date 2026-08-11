# Milestone 3BA - Deployment and SaaS Strategy Readiness Review

Status: STRATEGY REVIEW ONLY

Milestone 3BA records the deployment and SaaS strategy decision after the full project documentation and deployment strategy audit through 3AZ.

This milestone is docs/tests-only.

No runtime implementation is allowed in 3BA.

## Audit base

3BA starts from the completed audit branch:

audit/full-project-documentation-and-deployment-strategy-verification-through-3az

Audit commit:

f6ecafb72148b030c90ab5d60ca9511cbca8b139

Audit result:

- Milestone 1A through 3AZ verified
- Historical milestone documents were not modified
- PR records were not fully verifiable
- Missing and deferred original-plan items were recorded
- Deployment strategy was clarified
- Corrected forward sequence was recorded

## Strategic reason for 3BA

Milestone 3AZ correctly selected:

Milestone 3BA - Club Finance Reports and Balance Sheet Read API Authorization

However, after 3AZ closure, a wider product strategy review identified that deployment and SaaS strategy must be locked before continuing report API development.

This is a later strategic reconciliation.

It does not rewrite 3AZ.

It does not change the historical 3AZ decision.

It records that the project now needs a deployment readiness checkpoint before report API authorization.

## Deployment model decision

The selected early-stage commercial deployment model is:

- One shared source codebase
- Separate deployed app instance per pilot club
- Separate database per club instance
- Separate environment/configuration per club instance
- Separate storage folder or bucket per club instance
- Club-specific branding and configuration
- No code fork per club

This model is selected for the pilot and early customer stage.

## Pilot clubs

Initial pilot clubs:

- Lions Club Beenachi
- Rotary Central Bathery

These two pilots should validate the platform across two different club cultures before wider marketing.

## Reason for separate app instances first

Separate app instances per club are preferred at the pilot stage because they:

- Reduce cross-club data leakage risk
- Simplify early deployment
- Simplify backup and restore per club
- Simplify support and troubleshooting
- Allow safer pilot validation
- Avoid premature global SaaS overengineering
- Preserve a future migration path to shared SaaS if justified later

## Codebase rule

The platform must not be forked per club.

Allowed:

- Same Git codebase
- Different environment files
- Different databases
- Different storage locations
- Different domain or subdomain
- Different club branding/configuration

Blocked:

- Separate Lions codebase
- Separate Rotary codebase
- Separate BNI codebase
- Client-specific code forks
- Hardcoded club-specific business logic

## Initial deployment unit

For the pilot stage, the deployment unit is one club instance.

Each club instance should have:

- Own domain or subdomain
- Own backend environment configuration
- Own frontend environment configuration where needed
- Own PostgreSQL database
- Own storage path or bucket
- Own seeded club record
- Own seeded local admin or president/admin user
- Own backup schedule
- Own support contact record
- Own branding configuration

## Future shared SaaS position

A fully shared global SaaS model is not rejected.

It is deferred until after pilot validation.

Future shared SaaS may be considered only after the product has stronger evidence from real club usage and after the missing SaaS readiness items are addressed.

## SaaS readiness gaps to track

Future milestones must track:

- Tenant hierarchy
- Organization type
- Club or chapter structure
- District or region support
- Configurable role labels
- Configurable permissions
- Financial year setting per club
- Timezone and locale support
- Multi-currency readiness
- Subscription and billing model
- Super-admin or support console
- Onboarding checklist
- Deployment checklist
- Data isolation policy
- Backup and restore policy
- Data export policy
- Privacy and data-retention policy
- Audit log review
- Migration path from isolated instances to shared SaaS if later required

## Business rollout decision

The commercial path is:

1. Complete pilot deployment readiness
2. Implement and validate the platform for Lions Club Beenachi
3. Implement and validate the platform for Rotary Central Bathery
4. Collect operational feedback from President/Admin, Secretary, Treasurer, and Members
5. Fix product gaps through controlled milestones
6. Prepare repeatable onboarding and support process
7. Start wider marketing to Lions Clubs, Rotary Clubs, BNI chapters, and other membership-based clubs

## Product positioning

The product should be positioned as a membership-based club management platform.

Initial target segments:

- Lions Clubs
- Rotary Clubs
- BNI chapters
- Local associations
- Chambers
- Service clubs
- Membership-based non-profit clubs

The first commercial promise should remain practical:

- Member management
- Role-based access
- Finance ledger and dues
- Payment recording
- Receipts and receipt PDF
- Member finance summary
- Club-specific deployment
- Controlled future reports

Do not promise unfinished modules as completed.

## Deferred original-plan items

The audit confirmed that several original-plan items remain deferred or not yet started.

Still deferred:

- Club finance reports
- Balance sheet
- Reminders
- Documents
- Announcements
- Projects
- Meeting notices
- Minutes visibility
- Donor consent search
- Fellowship sponsorship
- Special collections
- Excel import
- Audit log review UI
- Full global SaaS readiness

These are not implemented in 3BA.

## Corrected forward sequence

The corrected sequence is:

- 3AZ - Club Finance Reports and Balance Sheet Scope Gate - closed
- 3BA - Deployment and SaaS Strategy Readiness Review - docs/tests-only
- 3BB - Club Finance Reports and Balance Sheet Read API Authorization - docs/tests-only
- 3BC - Club Finance Reports and Balance Sheet Read API Narrow Implementation
- 3BD - Read API Validation and Closure
- Later - Reports Frontend Scope Gate

## 3BB boundary

3BB should return to finance reports and balance sheet read API authorization.

3BB must remain docs/tests-only.

3BB may authorize a later narrow read API implementation, but must not implement runtime routes, controllers, services, reports, exports, reminders, Excel import, frontend screens, allocation workflows, cancellation, or reversal.

## Scope boundary

3BA is docs/tests-only.

Allowed files in 3BA:

- backend/docs/milestone_3ba_deployment_saas_strategy_readiness_review.md
- backend/tests/Feature/Milestone3baDeploymentSaasStrategyReadinessReviewTest.php

Blocked in 3BA:

- app/page.tsx
- backend routes
- backend controllers
- backend requests
- backend resources
- backend services
- backend models
- migrations
- seeders
- frontend screens
- report implementation
- balance sheet implementation
- exports
- reminders
- Excel import
- payment history
- payment cancellation
- payment reversal
- special collection allocation workflow
- fellowship payment allocation workflow
- receipt sharing workflow
- subscription implementation
- super-admin implementation
- shared SaaS tenant implementation

## Closure rule

3BA closes only after:

- Strategy document exists
- Strategy test exists
- Focused 3BA test passes
- Full backend test suite passes
- No runtime file is changed

## Closure decision

Deployment and SaaS strategy readiness is reviewed.

The project will proceed with one shared codebase and separate deployed app/database/config/storage per pilot club first.

The next milestone should be:

Milestone 3BB - Club Finance Reports and Balance Sheet Read API Authorization
