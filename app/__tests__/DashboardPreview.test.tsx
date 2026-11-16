import { StrictMode } from "react";
import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import DashboardPreview from "../DashboardPreview";

const auth = vi.hoisted(() => ({
  authenticatedFetch: vi.fn(),
  session: {
    role: "treasurer",
    user: { name: "Synthetic Member" },
    membership: {
      club: { id: 7, name: "Synthetic Lions Club" },
      member: null as { id: number } | null,
      permissions: [] as { slug: string }[],
      authority_as_of: "2026-11-15T00:00:00Z",
      effective_office: { office: "treasurer", effective_until: "2099-01-01T00:00:00Z" }
    }
  }
}));

vi.mock("../DevAuthSession", async importOriginal => ({
  ...await importOriginal<typeof import("../DevAuthSession")>(),
  useDevAuth: () => auth
}));

const grants = (...slugs: string[]) => slugs.map(slug => ({ slug }));
const report = (amount: string) => ({
  finance_reports: {
    club_id: 7,
    outstanding_dues_summary: { total_outstanding_amount: amount, members_with_dues_count: amount === "0.00" ? 0 : 1 },
    payment_collection_summary: { posted_payment_total: "42.00", posted_payment_count: 2 }
  }
});

beforeEach(() => {
  auth.session.role = "treasurer";
  auth.session.membership.member = null;
  auth.session.membership.permissions = grants("club.view", "members.view", "finance.club.view", "finance.manage", "finance.club.statement.view", "finance.club.expenses.create", "events.view", "announcements.view");
  auth.authenticatedFetch.mockReset();
});

afterEach(cleanup);

it("shows one verified officer priority and opens existing finance destinations without writes", async () => {
  auth.authenticatedFetch.mockImplementation(async (path: string) => Response.json(path.endsWith("/reports") ? report("125.00") : { data: [] }));
  const navigate = vi.fn();
  render(<StrictMode><DashboardPreview navigate={navigate} /></StrictMode>);

  expect(await screen.findByText("₹125.00")).toBeVisible();
  expect(screen.getByText("1 member has outstanding dues")).toBeVisible();
  expect(screen.getByText("₹42.00")).toBeVisible();
  expect(screen.getByText("Posted payment count: 2")).toBeVisible();
  fireEvent.click(screen.getByRole("button", { name: /Review member-wise dues/ }));
  fireEvent.click(screen.getByRole("button", { name: "Record Payment" }));
  fireEvent.click(screen.getByRole("button", { name: "Open Statement" }));
  fireEvent.click(screen.getByRole("button", { name: "Record Expense" }));
  expect(navigate.mock.calls).toEqual([["finance-report"], ["record-payment"], ["statement"], ["record-expense"]]);
  expect(auth.authenticatedFetch.mock.calls.map(([path]) => path).filter(path => !path.includes("/account-statement?"))).toEqual([
    "/clubs/7/finance/reports",
    "/clubs/7/events?scope=upcoming&per_page=10",
    "/clubs/7/announcements?scope=current&per_page=3&sort=published_at&direction=desc"
  ]);
  expect(auth.authenticatedFetch.mock.calls.every(([, init]) => !init.method)).toBe(true);
});

it("shows personal dues, recent receipts, and club activity without officer queries or shortcuts", async () => {
  auth.session.role = "member";
  auth.session.membership.member = { id: 9 };
  auth.session.membership.permissions = grants("finance.self.view", "members.view", "events.view", "announcements.view");
  auth.authenticatedFetch.mockImplementation(async (path: string) => {
    if (path.endsWith("/summary")) return Response.json({ data: { member_id: 9, currency: "INR", outstanding_paise: 12300 } });
    if (path.includes("/me/payments")) return Response.json({ data: [{ id: 1, member_id: 9, amount_paise: 2000, payment_date: "2026-11-14", receipt: { status: "issued", receipt_number: "TEST-9" } }] });
    if (path.includes("/events?")) return Response.json({ data: [{ club_id: 7, status: "published", title: "Synthetic meeting", start_date: "2026-11-21" }] });
    return Response.json({ data: [{ id: 2, title: "Synthetic announcement", published_at: "2026-11-13T00:00:00Z" }] });
  });
  const navigate = vi.fn();
  render(<StrictMode><DashboardPreview navigate={navigate} /></StrictMode>);

  expect(await screen.findByText("₹123.00")).toBeVisible();
  expect(await screen.findByText("TEST-9")).toBeVisible();
  expect(await screen.findByText("Synthetic meeting")).toBeVisible();
  expect(await screen.findByText("Synthetic announcement")).toBeVisible();
  expect(screen.queryByRole("button", { name: /Record payment|Club Account Statement|Outstanding dues/ })).toBeNull();
  fireEvent.click(screen.getByRole("button", { name: /View my dues/ }));
  fireEvent.click(screen.getByRole("button", { name: /View payment history/ }));
  fireEvent.click(screen.getByRole("button", { name: "My digital card" }));
  expect(navigate.mock.calls).toEqual([["my-dues"], ["my-receipts"], ["digital-card"]]);
  expect(auth.authenticatedFetch.mock.calls.map(([path]) => path).filter(path => !path.includes("/account-statement?"))).toEqual([
    "/clubs/7/finance/me/summary", "/clubs/7/finance/me/payments?status=posted&per_page=5",
    "/clubs/7/events?scope=upcoming&per_page=10", "/clubs/7/announcements?scope=current&per_page=3&sort=published_at&direction=desc"
  ]);
});

