import React from "react";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const authState = vi.hoisted(() => ({
  role: "treasurer" as "president" | "secretary" | "treasurer" | "member",
  identity: "ranjith-treasurer",
  member: { id: 35, member_code: "26766860", full_name: "Ranjith MR" },
  session: {
    identity: "ranjith-treasurer",
    role: "treasurer" as "president" | "secretary" | "treasurer" | "member",
    token: "frontend-test-token",
    user: { id: 7, name: "Frontend Test User", email: "frontend@example.test" },
    membership: {
      id: 7,
      club_id: 1,
      club: { id: 1, code: "LCB", name: "LC Beenachi" },
      role: { slug: "treasurer" },
      member: { id: 35, member_code: "26766860", full_name: "Ranjith MR" }
    }
  },
  auth: null as unknown as Record<string, unknown>
}));

authState.auth = {
  enabled: true,
  status: "authenticated",
  session: authState.session,
  error: null,
  switching: false,
  restoredFromStorage: true,
  switchIdentity: async () => true,
  authenticatedFetch: async () => new Response(null, { status: 501 })
};

vi.mock("../DevAuthSession", () => ({
  DevAuthProvider: ({ children }: { children: React.ReactNode }) => <>{children}</>,
  useDevAuth: () => authState.auth
}));

vi.mock("../FinanceBackend", () => ({
  default: ({ role, memberId, isPersonal }: { role: string; memberId?: number; isPersonal?: boolean }) => (
    <section data-testid="finance-backend">FinanceBackend {role} member {memberId} personal {String(Boolean(isPersonal))}</section>
  )
}));

vi.mock("../ProfileUpdatesBackend", () => ({
  default: () => <section data-testid="profile-updates-backend" />
}));

import Prototype from "../page";

type Scenario = {
  documentStatus: number;
  documentPayload: unknown;
  selectedCoreStatus?: number;
};

const sujesh = memberRecord(4, "4177371", "Sujesh P R");
const sajith = { ...memberRecord(5, "4794369", "Sajith K"), user_id: 7 };
const members = Array.from({ length: 47 }, (_, index) => {
  if (index === 0) return sujesh;
  if (index === 1) return sajith;
  return memberRecord(index + 4, String(7000000 + index), `Member ${String(index + 1).padStart(2, "0")}`);
});

function memberRecord(id: number, memberCode: string, fullName: string) {
  return {
    id,
    club_id: 1,
    user_id: null,
    member_code: memberCode,
    full_name: fullName,
    first_name: fullName,
    middle_name: null,
    last_name: null,
    salutation: "Mr",
    status: "active",
    membership_type: "Lion-Regular [Active]",
    membership_category: null,
    membership_program: null,
    joined_at: "2015-06-01",
    occupation: null,
    spouse_name: null,
    wedding_anniversary: null,
    gender: "male",
    phone: "9000000000",
    email: `${memberCode}@example.test`,
    blood_group: null,
    blood_donor_consent: false,
    address_line_1: null,
    address_line_2: null,
    city_locality: null,
    district: null,
    state: null,
    postal_code: null,
    country: null
  };
}

function json(payload: unknown, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { "Content-Type": "application/json" }
  });
}

function installFetchScenario(scenario: Scenario) {
  let sujeshCoreReads = 0;
  global.fetch = vi.fn(async (input: RequestInfo | URL) => {
    const url = String(input);
    const path = new URL(url, "http://localhost").pathname;

    if (path.endsWith("/profile-documents")) {
      return json(scenario.documentPayload, scenario.documentStatus);
    }
    if (path.endsWith("/members/me")) {
      return json({ member: sajith });
    }
    if (path.endsWith("/members")) {
      return json({ members });
    }
    const memberMatch = path.match(/\/members\/(\d+)$/);
    if (memberMatch) {
      const record = members.find((member) => member.id === Number(memberMatch[1]));
      if (record?.id === sujesh.id) {
        sujeshCoreReads += 1;
        if (sujeshCoreReads > 1 && scenario.selectedCoreStatus) {
          return json({ message: "Core profile failed." }, scenario.selectedCoreStatus);
        }
      }
      return record ? json({ member: record }) : json({ message: "Not found." }, 404);
    }
    if (path.includes("/finance-summary")) {
      return json({ data: { current_dues_paise: 0, current_dues_display: "0.00", payment_status: "paid" } });
    }
    return json({ data: [], members: [] });
  }) as typeof fetch;
}

async function openSujeshProfile() {
  render(<Prototype />);
  fireEvent.click(await screen.findByRole("button", { name: "Open Members" }));
  fireEvent.click(await screen.findByRole("button", { name: /Sujesh P R/ }));
  await screen.findByRole("heading", { name: "Sujesh P R" });
}

