# Milestone 3GJ - Reports Catalog and Shared Query Foundation

Base: verified 3GI commit `dd08c3539bfa1a5dd8383d2ee92c9dec3f002f1e`.

3GJ adds the fixed four-key `ReportCatalog`, `reports.view` permission for President/Admin, Secretary and Treasurer, active same-club permission middleware, catalog GET endpoint, common query validation, 366-day maximum, page-size maximum, and tests.

The catalog exposes key, title, description, allowed filters, aggregate-only flag, and sensitivity. It does not expose `collections-summary` or any unsupported key.

No report source query, report endpoint, finance report, mutation route, persistence table, arbitrary model/column selection, frontend change, or export is implemented.

GO - 3GK may open after 3GJ–3GH tests and permission regressions pass, separate commit/push, matching remote hash, and clean tracking.
