# LC Beenachi Connect

## Milestone 1C — Laravel Backend Foundation Setup

### Status

```text
Milestone: 1C
Title: Laravel Backend Foundation Setup
Status: Scope definition
Scope: Backend foundation only
Business modules: Not included
```

---

# 1. Purpose

Milestone 1C starts the backend implementation for LC Beenachi Connect after the approval of **Milestone 1B — Backend Readiness & Technical Specification**.

The purpose of this milestone is to create the **Laravel backend foundation only**.

This milestone must not implement business modules such as member ledger, dues generation, payments, receipts, vouchers, fellowship workflow, reports, reminders, Excel import, document upload module, events, meetings, announcements, projects, or finance operations.

The goal is to establish a stable backend base before business logic is added.

---

# 2. Previous Milestone Reference

Current locked project status:

```text
Milestone 1  → documented
Milestone 1A → closed / reviewed / pushed / tagged
Milestone 1B → approved / pushed / docs-only
Commit       → be2b832 Approve Milestone 1B backend readiness specification
Backend code → not started before Milestone 1C
```

Milestone 1C must follow the approved Milestone 1B backend specification.

---

# 3. Milestone 1C Scope

Milestone 1C includes only the backend foundation items below:

```text
1. Laravel project setup
2. PostgreSQL connection setup
3. Base API structure
4. API versioning using /api/v1
5. Base JSON response format
6. Authentication foundation
7. Club / organization base foundation
8. Role-permission foundation
9. Audit foundation
10. Environment and configuration structure
```

---

# 4. Strictly Not Included

The following must not be implemented in Milestone 1C:

```text
No member ledger
No dues generation
No payments
No receipts
No vouchers
No fellowship workflow
No reports
No reminders
No Excel import
No document upload module
No events/meetings module
No announcements module
No projects module
No business module expansion
No SaaS billing
No WhatsApp/SMS integration
No full balance sheet
No Kuri/chit module
No public member/business profile card
No NFC/tap-to-share feature
```

If any of the above becomes necessary, it must be opened as a separate later milestone or ticket.

---

# 5. Backend Folder and Repository Structure Decision

The Laravel backend should be created in a clean backend folder, separate from the existing UI prototype.

Recommended structure:

```text
LC Beenachi Project Root/
  frontend/ or UI Prototype/
  backend/
    Laravel application files
  docs/
```

If the current project is still inside the UI prototype folder, Laravel files must not be mixed directly into the frontend root.

Before Laravel installation, confirm whether:

```text
1. The current repository root will become the full LC Beenachi project root; or
2. A new parent project folder/repository will be created.
```

This decision must be made before running Laravel installation commands.

---

# 6. Laravel Foundation Requirements

## 6.1 Laravel Setup

Milestone 1C should create a fresh Laravel API backend.

Required:

```text
Laravel installed
.env configured
Application key generated
Local server runnable
Basic health check endpoint available
```

## 6.2 PostgreSQL Setup

Required:

```text
PostgreSQL connection configured
Database connection verified
Migration system working
No business tables added beyond foundation scope
```

Allowed foundation tables:

```text
users
clubs
roles
permissions
role_permissions
club_user_roles
audit_logs
```

Laravel framework/system tables are allowed where required for authentication, queues, cache, sessions, tokens, and migrations. These are not considered business module tables.

Examples of allowed Laravel/system tables may include:

```text
migrations
cache
jobs
sessions
password_reset_tokens
personal_access_tokens
```

Business finance tables must not be added in Milestone 1C.

---

# 7. API Foundation

All API endpoints must use versioning:

```text
/api/v1
```

Allowed foundation endpoints:

```text
GET    /api/v1/health
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
GET    /api/v1/auth/me
GET    /api/v1/clubs/current
GET    /api/v1/roles
GET    /api/v1/permissions
GET    /api/v1/audit-logs
```

The endpoint list may remain minimal if authentication and authorization are not fully completed in this milestone.

No business-module routes should be added in Milestone 1C.

---

# 8. Base JSON Response Format

The API should follow a consistent response format.

