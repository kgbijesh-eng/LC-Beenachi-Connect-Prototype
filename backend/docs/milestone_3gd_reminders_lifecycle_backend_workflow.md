# Milestone 3GD - Reminders Create, Update and Lifecycle Backend

Base: verified 3GC commit `9c96b2bd86f9deb0e5d5a557a68190676eb663b0`.

3GD adds create-Draft, state-aware update, Draft-to-Active activation, Active-to-Completed completion (including early completion), Draft/Active cancellation with required reason, management authorization, same-club nested ownership, row locking, optimistic `lock_version`, deterministic 409 conflicts, transactional audit, and focused tests.

President/Admin and Secretary may mutate. Treasurer, Member, unauthenticated, inactive, and another-club users are denied by the permission and club-membership boundary. Create cannot override club, status, actors, lifecycle timestamps, or version. Generic update cannot mutate status. Completed and Cancelled are terminal. No DELETE route exists.

Successful mutations write exactly one `reminders.created`, `reminders.updated`, `reminders.activated`, `reminders.completed`, or `reminders.cancelled` audit record in the same transaction. Failed, stale, repeated, forbidden, validation, or cross-club requests write no success audit.

No frontend integration, notification delivery, genuine data, or Reports work is included.

GO - 3GE may open after 3GD through 3GA tests pass, this branch is committed and pushed normally, local and remote tips match, and tracking and the working tree are clean.
