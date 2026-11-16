import { cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import Prototype from "../portal/page";

type SyntheticRole = "president-admin" | "secretary" | "treasurer" | "member";

const API = "http://127.0.0.1:8000/api/v1";

function syntheticSession(role: SyntheticRole, name = "Synthetic Officer", extraPermissions: string[] = []) {
  return {
    instance_organisation: { id: 1, code: "LCB", name: "LC Beenachi" },
    user: { id: 700, name, email: `${role}@example.test` },
    club_memberships: [{
      id: 40,
      club_id: 1,
      status: "active",
      club: { id: 1, code: "LCB", name: "LC Beenachi" },
      role: { slug: role, name: role },
      authority_as_of: "2020-01-01T00:00:00Z",
      effective_office: role === "member" ? null : { office: role === "president-admin" ? "president" : role, effective_until: "2099-01-01T00:00:00Z" },
      permissions: ["members.view", "club.view", ...(role === "member" ? ["finance.self.view"] : ["finance.club.view"]), ...extraPermissions].map(slug => ({ slug })),
      member: { id: 1, member_code: "1000001", full_name: name }
    }]
  };
}

function syntheticMembers(status = "active") {
  return Array.from({ length: 47 }, (_, index) => ({
    id: index + 1,
    club_id: 1,
    user_id: index === 0 ? 700 : null,
    member_code: String(1_000_001 + index),
    full_name: `Synthetic Member ${index + 1}`,
    status,
    joined_at: "2020-01-01",
    blood_group: null,
    blood_donor_consent: false
  }));
}

function detailRecord(record: ReturnType<typeof syntheticMembers>[number], birthdayToday = false) {
  const today = new Date();
  const month = String(today.getMonth() + 1).padStart(2, "0");
  const day = String(today.getDate()).padStart(2, "0");
  return {
    ...record,
    birth_date: birthdayToday ? `1980-${month}-${day}` : null,
    wedding_anniversary: null
  };
}

async function signIn() {
  fireEvent.change(screen.getByLabelText("Email"), { target: { value: "synthetic@example.test" } });
  fireEvent.change(screen.getByLabelText("Password"), { target: { value: "Synthetic!Runtime123" } });
  fireEvent.click(screen.getByRole("button", { name: "Sign in" }));
  await screen.findByRole("button", { name: "Sign out" });
}

function successfulFetch(options: {
  role?: SyntheticRole;
  name?: string;
  memberStatus?: string;
  birthdayToday?: boolean;
  withoutDirectory?: boolean;
  permissions?: string[];
} = {}) {
  const members = syntheticMembers(options.memberStatus);
  const announcement = {
    id: 41, club_id: 1, title: "Synthetic club notice", body: "Synthetic announcement details.",
    priority: "normal", effective_status: "published", stored_status: "published",
    audience: "club-members", published_at: "2026-07-28T10:00:00Z", is_read: true
  };
  return vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
    const url = String(input);
    if (url === `${API}/auth/login`) {
      return new Response(JSON.stringify({ token: "synthetic-dashboard-token" }), { status: 200 });
    }
    if (url === `${API}/auth/me`) {
      return new Response(JSON.stringify(syntheticSession(options.role ?? "president-admin", options.name, options.permissions)), { status: 200 });
    }
    if (url === `${API}/clubs/1/members/me`) return Response.json({ member: members[0] });
    if (url.startsWith(`${API}/clubs/1/events?`)) {
      return Response.json({ data: [{
        id: 12, club_id: 1, title: "Synthetic club meeting", type: "meeting", status: "published",
        visibility: "members", is_all_day: true, start_date: "2099-01-01"
      }] });
    }
    if (url.startsWith(`${API}/clubs/1/announcements?`)) return Response.json({ data: [announcement] });
    if (url === `${API}/clubs/1/announcements/41`) return Response.json({ data: announcement });
    if (url === `${API}/clubs/1/finance/me/summary`) {
      return Response.json({ data: { member_id: 1, currency: "INR", outstanding_paise: 0 } });
    }
    if (url.startsWith(`${API}/clubs/1/finance/me/payments?`)) return Response.json({ data: [] });
    if (url === `${API}/clubs/1/finance/reports`) {
      return Response.json({ finance_reports: {
        club_id: 1,
        outstanding_dues_summary: { members_with_dues_count: 1, total_outstanding_amount: "125.00" },
        payment_collection_summary: { posted_payment_total: "0.00", posted_payment_count: 0 },
        club_finance_overview: { total_debit_amount: "125.00", total_credit_amount: "0.00", current_dues_amount: "125.00" },
        member_wise_dues_summary: []
      } });
    }
    if (url === `${API}/clubs/1/finance/balance-sheet`) return Response.json({ balance_sheet: null });
    if (url === `${API}/clubs/1/members` && !options.withoutDirectory) {
      return new Response(JSON.stringify({ members }), { status: 200 });
    }
    const detailMatch = /\/clubs\/1\/members\/(\d+)$/.exec(url);
    if (detailMatch) {
      const record = members.find((member) => member.id === Number(detailMatch[1]));
      return new Response(JSON.stringify({
        member: detailRecord(record!, Boolean(options.birthdayToday && record?.id === 1))
      }), { status: 200 });
    }
    if (url === `${API}/auth/logout`) {
      return new Response("{}", { status: 200 });
    }
    throw new Error(`Unexpected synthetic request: ${url}`);
  });
}

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe("production dashboard UI", () => {
  it("renders production labels from the verified backend session", async () => {
    successfulFetch({ name: "LC Beenachi President" });
    render(<Prototype />);
    await signIn();

    expect(screen.getByText("Club Operations Platform")).toBeInTheDocument();
    expect(screen.getByText("Signed in as LC Beenachi President")).toBeInTheDocument();
    expect(screen.queryByText(/President Test Role/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/Milestone 1A prototype/i)).not.toBeInTheDocument();
  });

  it.each([
    ["president-admin", "Synthetic President"],
    ["secretary", "Synthetic Secretary"],
    ["treasurer", "Synthetic Treasurer"],
    ["member", "Synthetic Member"]
  ] as const)("renders the verified %s identity and role", async (role, name) => {
    successfulFetch({ role, name });
    render(<Prototype />);
    await signIn();
    expect(screen.getByText(`Signed in as ${name}`)).toBeInTheDocument();
  });

  it("does not request protected dashboard data before authentication", () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    render(<Prototype />);
    expect(fetchMock).not.toHaveBeenCalled();
    expect(screen.queryByText("Loading member records…")).not.toBeInTheDocument();
  });

  it("opens the existing report with the same outstanding balance shown on the default dashboard", async () => {
    const fetchMock = successfulFetch({ role: "treasurer" });
    render(<Prototype />);
    await signIn();

    expect(await screen.findByRole("heading", { name: "₹125.00 in posted ledger dues" })).toBeVisible();
    fireEvent.click(screen.getByRole("button", { name: /Review posted dues/ }));
    expect(await screen.findByRole("heading", { name: "Club Finance Reports" })).toBeVisible();
    await screen.findByRole("heading", { name: "Outstanding Dues Summary" });
    const outstanding = screen.getByText("Total Outstanding").parentElement!;
    expect(within(outstanding).getByText("₹125")).toBeVisible();
    expect(fetchMock.mock.calls.filter(([url]) => url === `${API}/clubs/1/finance/reports`)).toHaveLength(2);
  });

  it("opens the member calendar, event details, and announcements without loading the directory", async () => {
    const fetchMock = successfulFetch({
      role: "member",
      withoutDirectory: true,
      permissions: ["events.view", "announcements.view"]
    });
    render(<Prototype />);
    await signIn();

    fireEvent.click(await screen.findByRole("button", { name: /Open Events & Meetings/ }));
    expect(await screen.findByRole("heading", { name: "Synthetic club meeting" })).toBeVisible();
    fireEvent.click(screen.getByRole("button", { name: "View details" }));
    expect(await screen.findByRole("heading", { name: "Event / Meeting Details", level: 2 })).toBeVisible();
    expect(screen.getByText("Synthetic club meeting")).toBeVisible();
    fireEvent.click(screen.getByRole("button", { name: "← Events & Meetings" }));
    expect(await screen.findByRole("heading", { name: "Synthetic club meeting" })).toBeVisible();

    fireEvent.click(screen.getByRole("button", { name: "Home" }));
    fireEvent.click(await screen.findByRole("button", { name: /Open announcements/i }));
    expect(await screen.findByText("Synthetic announcement details.")).toBeVisible();

    const requests = fetchMock.mock.calls.map(([url]) => new URL(String(url)).pathname);
    expect(requests.filter(path => path === "/api/v1/clubs/1/members/me")).toHaveLength(1);
    expect(requests).not.toContain("/api/v1/clubs/1/members");
  });

  it("loads only the linked member and personal finance on the default member dashboard, then clears on sign-out", async () => {
    const fetchMock = successfulFetch({ role: "member" });
    render(<Prototype />);
    await signIn();
    expect(await screen.findByText("No outstanding dues for your assessed charges.")).toBeVisible();
    const nav = screen.getByRole("navigation", { name: "Member navigation" });
    expect(within(nav).queryByRole("button", { name: "Events & Meetings" })).toBeNull();
    expect(within(nav).queryByRole("button", { name: "Announcements" })).toBeNull();
    expect(fetchMock.mock.calls.map(([url]) => String(url)).filter(url => url.includes("/clubs/"))).toEqual([
      `${API}/clubs/1/members/me`, `${API}/clubs/1/finance/me/summary`, `${API}/clubs/1/finance/me/payments?status=posted&per_page=5`
    ]);
    fireEvent.click(screen.getByRole("button", { name: "Sign out" }));
    await screen.findByRole("button", { name: "Sign in" });
    expect(screen.queryByRole("region", { name: "Member dashboard" })).toBeNull();
  });
});