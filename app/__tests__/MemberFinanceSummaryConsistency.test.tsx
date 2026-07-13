import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import FinanceBackend from "../FinanceBackend";

const auth = vi.hoisted(() => ({
  session: {
    token: "summary-token",
    role: "president",
    user: { id: 10 },
    membership: { club: { id: 7 }, member: { id: 101 } }
  }
}));

vi.mock("../DevAuthSession", () => ({ useDevAuth: () => auth }));

const members = [
  { id: 102, full_name: "First Listed Member", member_code: "M102", status: "active" },
  { id: 101, full_name: "Selected Member", member_code: "M101", status: "active" }
];

const summary = {
  member_id: 101,
  currency: "INR",
  assessed_paise: 150050,
  assessed_display: "1500.50",
  paid_paise: 50025,
  paid_display: "500.25",
  outstanding_paise: 100025,
  outstanding_display: "1000.25",
  open_obligations: 1
};

const obligation = {
  id: 501,
  club_charge_id: 301,
  member_id: 101,
  title: "Member Dues",
  category: "membership_dues",
  status: "issued",
  financial_year: "2026-27",
  due_date: "2026-07-31",
  original_amount_paise: 150050,
  allocated_paise: 50025,
  outstanding_paise: 100025,
  outstanding_display: "1000.25",
  currency: "INR",
  payment_state: "part_paid",
  lock_version: 0
};

const payment = {
  id: 701,
  member_id: 101,
  amount_paise: 50025,
  amount_display: "500.25",
  currency: "INR",
  payment_date: "2026-07-12",
  payment_mode: "upi",
  status: "recorded",
  allocations: [],
  receipt: null,
  lock_version: 0
};

function jsonResponse(body: unknown, status = 200) {
  return Promise.resolve(new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" }
  }));
}

function installSummaryFetch(options: { zero?: boolean; malformed?: boolean; mismatchedMember?: boolean } = {}) {
  const selectedSummary = options.zero
    ? { ...summary, assessed_paise: 0, assessed_display: "0.00", paid_paise: 0, paid_display: "0.00", outstanding_paise: 0, outstanding_display: "0.00", open_obligations: 0 }
    : options.malformed
      ? { ...summary, outstanding_paise: "100025" }
      : options.mismatchedMember
        ? { ...summary, member_id: 102 }
        : summary;

  return vi.spyOn(globalThis, "fetch").mockImplementation((input, init) => {
    const url = String(input);
    if (url.endsWith("/clubs/7/members?per_page=100&page=1")) return jsonResponse({ members, meta: { last_page: 1 } });
    if (url.endsWith("/clubs/7/finance/members/101/summary")) return jsonResponse({ data: selectedSummary });
    if (url.endsWith("/clubs/7/finance/members/101/charges")) return jsonResponse({ data: options.zero ? [] : [obligation], meta: { last_page: 1 } });
    if (url.endsWith("/clubs/7/finance/members/101/payments")) return jsonResponse({ data: [payment], meta: { last_page: 1 } });
    if (url.endsWith("/clubs/7/finance/me/summary")) return jsonResponse({ data: selectedSummary });
    if (url.endsWith("/clubs/7/finance/me/charges")) return jsonResponse({ data: options.zero ? [] : [obligation], meta: { last_page: 1 } });
    if (url.endsWith("/clubs/7/finance/me/payments")) return jsonResponse({ data: [payment], meta: { last_page: 1 } });
    throw new Error(`Unexpected ${init?.method ?? "GET"} request: ${url}`);
  });
}

