# Milestone 3EM — Member Directory Selected Member Read Failure Narrow Implementation

## Status

Milestone 3EM is a narrow implementation milestone.
- 3EL is closed, pushed, remote verified, and tracking clean.
- 3EL authorized the narrow selected-member read failure fix.
- 3EJ overall result remains FAIL until a later human/operator retest milestone proves otherwise.
- 3EK locked the 3EJ controlled-use failure record.
- The defect remains classified as a human operator controlled-use blocking defect.
- Area: Member Directory / selected member read / member profile context.
- No backend runtime fix was implemented (the fix was entirely achieved within the frontend layout).
- No data model/migration/package/lock file changes were made.
- No human/operator retest was executed in 3EM.
- Controlled internal MVP remains blocked until a later controlled-use retest passes.
- Limited internal member pilot remains not allowed.
- Production/SaaS/deployment/public launch remain NO-GO.
- Next milestone recommendation is Milestone 3EN — Member Directory Selected Member Read Failure Fix Closure Review.

---

## Human Observed Failure Summary (3EJ / 3EK)

During controlled-use testing, the human operator clicked the following Member Directory links as President:
- **MEM-002** / Lion Secretary User
- **MEM-003** / Lion Treasurer User
- **MEM-004** / Lion Ajith Kumar - Member
- **MEM-001** / Lion Bijesh - President

### Actual Behavior (Before Fix)
Each selection opened the same My Profile screen for Lion Bijesh / MEM-001 - President, and displayed the exact warning:
"Selected member was not found in backend member records."

---

## Future Fixed Behavior

The implementation achieves the following expected future fixed behavior for member selections:
- **MEM-002** opens Secretary's own read/detail page (Lion Secretary User).
- **MEM-003** opens Treasurer's own read/detail page (Lion Treasurer User).
- **MEM-004** opens Ajith Kumar's own read/detail page (Lion Ajith Kumar).
- **MEM-001** opens Bijesh's own read/detail page (Lion Bijesh).
- Known backend member records must not fall back to My Profile and must not show the warning: "Selected member was not found in backend member records."

---

## Implementation Summary of the Narrow Fix

The fix was implemented strictly in `app/page.tsx` under the following design:
1. **Selection State**: Added a React state variable `selectedMember` to track the clicked member:
   ```typescript
   const [selectedMember, setSelectedMember] = useState<Member | null>(null);
   ```
2. **Directory Callback**: Updated `MembersList` to accept an `onSelectMember` callback. Clicking any row in the directory sets `selectedMember` and navigates to the `"member-profile"` screen.
3. **Context Sync**: Rendered `MemberProfile` with the selected member falling back to the default active member:
   ```typescript
   <MemberProfile member={selectedMember ?? activeMember} role={role} />
   ```
4. **Navigation Reset**: Added safety resets to clear the active selection when switching roles or when navigation refers to **My Profile** (e.g. sidebar menu items, dashboard quick-action cards, or when leaving the profile page) to prevent the directory selection from sticking.

---

## Selected Member Identity Source
The identity source is the React component state `selectedMember` populated directly from the local member directory list row on click.

## Fallback Behavior
When `selectedMember` is `null` (representing direct navigation to the user's own profile), it resolves to the authenticated user's `activeMember` object based on the currently simulated role.

---

## Exclusions & Go/No-Go

- **Backend Code**: No changes were made to backend routes, controllers, resources, models, services, migrations, or database seed data.
- **Package Files**: No changes to `package.json`, `package-lock.json`, `composer.json`, or `composer.lock`.
- **E2E Tooling**: No browser testing tools or automation suites were introduced.
- **Retest Status**: No human/operator retest has been performed yet. 3EJ remains FAIL.
- **Checklist Files**: `task.md` was not modified in the repository.
- **Production Status**:
  - Controlled internal MVP execution: FAIL (Blocked)
  - Limited internal member pilot: NO-GO
  - Production / SaaS / Deployment / Public Launch: NO-GO
