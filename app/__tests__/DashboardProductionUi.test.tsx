import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import Prototype from "../page";

type SyntheticRole = "president-admin" | "secretary" | "treasurer" | "member";

const API = "http://127.0.0.1:8000/api/v1";

function syntheticSession(role: SyntheticRole, name = "Synthetic Officer") {
  return {
    user: { id: 700, name, email: `${role}@example.test` },
    club_memberships: [{
      id: 40,
      club_id: 1,
      club: { id: 1, code: "LCB", name: "LC Beenachi" },
      role: { slug: role, name: role },
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
} = {}) {
  const members = syntheticMembers(options.memberStatus);
  return vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
    const url = String(input);
    if (url === `${API}/auth/login`) {
      return new Response(JSON.stringify({ token: "synthetic-dashboard-token" }), { status: 200 });
    }
    if (url === `${API}/auth/me`) {
      return new Response(JSON.stringify(syntheticSession(options.role ?? "president-admin", options.name)), { status: 200 });
    }
    if (url === `${API}/clubs/1/members`) {
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
    expect(screen.getByText("Signed in as LC Beenachi President — President")).toBeInTheDocument();
    expect(screen.queryByText(/President Test Role/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/Milestone 1A prototype/i)).not.toBeInTheDocument();
  });

  it.each([
    ["president-admin", "President", "Synthetic President"],
    ["secretary", "Secretary", "Synthetic Secretary"],
    ["treasurer", "Treasurer", "Synthetic Treasurer"],
    ["member", "Normal Member", "Synthetic Member"]
  ] as const)("renders the verified %s identity and role", async (role, label, name) => {
    successfulFetch({ role, name });
    render(<Prototype />);
    await signIn();
    expect(screen.getByText(`Signed in as ${name} — ${label}`)).toBeInTheDocument();
  });

  it("does not request protected dashboard data before authentication", () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    render(<Prototype />);
    expect(fetchMock).not.toHaveBeenCalled();
    expect(screen.queryByText("Loading member records…")).not.toBeInTheDocument();
  });

  it("resolves the active count before profile enrichment and sends the bearer token", async () => {
    const members = syntheticMembers();
    let detailSignal: AbortSignal | undefined;
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation((input, init) => {
      const url = String(input);
      if (url === `${API}/auth/login`) return Promise.resolve(new Response(JSON.stringify({ token: "synthetic-dashboard-token" }), { status: 200 }));
      if (url === `${API}/auth/me`) return Promise.resolve(new Response(JSON.stringify(syntheticSession("president-admin")), { status: 200 }));
      if (url === `${API}/clubs/1/members`) return Promise.resolve(new Response(JSON.stringify({ members }), { status: 200 }));
      if (/\/clubs\/1\/members\/\d+$/.test(url)) {
        detailSignal = init?.signal ?? undefined;
        return new Promise(() => undefined);
      }
      if (url === `${API}/auth/logout`) return Promise.resolve(new Response("{}", { status: 200 }));
      return Promise.reject(new Error(`Unexpected synthetic request: ${url}`));
    });

    render(<Prototype />);
    await signIn();

    await waitFor(() => expect(screen.getByText("47")).toBeInTheDocument());
    expect(screen.getByText("Loading birthday information…")).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledWith(`${API}/clubs/1/members`, expect.objectContaining({
      headers: expect.objectContaining({ Authorization: "Bearer synthetic-dashboard-token" }),
      signal: expect.any(AbortSignal)
    }));

    fireEvent.click(screen.getByRole("button", { name: "Sign out" }));
    await screen.findByRole("button", { name: "Sign in" });
    expect(detailSignal?.aborted).toBe(true);
    expect(screen.queryByText("47")).not.toBeInTheDocument();
  });

  it("renders successful birthday data and does not repeat completed requests", async () => {
    const fetchMock = successfulFetch({ birthdayToday: true });
    render(<Prototype />);
    await signIn();

    await screen.findByText("1 member birthday today");
    expect(screen.getByText("47")).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledTimes(50);
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(50));
  });

  it("renders a clear empty birthday state and preserves a valid zero count", async () => {
    successfulFetch({ memberStatus: "inactive" });
    render(<Prototype />);
    await signIn();

    await screen.findByText("No member birthdays today");
    const activeMemberCard = screen.getByText("Active Members").closest(".compactDashboardCard");
    expect(activeMemberCard).not.toBeNull();
    expect(within(activeMemberCard as HTMLElement).getByText("0")).toBeInTheDocument();
  });

  it.each([
    ["HTTP failure", () => Promise.resolve(new Response("{}", { status: 503 }))],
    ["network failure", () => Promise.reject(new Error("synthetic network failure"))],
    ["invalid response", () => Promise.resolve(new Response(JSON.stringify({ members: "invalid" }), { status: 200 }))]
  ])("exits loading safely after %s", async (_case, memberResponse) => {
    vi.spyOn(globalThis, "fetch").mockImplementation((input) => {
      const url = String(input);
      if (url === `${API}/auth/login`) return Promise.resolve(new Response(JSON.stringify({ token: "synthetic-dashboard-token" }), { status: 200 }));
      if (url === `${API}/auth/me`) return Promise.resolve(new Response(JSON.stringify(syntheticSession("president-admin")), { status: 200 }));
      if (url === `${API}/clubs/1/members`) return memberResponse();
      return Promise.reject(new Error(`Unexpected synthetic request: ${url}`));
    });

    render(<Prototype />);
    await signIn();

    await screen.findByText("Unable to load member records");
    expect(screen.getByText("Birthday information unavailable")).toBeInTheDocument();
    expect(screen.queryByText("Loading member records…")).not.toBeInTheDocument();
    expect(screen.queryByText("Loading birthday information…")).not.toBeInTheDocument();
    expect(screen.queryByText(/synthetic network failure/i)).not.toBeInTheDocument();
  });
});