it.each([true, false])("distinguishes confirmed zero from an unavailable report (available: %s)", async available => {
  auth.session.membership.permissions = grants("club.view", "finance.club.view");
  auth.authenticatedFetch.mockResolvedValue(available ? Response.json(report("0.00")) : Response.json({}, { status: 503 }));
  render(<DashboardPreview navigate={vi.fn()} />);
  expect(screen.getAllByText("Loading…").length).toBeGreaterThan(0);
  expect(await screen.findByText(available ? "No outstanding dues in the posted member ledger" : "Unable to verify member dues.")).toBeVisible();
  expect(screen.queryByRole("button", { name: "Record Payment" })).toBeNull();
  expect(screen.queryByRole("button", { name: "Open Statement" })).toBeNull();
  expect(screen.queryByRole("button", { name: "Record Expense" })).toBeNull();
  if (available) expect(screen.getByText("₹0.00")).toBeVisible();
  else expect(screen.queryByText("₹0.00")).toBeNull();
});

it.each([true, false])("distinguishes member loading from confirmed zero or failed verification (available: %s)", async available => {
  auth.session.role = "member";
  auth.session.membership.member = { id: 9 };
  auth.session.membership.permissions = grants("finance.self.view");
  auth.authenticatedFetch.mockImplementation(async (path: string) => path.endsWith("/summary")
    ? available ? Response.json({ data: { member_id: 9, currency: "INR", outstanding_paise: 0 } }) : Response.json({}, { status: 503 })
    : Response.json({ data: [] }));
  render(<DashboardPreview navigate={vi.fn()} />);

  expect(screen.getByText("Checking dues")).toBeVisible();
  expect(screen.queryByText("₹0.00")).toBeNull();
  expect(await screen.findByText(available ? "Up to date" : "Your balance could not be verified.")).toBeVisible();
  if (available) expect(screen.getByText("₹0.00")).toBeVisible();
  else {
    expect(screen.queryByText("₹0.00")).toBeNull();
    expect(screen.queryByText("Up to date")).toBeNull();
  }
});

it("keeps verified dues visible when the posted payment fields are invalid", async () => {
  const data = report("125.00");
  auth.authenticatedFetch.mockImplementation(async (path: string) => Response.json(path.endsWith("/reports")
    ? { finance_reports: { ...data.finance_reports, payment_collection_summary: { posted_payment_total: null, posted_payment_count: -1 } } }
    : { data: [] }));
  render(<DashboardPreview navigate={vi.fn()} />);

  expect(await screen.findByText("₹125.00")).toBeVisible();
  expect(screen.getByText("Posted payment count: Unavailable")).toBeVisible();
  expect(screen.queryByText("₹0.00")).toBeNull();
});

it("shows accounting activation failure without displaying a synthetic zero", async () => {
  auth.session.membership.permissions = grants("finance.club.statement.view");
  auth.authenticatedFetch.mockResolvedValue(Response.json({ code: "ACCOUNTING_NOT_ACTIVATED" }, { status: 409 }));
  render(<DashboardPreview navigate={vi.fn()} />);
  expect((await screen.findAllByText("Accounting not activated")).length).toBeGreaterThan(0);
  expect(screen.queryByText("₹0.00")).toBeNull();
  expect(auth.authenticatedFetch.mock.calls[0][0]).toMatch(/account-statement\?.*dashboard=1/);
});

it("keeps statement-based accounting hidden without the statement grant", () => {
  auth.session.membership.permissions = [];
  render(<DashboardPreview navigate={vi.fn()} />);
  expect(screen.queryByRole("heading", { name: "Cash & Bank" })).toBeNull();
  expect(auth.authenticatedFetch).not.toHaveBeenCalled();
});

it("passes the covered range to the statement and keeps confirmed zero distinct from an unavailable source", async () => {
  auth.session.membership.permissions = grants("finance.club.statement.view");
  const navigate = vi.fn();
  auth.authenticatedFetch.mockImplementation(async (path: string) => {
    const query = new URL(path, "https://example.test").searchParams;
    return Response.json({ data: {
      club_id: 7, currency: "INR", amount_unit: "paise",
      lions_year: { label: query.get("lions_year") },
      range: { from: query.get("from"), to: query.get("to") },
      source: { cutover_date: query.get("from") },
      integrity: { account_equations_reconcile: true },
      funds: [], traceability: { journals: [] }
    } });
  });
  const view = render(<DashboardPreview navigate={navigate} />);
  fireEvent.click(await screen.findByRole("button", { name: /Open statement for this period/ }));
  const query = new URL(auth.authenticatedFetch.mock.calls[0][0], "https://example.test").searchParams;
  expect(navigate).toHaveBeenCalledWith("statement", {
    lionsYear: query.get("lions_year"), from: query.get("from"), to: query.get("to")
  });
  expect(screen.getAllByText("₹0.00").length).toBeGreaterThan(0);

  view.unmount();
  auth.authenticatedFetch.mockResolvedValue(Response.json({}, { status: 503 }));
  render(<DashboardPreview navigate={navigate} />);
  expect((await screen.findAllByText("Unable to verify")).length).toBeGreaterThan(0);
  expect(screen.queryByText("₹0.00")).toBeNull();
});
