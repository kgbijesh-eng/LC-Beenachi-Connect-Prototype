# Milestone 3GC - Reminders Database and Read API Foundation

Base: `milestone-3gb-reminders-authorization-contract-approval` at verified tip `50ef1c9d451df77d808d28068297cebb9bf668a5`.

3GC implements only the `club_reminders` schema, `ClubReminder` controlled values and relationships, `reminders.view`/`reminders.manage` role assignments, club-scoped list/detail GET APIs, validated filters, deterministic ordering, pagination, computed overdue, UTC and club-timezone representations, privacy-aware resources, and tests.

Create, update, activate, complete, cancel, DELETE, write services, mutation requests, frontend integration, notification delivery, and Reports are not implemented.

The read matrix follows 3GB: management sees all own-club records; Treasurer and Member see Active/Completed members-visible records and Cancelled members-visible records that were previously activated. Draft and management-visible records remain concealed.

All-day records use `due_date`; timed records use UTC `due_at`. Overdue is computed only for Active records. Stable numeric IDs preserve duplicate-title identity. List queries are route-club scoped before filtering, and detail rejects wrong-club nested identity.

Changed runtime scope is limited to the migration, model, read request/service/resource/controller, GET routes, permission seeder/test, this document, and the focused 3GC test.

GO - 3GD may open after 3GC plus 3GB/3GA regressions pass, the branch is committed and pushed normally, local and remote tips match, tracking is clean, and no mutation route exists in the historical 3GC commit.