beforeEach(() => {
  authState.role = "treasurer";
  authState.identity = "ranjith-treasurer";
  authState.member = { id: 35, member_code: "26766860", full_name: "Ranjith MR" };
  authState.session.role = authState.role;
  authState.session.identity = authState.identity;
  authState.session.membership.role.slug = "treasurer";
  authState.session.membership.member = authState.member;
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe("MemberProfile document loading boundary", () => {
  it("keeps Treasurer core profile and finance rendered when documents return 403", async () => {
    installFetchScenario({ documentStatus: 403, documentPayload: { message: "This action is unauthorized." } });
    await openSujeshProfile();

    expect(await screen.findByText("Profile document metadata is unavailable for this role.")).toBeInTheDocument();
    expect(screen.queryByText("No profile document records added yet.")).not.toBeInTheDocument();
    expect(screen.queryByText("Metadata controls")).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Add Document Metadata" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Refresh Metadata" })).not.toBeInTheDocument();
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("FinanceBackend treasurer member 4");
    expect(screen.queryByText("This action is unauthorized.")).not.toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president"],
    ["secretary", "rajeesh-secretary"]
  ] as const)("shows one empty document state with metadata controls for %s", async (role, identity) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = role === "president" ? "president-admin" : role;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    await openSujeshProfile();

    expect(await screen.findAllByText("No profile document records added yet.")).toHaveLength(1);
    expect(screen.getByText("Metadata controls")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Add Document Metadata" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Refresh Metadata" })).toBeInTheDocument();
    expect(screen.queryByText("Profile document metadata is unavailable for this role.")).not.toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president"],
    ["secretary", "rajeesh-secretary"]
  ] as const)("keeps %s document behavior unchanged on a successful response", async (role, identity) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = role === "president" ? "president-admin" : role;
    installFetchScenario({
      documentStatus: 200,
      documentPayload: { profile_documents: [{ id: 91, document_type: "Membership proof", status: "active" }] }
    });
    await openSujeshProfile();

    expect(await screen.findByRole("heading", { name: "Membership proof" })).toBeInTheDocument();
    expect(screen.getByTestId("finance-backend")).toBeInTheDocument();
  });

  it("keeps a selected core-profile failure page-blocking", async () => {
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] }, selectedCoreStatus: 500 });
    render(<Prototype />);
    fireEvent.click(await screen.findByRole("button", { name: "Open Members" }));
    fireEvent.click(await screen.findByRole("button", { name: /Sujesh P R/ }));

    expect(await screen.findByText("The selected member profile is unavailable for this role.")).toBeInTheDocument();
    expect(screen.queryByTestId("finance-backend")).not.toBeInTheDocument();
  });

  it("keeps profile and finance rendered while showing a local non-403 document failure", async () => {
    installFetchScenario({ documentStatus: 500, documentPayload: { message: "Document service unavailable." } });
    await openSujeshProfile();

    expect(await screen.findByText("Document service unavailable.")).toBeInTheDocument();
    expect(screen.getByText("Profile document metadata could not be loaded or saved safely.")).toBeInTheDocument();
    expect(screen.getByTestId("finance-backend")).toBeInTheDocument();
    expect(screen.queryByText("Profile document metadata loaded.")).not.toBeInTheDocument();
  });

  it("preserves Normal Member own-document behavior without exposing another member", async () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.role = authState.role;
    authState.session.identity = authState.identity;
    authState.session.membership.role.slug = "member";
    authState.session.membership.member = authState.member;
    installFetchScenario({
      documentStatus: 200,
      documentPayload: { profile_documents: [{ id: 92, document_type: "Own membership proof", status: "active" }] }
    });
    render(<Prototype />);
    fireEvent.click(await screen.findByRole("button", { name: "My Profile" }));

    expect(await screen.findByRole("heading", { name: "Own membership proof" })).toBeInTheDocument();
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("personal false");
    expect(screen.queryByText("Sujesh P R")).not.toBeInTheDocument();
  });

  it("shows one empty document state for a Normal Member's own profile", async () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.role = authState.role;
    authState.session.identity = authState.identity;
    authState.session.membership.role.slug = "member";
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);
    fireEvent.click(await screen.findByRole("button", { name: "My Profile" }));

    expect(await screen.findAllByText("No profile document records added yet.")).toHaveLength(1);
    expect(screen.queryByText("Sujesh P R")).not.toBeInTheDocument();
    expect(screen.getByTestId("finance-backend")).toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president", "president-admin", 2],
    ["secretary", "rajeesh-secretary", "secretary", 2],
    ["treasurer", "ranjith-treasurer", "treasurer", 2],
    ["member", "sajith-member", "member", 1]
  ] as const)("reports the connected Events & Meetings source truthfully for %s", async (role, identity, roleSlug, expectedCount) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    if (role === "member") {
      authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
      authState.session.membership.member = authState.member;
    }
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    expect(await screen.findAllByText("Available in Events & Meetings.")).toHaveLength(expectedCount);
    expect(screen.queryByText("No live event source is connected.")).not.toBeInTheDocument();
    expect(screen.queryByText("Meeting information is not connected.")).not.toBeInTheDocument();
    expect(screen.queryByText("Meeting information is not connected to the Dashboard yet.")).not.toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president", "president-admin"],
    ["secretary", "rajeesh-secretary", "secretary"],
    ["treasurer", "ranjith-treasurer", "treasurer"]
  ] as const)("reports the connected Finance source truthfully for %s", async (role, identity, roleSlug) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    expect(await screen.findByText("Available in Finance.")).toBeInTheDocument();
    expect(screen.queryByText("Finance summary not connected")).not.toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president", "president-admin"],
    ["member", "sajith-member", "member"]
  ] as const)("does not present static club-document records as real for %s", async (role, identity, roleSlug) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    if (role === "member") {
      authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
      authState.session.membership.member = authState.member;
    }
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    if (role === "president") fireEvent.click(screen.getByRole("button", { name: /My Member Area/ }));
    fireEvent.click(screen.getByRole("button", { name: "My Documents" }));

    expect(await screen.findByText("Club document records are not connected to a verified backend source. No document records are shown.")).toBeInTheDocument();
    expect(screen.queryByText("General Body Minutes - May 2026")).not.toBeInTheDocument();
    expect(screen.queryByText("Annual Plan 2026")).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "View Details" })).not.toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president", "president-admin"],
    ["secretary", "rajeesh-secretary", "secretary"]
  ] as const)("opens the connected profile-update review workflow for %s", async (role, identity, roleSlug) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /People/ }));
    fireEvent.click(screen.getByRole("button", { name: /^Members/ }));
    fireEvent.click(screen.getByRole("button", { name: "Profile Update Pending" }));

    expect(await screen.findByTestId("profile-updates-backend")).toBeInTheDocument();
    expect(screen.queryByText("The profile update approval workflow is not connected yet.")).not.toBeInTheDocument();
  });

  it("keeps Treasurer outside the profile-update review navigation", () => {
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    expect(screen.queryByRole("button", { name: /People/ })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Profile Update Pending" })).not.toBeInTheDocument();
    expect(screen.queryByTestId("profile-updates-backend")).not.toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president", "president-admin"],
    ["secretary", "rajeesh-secretary", "secretary"]
  ] as const)("does not present fabricated announcement records or actions for %s", async (role, identity, roleSlug) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Operations/ }));
    fireEvent.click(screen.getByRole("button", { name: /^Announcements/ }));
    fireEvent.click(screen.getByRole("button", { name: "Published" }));

    expect(await screen.findByText("Announcement records are not connected to a verified backend source. No announcement records are shown.")).toBeInTheDocument();
    expect(screen.queryByText("District Governor Visit Update")).not.toBeInTheDocument();
    expect(screen.queryByText("Monthly Fellowship Notice")).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "View" })).not.toBeInTheDocument();
  });

  it.each([
    ["Board Members", "Board records are not connected to a verified backend source. No board records are shown."],
    ["Committee List", "Committee records are not connected to a verified backend source. No committee records are shown."],
    ["Committee Members", "Committee membership records are not connected to a verified backend source. Selectable committee membership is unavailable."]
  ] as const)("keeps the President %s destination distinct and truthful", async (destination, expectedState) => {
    authState.role = "president";
    authState.identity = "bijesh-president";
    authState.session.role = "president";
    authState.session.identity = "bijesh-president";
    authState.session.membership.role.slug = "president-admin";
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /People/ }));
    fireEvent.click(screen.getByRole("button", { name: /Board & Committees/ }));
    fireEvent.click(screen.getByRole("button", { name: destination }));

    expect(await screen.findByText(expectedState)).toBeInTheDocument();
    expect(screen.queryByText("Committee List item 2")).not.toBeInTheDocument();
    expect(screen.queryByText("Term / Lionistic Year: 2026–27")).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "View member profile" })).not.toBeInTheDocument();
  });

  it.each([
    ["secretary", "rajeesh-secretary", "secretary"],
    ["treasurer", "ranjith-treasurer", "treasurer"]
  ] as const)("preserves truthful Board/Committee access for %s", async (role, identity, roleSlug) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: role === "treasurer" ? /Club Visibility/ : /People/ }));
    fireEvent.click(screen.getByRole("button", { name: /Board & Committees/ }));
    fireEvent.click(screen.getByRole("button", { name: "Committee List" }));

    expect(await screen.findByText("Committee records are not connected to a verified backend source. No committee records are shown.")).toBeInTheDocument();
    expect(screen.queryByText("Committee List item 2")).not.toBeInTheDocument();
  });

  it("keeps Normal Members outside Board/Committee administration navigation", () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.session.role = "member";
    authState.session.identity = "sajith-member";
    authState.session.membership.role.slug = "member";
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    expect(screen.getByRole("button", { name: "Member Directory" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Board & Committees/ })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Board Members" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Committee List" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Committee Members" })).not.toBeInTheDocument();
  });

});
