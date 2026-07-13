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
  default: ({ role, memberId, isPersonal, view, obligationId, onRecordPayment, onReturnToSummary }: { role: string; memberId?: number; isPersonal?: boolean; view?: string; obligationId?: number; onRecordPayment?: (memberId: number, obligationId: number) => void; onReturnToSummary?: () => void }) => {
    const [pane, setPane] = React.useState(view ?? "workspace");
    const returnToSummary = () => onReturnToSummary ? onReturnToSummary() : setPane("summary");
    return (
      <section data-testid="finance-backend">
        FinanceBackend {role} member {memberId} obligation {obligationId} personal {String(Boolean(isPersonal))} view {view ?? "workspace"}
        {pane === "summary" && <h2>{isPersonal ? "My Finance Summary" : "Member Finance Summary"}</h2>}
        {pane === "dues" && <><h2>{isPersonal ? "My Dues" : "Member Dues"}</h2><button onClick={returnToSummary}>Back to Finance Summary</button></>}
        {pane === "payment-history" && <><h2>{isPersonal ? "My Payment History" : "Member Payment History"}</h2><button onClick={returnToSummary}>Back to Finance Summary</button></>}
        {view === "outstanding" && onRecordPayment && <button onClick={() => onRecordPayment(4, 44)}>Record Payment for obligation 44</button>}
      </section>
    );
  }
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
    birth_date: null as string | null,
    membership_type: "Lion-Regular [Active]",
    membership_category: null,
    membership_program: null,
    joined_at: "2015-06-01" as string | null,
    occupation: null,
    spouse_name: null,
    wedding_anniversary: null as string | null,
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
  vi.useRealTimers();
  vi.restoreAllMocks();
  members.forEach((member) => {
    member.status = "active";
    member.birth_date = null;
    member.joined_at = "2015-06-01";
    member.wedding_anniversary = null;
  });
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
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("personal true");
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("view summary");
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

  it("labels the officer dashboard profile-update review source as connected", async () => {
    authState.role = "president";
    authState.identity = "bijesh-president";
    authState.session.role = "president";
    authState.session.identity = "bijesh-president";
    authState.session.membership.role.slug = "president-admin";
    authState.member = { id: 34, member_code: "4794370", full_name: "Bijesh K G" };
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    expect(await screen.findByText("Profile update review is available.")).toBeInTheDocument();
    expect(screen.queryByText("Approval totals are unavailable.")).not.toBeInTheDocument();
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
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.role = "member";
    authState.session.identity = "sajith-member";
    authState.session.membership.role.slug = "member";
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    expect(screen.getByRole("button", { name: "Member Directory" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Board & Committees/ })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Board Members" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Committee List" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Committee Members" })).not.toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president", "president-admin"],
    ["secretary", "rajeesh-secretary", "secretary"],
    ["treasurer", "ranjith-treasurer", "treasurer"]
  ] as const)("shows a truthful disconnected Club Account Statement for %s", async (role, identity, roleSlug) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Finance/ }));
    fireEvent.click(screen.getByRole("button", { name: /^Club Account Statement/ }));
    fireEvent.click(screen.getByRole("button", { name: "Account Overview" }));

    expect(await screen.findByText("Club account statements are not connected to a verified backend source. No balances or transactions are shown.")).toBeInTheDocument();
    expect(screen.queryByText("Opening Balance")).not.toBeInTheDocument();
    expect(screen.queryByText("RCP-021")).not.toBeInTheDocument();
    expect(screen.queryByText("₹25,000")).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: /^Club Finance Reports/ })).toBeInTheDocument();
  });

  it("keeps Normal Members outside Club Account Statement navigation", () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.session.role = "member";
    authState.session.identity = "sajith-member";
    authState.session.membership.role.slug = "member";
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    expect(screen.queryByRole("button", { name: /^Finance$/ })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Club Account Statement" })).not.toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president", "president-admin"],
    ["secretary", "rajeesh-secretary", "secretary"],
    ["treasurer", "ranjith-treasurer", "treasurer"],
    ["member", "sajith-member", "member"]
  ] as const)("opens Celebrations on the current period for %s", async (role, identity, roleSlug) => {
    vi.useFakeTimers({ toFake: ["Date"] });
    vi.setSystemTime(new Date("2026-07-04T12:00:00+05:30"));
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

    fireEvent.click(screen.getByRole("button", { name: role === "member" ? "Celebrations" : "Open Celebrations" }));

    expect(await screen.findByText("July 2026")).toBeInTheDocument();
    expect(screen.getByLabelText("Celebration month")).toHaveValue("7");
    expect(screen.queryByText("June 2026")).not.toBeInTheDocument();

    fireEvent.change(screen.getByLabelText("Celebration month"), { target: { value: "6" } });
    expect(screen.getByText("June 2026")).toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president", "president-admin"],
    ["secretary", "rajeesh-secretary", "secretary"],
    ["treasurer", "ranjith-treasurer", "treasurer"]
  ] as const)("limits Monthly Birthday Report to the current local month for %s", async (role, identity, roleSlug) => {
    vi.useFakeTimers({ toFake: ["Date"] });
    vi.setSystemTime(new Date("2026-07-05T12:00:00+05:30"));
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    sujesh.birth_date = "1980-07-19";
    sajith.birth_date = "1990-08-04";
    members[2].birth_date = "not-a-date";
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Reports & Tools/ }));
    fireEvent.click(screen.getByRole("button", { name: /Celebration Reports/ }));
    fireEvent.click(screen.getByRole("button", { name: "Monthly Birthday Report" }));

    expect(await screen.findAllByRole("heading", { name: "Monthly Birthday Report" })).toHaveLength(2);
    expect(screen.getByText("July 2026")).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Sujesh P R" })).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Sajith K" })).not.toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Member 02" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Export|Generate Report/ })).not.toBeInTheDocument();
  });

  it("keeps Monthly Birthday Report outside Normal Member navigation", () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.session.role = "member";
    authState.session.identity = "sajith-member";
    authState.session.membership.role.slug = "member";
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    expect(screen.queryByRole("button", { name: /Reports & Tools/ })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Monthly Birthday Report" })).not.toBeInTheDocument();
  });

  it("routes officer Events and Meetings reports to the verified backend register", async () => {
    authState.role = "president";
    authState.identity = "bijesh-president";
    authState.session.role = "president";
    authState.session.identity = "bijesh-president";
    authState.session.membership.role.slug = "president-admin";
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Reports & Tools/ }));
    fireEvent.click(screen.getByRole("button", { name: /Events & Meetings Reports/ }));

    expect(screen.getByRole("button", { name: "Events & Meetings Register" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Attendance Placeholder Report" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Past Events Summary" })).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "Events & Meetings Register" }));
    expect(await screen.findByRole("heading", { name: "Reports" })).toBeInTheDocument();
    expect(screen.getByText(/verified provenance/)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Export|Download|Generate Report/ })).not.toBeInTheDocument();
  });

  it("routes officer Club Activity reports to the verified backend register", async () => {
    authState.role = "president";
    authState.identity = "bijesh-president";
    authState.session.role = "president";
    authState.session.identity = "bijesh-president";
    authState.session.membership.role.slug = "president-admin";
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Reports & Tools/ }));
    fireEvent.click(screen.getByRole("button", { name: /Club Activity \/ Service Reports/ }));

    expect(screen.getByRole("button", { name: "Projects & Activities Register" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Monthly Activity Report" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Yearly Consolidated Activity Report" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Category-wise Activity Report" })).not.toBeInTheDocument();
  });

  it("keeps Projects and Activities report navigation outside the Normal Member view", async () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.role = "member";
    authState.session.identity = "sajith-member";
    authState.session.membership.role.slug = "member";
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: "Projects / Activities" }));

    expect(await screen.findByText("No Projects or Activities match the current filters.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Open Reports & Tools" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Create Draft" })).not.toBeInTheDocument();
  });

  it.each([
    ["president", "bijesh-president", "president-admin"],
    ["secretary", "rajeesh-secretary", "secretary"],
    ["treasurer", "ranjith-treasurer", "treasurer"]
  ] as const)("limits Monthly Anniversary Report to the current local month for %s", async (role, identity, roleSlug) => {
    vi.useFakeTimers({ toFake: ["Date"] });
    vi.setSystemTime(new Date("2026-07-05T12:00:00+05:30"));
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    sujesh.joined_at = "2005-07-19";
    sajith.joined_at = "2015-08-04";
    sajith.wedding_anniversary = "2017-07-22";
    members[2].joined_at = "not-a-date";
    members[2].wedding_anniversary = "2020-13-40";
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Reports & Tools/ }));
    fireEvent.click(screen.getByRole("button", { name: /Celebration Reports/ }));
    fireEvent.click(screen.getByRole("button", { name: "Monthly Anniversary Report" }));

    expect(await screen.findAllByRole("heading", { name: "Monthly Anniversary Report" })).toHaveLength(2);
    expect(screen.getByText("July 2026")).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Sujesh P R" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Sajith K" })).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Member 02" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Export|Generate Report/ })).not.toBeInTheDocument();
  });

  it("shows a truthful empty state for a current month without anniversaries", async () => {
    vi.useFakeTimers({ toFake: ["Date"] });
    vi.setSystemTime(new Date("2026-07-05T12:00:00+05:30"));
    authState.role = "president";
    authState.identity = "bijesh-president";
    authState.session.role = "president";
    authState.session.identity = "bijesh-president";
    authState.session.membership.role.slug = "president-admin";
    members.forEach((member) => { member.joined_at = null; member.wedding_anniversary = null; });
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Reports & Tools/ }));
    fireEvent.click(screen.getByRole("button", { name: /Celebration Reports/ }));
    fireEvent.click(screen.getByRole("button", { name: "Monthly Anniversary Report" }));

    expect(await screen.findByText("No anniversaries recorded for this period.")).toBeInTheDocument();
  });

  it("keeps Celebration Register all-time while monthly reports share current-period semantics", async () => {
    authState.role = "president";
    authState.identity = "bijesh-president";
    authState.session.role = "president";
    authState.session.identity = "bijesh-president";
    authState.session.membership.role.slug = "president-admin";
    sujesh.birth_date = "1980-07-19";
    sajith.birth_date = "1990-08-04";
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Reports & Tools/ }));
    fireEvent.click(screen.getByRole("button", { name: /Celebration Reports/ }));
    fireEvent.click(screen.getByRole("button", { name: "Celebration Register" }));

    expect((await screen.findAllByRole("heading", { name: "Sujesh P R" })).length).toBeGreaterThan(0);
    expect(screen.getAllByRole("heading", { name: "Sajith K" }).length).toBeGreaterThan(0);
  });

  it("loads the privacy-reduced club directory for a Normal Member without exposing officer profile actions", async () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.role = "member";
    authState.session.identity = "sajith-member";
    authState.session.membership.role.slug = "member";
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: "Member Directory" }));

    expect(await screen.findByText("Sujesh P R")).toBeInTheDocument();
    expect(screen.getByText("Sajith K")).toBeInTheDocument();
    expect(screen.getByText("47 verified club members. Detailed member profiles are available only to authorized club officers.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Sujesh P R/ })).not.toBeInTheDocument();
    expect(screen.queryByText("9000000000")).not.toBeInTheDocument();
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringMatching(/\/clubs\/1\/members$/),
      expect.objectContaining({ headers: expect.any(Object) })
    );
  });

  it.each([
    ["Active Member List", "Sujesh P R", "Sajith K"],
    ["Inactive Member List", "Sajith K", "Sujesh P R"]
  ] as const)("keeps %s aligned to the stored membership status", async (report, visibleMember, hiddenMember) => {
    authState.role = "president";
    authState.identity = "bijesh-president";
    authState.session.role = "president";
    authState.session.identity = "bijesh-president";
    authState.session.membership.role.slug = "president-admin";
    sajith.status = "inactive";
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Reports & Tools/ }));
    fireEvent.click(screen.getByRole("button", { name: /Member Reports/ }));
    fireEvent.click(screen.getByRole("button", { name: report }));

    expect(await screen.findByRole("heading", { name: `Member: ${visibleMember}` })).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: `Member: ${hiddenMember}` })).not.toBeInTheDocument();
    expect(screen.getByText(report === "Active Member List" ? "46 verified active members" : "1 verified inactive member")).toBeInTheDocument();
  });

  it("shows a truthful empty state when no imported members have the selected status", async () => {
    authState.role = "treasurer";
    authState.identity = "ranjith-treasurer";
    authState.session.role = "treasurer";
    authState.session.identity = "ranjith-treasurer";
    authState.session.membership.role.slug = "treasurer";
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Reports & Tools/ }));
    fireEvent.click(screen.getByRole("button", { name: /Member Reports/ }));
    fireEvent.click(screen.getByRole("button", { name: "Inactive Member List" }));

    expect(await screen.findByText("0 verified inactive members")).toBeInTheDocument();
    expect(screen.getByText("No verified inactive members are recorded.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /View .* profile/ })).not.toBeInTheDocument();
  });

  it("opens an authorized member profile from an officer member report", async () => {
    authState.role = "president";
    authState.identity = "bijesh-president";
    authState.session.role = "president";
    authState.session.identity = "bijesh-president";
    authState.session.membership.role.slug = "president-admin";
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /Reports & Tools/ }));
    fireEvent.click(screen.getByRole("button", { name: /Member Reports/ }));
    fireEvent.click(screen.getByRole("button", { name: "Active Member List" }));
    fireEvent.click(await screen.findByRole("button", { name: "View Sujesh P R profile" }));

    expect(await screen.findByRole("heading", { name: "Sujesh P R" })).toBeInTheDocument();
    expect(screen.getByText("Club member record")).toBeInTheDocument();
  });

  it("carries canonical outstanding-dues context into the payment screen", async () => {
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /^Finance/ }));
    fireEvent.click(screen.getByRole("button", { name: /^Outstanding Dues/ }));
    fireEvent.click(screen.getByRole("button", { name: "Member-wise Outstanding" }));

    expect(await screen.findByTestId("finance-backend")).toHaveTextContent("view outstanding");
    fireEvent.click(screen.getByRole("button", { name: "Record Payment for obligation 44" }));

    expect(await screen.findByTestId("finance-backend")).toHaveTextContent("member 4 obligation 44");
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("view record-payment");
  });

  it("routes payment history and receipt list to the canonical read-only history view", async () => {
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /^Finance/ }));
    fireEvent.click(screen.getByRole("button", { name: /^Payments \/ Receipts/ }));
    fireEvent.click(screen.getByRole("button", { name: "Payment History" }));

    expect(await screen.findByTestId("finance-backend")).toHaveTextContent("view payment-history");
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("personal false");

    fireEvent.click(screen.getByRole("button", { name: "Receipt List" }));
    expect(await screen.findByTestId("finance-backend")).toHaveTextContent("view payment-history");
  });

  it("routes Member Ledger to summary without silently selecting an officer member", async () => {
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /^Finance/ }));
    fireEvent.click(screen.getByRole("button", { name: /^Member Ledger \/ Dues/ }));
    fireEvent.click(screen.getByRole("button", { name: "Member Ledger" }));

    expect(await screen.findByTestId("finance-backend")).toHaveTextContent("view summary");
    expect(screen.getByTestId("finance-backend")).not.toHaveTextContent(/member \d/);
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("personal false");
  });

  it.each([
    ["president", "bijesh-president", "president-admin"],
    ["secretary", "rajeesh-secretary", "secretary"],
    ["treasurer", "ranjith-treasurer", "treasurer"]
  ] as const)("returns %s from Member Ledger to the Finance parent without selection or mutation", async (role, identity, roleSlug) => {
    authState.role = role;
    authState.identity = identity;
    authState.session.role = role;
    authState.session.identity = identity;
    authState.session.membership.role.slug = roleSlug;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: /^Finance/ }));
    fireEvent.click(screen.getByRole("button", { name: /^Member Ledger \/ Dues/ }));
    fireEvent.click(screen.getByRole("button", { name: "Member Ledger" }));
    expect(await screen.findByRole("heading", { name: "Member Ledger / Dues", level: 1 })).toBeInTheDocument();

    const back = screen.getByRole("button", { name: "← Finance" });
    expect(back).toBeEnabled();
    fireEvent.click(back);

    expect(await screen.findByRole("heading", { name: "Finance", level: 1 })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "← Finance" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: /^Finance/ })).toHaveClass("activeGroup");
    expect(screen.getByRole("heading", { name: "Club Account Statement", level: 2 })).toBeInTheDocument();
    expect(screen.queryByTestId("finance-backend")).not.toBeInTheDocument();
    expect(screen.queryByRole("combobox", { name: "Member" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Create Draft|Record Payment/ })).not.toBeInTheDocument();
    expect(vi.mocked(globalThis.fetch).mock.calls.every(([, init]) => !init?.method || init.method === "GET")).toBe(true);
  });

  it("does not expose the officer Finance return control to a Normal Member", async () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.role = "member";
    authState.session.identity = "sajith-member";
    authState.session.membership.role.slug = "member";
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: "My Finance Summary" }));
    expect(await screen.findByRole("heading", { name: "My Finance Summary", level: 1 })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "← Finance" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /^Finance$/ })).not.toBeInTheDocument();
  });

  it("returns direct Normal Member dues to a consistent self-finance parent view", async () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.role = "member";
    authState.session.identity = "sajith-member";
    authState.session.membership.role.slug = "member";
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: "My Dues" }));
    expect(await screen.findByRole("heading", { name: "My Dues", level: 1 })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Back to Finance Summary" }));

    expect(await screen.findByRole("heading", { name: "My Finance Summary", level: 1 })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "My Finance Summary", level: 2 })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "My Finance Summary" })).toHaveClass("active");
    expect(screen.getByRole("button", { name: "My Dues" })).not.toHaveClass("active");
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("member 5");
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("personal true");
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("view summary");
  });

  it("returns direct Normal Member payment history to the same consistent self-finance parent view", async () => {
    authState.role = "member";
    authState.identity = "sajith-member";
    authState.member = { id: 5, member_code: "4794369", full_name: "Sajith K" };
    authState.session.role = "member";
    authState.session.identity = "sajith-member";
    authState.session.membership.role.slug = "member";
    authState.session.membership.member = authState.member;
    installFetchScenario({ documentStatus: 200, documentPayload: { profile_documents: [] } });
    render(<Prototype />);

    fireEvent.click(screen.getByRole("button", { name: "My Payment History" }));
    expect(await screen.findByRole("heading", { name: "Transaction History", level: 1 })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Back to Finance Summary" }));

    expect(await screen.findByRole("heading", { name: "My Finance Summary", level: 1 })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "My Finance Summary", level: 2 })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "My Finance Summary" })).toHaveClass("active");
    expect(screen.getByRole("button", { name: "My Payment History" })).not.toHaveClass("active");
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("member 5");
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("personal true");
    expect(screen.getByTestId("finance-backend")).toHaveTextContent("view summary");
  });

});