Success example:

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {}
}
```

Error example:

```json
{
  "success": false,
  "message": "Validation failed.",
  "errors": {}
}
```

This response structure should be applied consistently from the beginning.

---

# 9. Authentication Foundation

Milestone 1C should prepare authentication but avoid overbuilding advanced login features.

Preferred Laravel authentication foundation:

```text
Laravel Sanctum
```

Sanctum should be used unless a later technical review identifies a strong reason to choose a different authentication approach.

Required:

```text
User model configured
Password-based login foundation
Authenticated user endpoint
Logout endpoint
Basic auth middleware
Token/session strategy clarified
```

Deferred:

```text
OTP login
Email verification
Password reset
Two-factor authentication
Social login
Advanced device/session management
```

These can be added later if needed.

---

# 10. Club Base Foundation

The backend must support club-level separation from the beginning.

Required table:

```text
clubs
```

Required rule:

```text
Every future club-specific business table must include club_id.
```

Milestone 1C should establish the club base but must not implement full SaaS onboarding.

Deferred:

```text
Multi-club subscription billing
Club self-registration
Platform admin dashboard
Club onboarding wizard
Club subscription/payment management
```

---

# 11. Role-Permission Foundation

Milestone 1C should create the role-permission base.

Required roles:

```text
President
Secretary
Treasurer
Member
```

Future role:

```text
Platform Admin
```

Required permission approach:

```text
Action-based permissions
Own vs any permission pattern
Club-scoped role assignment
```

Required core tables:

```text
roles
permissions
role_permissions
club_user_roles
```

Do not yet implement all business permissions for ledger, receipts, payments, vouchers, fellowship, reports, reminders, or imports.

Milestone 1C should prepare the structure only.

---

# 12. Audit Foundation

Milestone 1C should create the audit log foundation early.

Required table:

```text
audit_logs
```

Required fields:

```text
id
club_id
user_id
action
entity_type
entity_id
old_values
new_values
ip_address
user_agent
created_at
```

Milestone 1C may log only basic foundation actions initially, such as:

```text
login
logout
role assignment
permission assignment
club creation
settings/config change
```

Business audit logging will expand in later milestones.

---

# 13. Environment and Configuration

Milestone 1C should prepare environment structure safely.

Required:

```text
.env example file
Database config
App URL config
Frontend URL / CORS config
Queue config placeholder
Mail config placeholder
Storage config placeholder
Sanctum/auth config
```

Rules:

```text
No secrets committed to Git
No production credentials committed
No real passwords in documentation
No real API keys in documentation
No database password committed
```

---

# 14. Testing and Verification

Milestone 1C should include basic verification.

Required checks:

```text
Laravel app boots successfully
Database connection works
Migrations run successfully
/api/v1/health returns success
Authentication foundation works if implemented
Role/permission seed works if implemented
No business module routes added
No finance tables added
No secrets committed
```

Recommended verification commands:

```bash
php artisan test
php artisan migrate:fresh --seed
php artisan route:list
```

`php artisan route:list` must not show routes for:

```text
ledger
dues
payments
receipts
vouchers
fellowship
reports
reminders
imports
documents
events
meetings
announcements
projects
```

Recommended test types:

```text
Feature test for /api/v1/health
Feature test for /api/v1/auth/me if authentication is active
Feature test for /api/v1/clubs/current if implemented
Feature or unit test for base JSON response format if practical
```

---

# 15. Completion Criteria

Milestone 1C can be closed only when:

```text
1. Laravel backend is created in the correct folder.
2. PostgreSQL connection is configured and verified.
3. API versioning /api/v1 is established.
4. Base JSON response format is defined.
5. Authentication foundation is present.
6. Club base foundation is present.
7. Role-permission foundation is present.
8. Audit foundation is present.
9. Environment configuration is clean.
10. No secrets are committed.
11. Laravel framework/system tables are reviewed.
12. No business tables outside the 1C foundation are added.
13. No member ledger module is added.
14. No payments/receipts/vouchers are added.
15. No fellowship workflow is added.
16. No reports/reminders/imports are added.
17. No document/events/meetings/announcements/projects modules are added.
18. Tests or manual verification confirm the foundation works.
19. Route list confirms no business module routes were added.
```

---

# 16. Recommended Implementation Order

Milestone 1C should be implemented in this order:

```text
1. Confirm backend folder/repository structure decision.
2. Create Laravel project.
3. Configure PostgreSQL.
4. Add /api/v1 route structure.
5. Add health endpoint.
6. Add base JSON response pattern.
7. Add authentication foundation using Laravel Sanctum.
8. Add clubs table/model foundation.
9. Add roles and permissions foundation.
10. Add club_user_roles foundation.
11. Add audit_logs foundation.
12. Add seeders for default club and roles.
13. Add basic tests.
14. Run verification commands.
15. Verify no business modules were added.
```

---

# 17. Recommended Git Branch

```bash
git checkout main
git pull
git checkout -b milestone-1c-laravel-backend-foundation
```

---

# 18. Recommended First Commit

The first Milestone 1C commit should be docs-only.

```bash
git add docs/milestone-1c/Milestone_1C_Laravel_Backend_Foundation_Setup.md
git commit -m "Add Milestone 1C backend foundation scope"
git push -u origin milestone-1c-laravel-backend-foundation
```

Laravel setup should start only after this scope document is committed.

---

# 19. Final Boundary

Milestone 1C is a backend foundation milestone.

It must not become a business module milestone.

Any request to add ledger, dues, payments, receipts, vouchers, fellowship, reports, reminders, import, documents, events, meetings, announcements, projects, or other business modules during Milestone 1C must be deferred to a later milestone.

Milestone 1C should close only after the backend foundation is stable, verified, and cleanly separated from business module implementation.
