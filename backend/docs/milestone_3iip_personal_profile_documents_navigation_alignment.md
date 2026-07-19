# Milestone 3IIP — Personal Profile Documents Navigation Alignment

## Baseline and scope

Milestone 3IIP is based on `c6d13a14ca870d8396584f395fa22bda185f0527` and adds a narrow read-only personal profile-document metadata screen.

It changes no backend runtime, route, resource, model, permission, migration, persistence or audit behavior. It adds no document-content, upload, download, preview, OCR, sharing, URL or storage capability.

## Verified response contracts

The existing authenticated client supplies the `/api/v1` base. The client-relative request `GET /clubs/{club}/members/me` therefore resolves to `GET /api/v1/clubs/{club}/members/me` and accepts only the verified `{ "member": { ... } }` envelope.

After validating the returned member ID and club ID against authenticated session linkage, the client-relative request `GET /clubs/{club}/members/{member}/profile-documents` resolves to `GET /api/v1/clubs/{club}/members/{member}/profile-documents` and accepts only the verified `{ "profile_documents": [ ... ] }` envelope.

The screen never scans the member directory and accepts no caller-controlled club or member identity.

## Display and privacy boundary

Navigation and screen title are both `My Profile Documents`.

The screen states: `This screen shows metadata recorded against your member profile. File content, upload, download and preview are not available through this screen.`

It does not claim whether content is stored elsewhere. It does not describe a shared club-document library, documents available to all members, downloadable documents or document storage.

Visible metadata is limited to document type, status, external reference, remarks, metadata-created timestamp and metadata-updated timestamp. Actor IDs, club ID and member ID are not displayed. External references and remarks remain escaped non-clickable text and are never interpreted as URLs, filenames or storage identifiers.

## Integrity and stale-response controls

Every record must match the authenticated club and resolved own member. Missing or guessed envelopes, malformed rows, mixed ownership and invalid non-null Laravel ISO timestamps reject the complete response. An exact empty array is distinct from an unavailable source. Null timestamps show `Unavailable` and are never described as issue, validity or expiry dates.

AbortController and a monotonically increasing request-generation token prevent old context, old refresh and unmounted-component responses from rendering. Context changes immediately clear all rows and restart resolution from `/members/me`.

## Explicit exclusions

The personal screen uses GET requests only. It does not use legacy fabricated document arrays or expose add, edit, save, delete, upload, download, preview, OCR, share, export, URL, storage or file-content controls. Existing selected-member metadata management remains unchanged and separate.

No operational data was accessed during implementation or validation. Tests use isolated SQLite `:memory:` only.