describe("Member finance summary source truth and navigation", () => {
  beforeEach(() => vi.restoreAllMocks());
  afterEach(() => cleanup());

  it("requires explicit officer selection and never requests the first listed member", async () => {
    const fetchMock = installSummaryFetch();
    render(<FinanceBackend role="president" view="summary" />);

    expect(await screen.findByRole("combobox", { name: "Member" })).toHaveValue("0");
    expect(screen.getByText("Please select a member to view their finance summary.")).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/finance/members/102/"))).toBe(false);
    expect(screen.queryByRole("button", { name: "Create Draft" })).not.toBeInTheDocument();
  });

  it("renders only canonical backend summary totals with integer-paise INR formatting", async () => {
    const fetchMock = installSummaryFetch();
    render(<FinanceBackend role="treasurer" view="summary" />);

    fireEvent.change(await screen.findByRole("combobox", { name: "Member" }), { target: { value: "101" } });

    expect(await screen.findByRole("heading", { name: "Member Finance Summary" })).toBeInTheDocument();
    expect(screen.getByText("INR 1,500.50")).toBeInTheDocument();
    expect(screen.getByText("INR 500.25")).toBeInTheDocument();
    expect(screen.getByText("INR 1,000.25")).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith("/clubs/7/finance/members/101/summary"))).toBe(true);
    expect(fetchMock.mock.calls.every(([, init]) => !init?.method || init.method === "GET")).toBe(true);
  });

  it("keeps zero distinct from unavailable and malformed summary states", async () => {
    installSummaryFetch({ zero: true });
    const { unmount } = render(<FinanceBackend role="member" view="summary" isPersonal />);

    expect((await screen.findAllByText("INR 0.00"))).toHaveLength(3);
    expect(screen.getByText("No outstanding dues.")).toBeInTheDocument();
    expect(screen.queryByText(/unavailable/i)).not.toBeInTheDocument();

    unmount();
    vi.restoreAllMocks();
    installSummaryFetch({ malformed: true });
    render(<FinanceBackend role="member" view="summary" isPersonal />);

    expect(await screen.findByText("Finance summary response is malformed.")).toBeInTheDocument();
    expect(screen.queryByText("INR 0.00")).not.toBeInTheDocument();
  });

  it("retains the exact selected member through dues, history, and summary return navigation", async () => {
    const fetchMock = installSummaryFetch();
    render(<FinanceBackend role="secretary" view="summary" />);

    fireEvent.change(await screen.findByRole("combobox", { name: "Member" }), { target: { value: "101" } });
    await screen.findByText("INR 1,000.25");

    fireEvent.click(screen.getByRole("button", { name: "View Member Dues" }));
    expect(await screen.findByRole("heading", { name: "Member Dues", level: 2 })).toBeInTheDocument();
    expect(screen.getByText("Obligation ID 501")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Back to Finance Summary" }));
    expect(await screen.findByRole("heading", { name: "Member Finance Summary" })).toBeInTheDocument();
    expect(screen.getByRole("combobox", { name: "Member" })).toHaveValue("101");

    fireEvent.click(screen.getByRole("button", { name: "View Payment History" }));
    expect(await screen.findByRole("heading", { name: "Member Payment History" })).toBeInTheDocument();
    expect(screen.getByRole("article", { name: "Payment 701" })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Back to Finance Summary" }));
    expect(screen.getByRole("combobox", { name: "Member" })).toHaveValue("101");
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/finance/members/102/"))).toBe(false);
  });

  it.each(["president", "secretary", "treasurer"] as const)(
    "%s exposes an enabled dues return that preserves the explicit member without mutation",
    async (role) => {
      const fetchMock = installSummaryFetch();
      render(<FinanceBackend role={role} view="summary" />);

      fireEvent.change(await screen.findByRole("combobox", { name: "Member" }), { target: { value: "101" } });
      fireEvent.click(await screen.findByRole("button", { name: "View Member Dues" }));

      const back = await screen.findByRole("button", { name: "Back to Finance Summary" });
      expect(back).toBeEnabled();
      fireEvent.click(back);

      expect(await screen.findByRole("heading", { name: "Member Finance Summary" })).toBeInTheDocument();
      expect(screen.getByRole("combobox", { name: "Member" })).toHaveValue("101");
      expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/finance/members/102/"))).toBe(false);
      expect(fetchMock.mock.calls.every(([, init]) => !init?.method || init.method === "GET")).toBe(true);
    }
  );

  it("returns from the direct Normal Member dues route to self summary without fallback or mutation", async () => {
    const fetchMock = installSummaryFetch();
    render(<FinanceBackend role="member" view="dues" memberId={101} isPersonal />);

    expect(await screen.findByRole("heading", { name: "My Dues", level: 2 })).toBeInTheDocument();
    const back = screen.getByRole("button", { name: "Back to Finance Summary" });
    expect(back).toBeEnabled();
    fireEvent.click(back);

    expect(await screen.findByRole("heading", { name: "My Finance Summary" })).toBeInTheDocument();
    expect(screen.queryByRole("combobox", { name: "Member" })).not.toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith("/clubs/7/finance/me/summary"))).toBe(true);
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/clubs/7/members?"))).toBe(false);
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/finance/members/"))).toBe(false);
    expect(fetchMock.mock.calls.every(([, init]) => !init?.method || init.method === "GET")).toBe(true);
  });

  it("returns from Normal Member payment history to the same self summary without mutation", async () => {
    const fetchMock = installSummaryFetch();
    render(<FinanceBackend role="member" view="summary" memberId={101} isPersonal />);

    fireEvent.click(await screen.findByRole("button", { name: "View Payment History" }));
    expect(await screen.findByRole("heading", { name: "My Payment History" })).toBeInTheDocument();
    const back = screen.getByRole("button", { name: "Back to Finance Summary" });
    expect(back).toBeEnabled();
    fireEvent.click(back);

    expect(await screen.findByRole("heading", { name: "My Finance Summary" })).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/clubs/7/members?"))).toBe(false);
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/finance/members/"))).toBe(false);
    expect(fetchMock.mock.calls.every(([, init]) => !init?.method || init.method === "GET")).toBe(true);
  });

  it("resolves a Normal Member only through linked self-finance routes", async () => {
    const fetchMock = installSummaryFetch();
    render(<FinanceBackend role="member" view="summary" isPersonal />);

    expect(await screen.findByRole("heading", { name: "My Finance Summary" })).toBeInTheDocument();
    expect(screen.queryByRole("combobox", { name: "Member" })).not.toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith("/clubs/7/finance/me/summary"))).toBe(true);
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/clubs/7/members?"))).toBe(false);
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/finance/members/"))).toBe(false);
  });

  it("fails closed when the self summary identifies a different member", async () => {
    installSummaryFetch({ mismatchedMember: true });
    render(<FinanceBackend role="member" view="summary" isPersonal />);

    expect(await screen.findByText("Finance summary does not match the selected member.")).toBeInTheDocument();
    await waitFor(() => expect(screen.queryByText("INR 1,000.25")).not.toBeInTheDocument());
  });
});
