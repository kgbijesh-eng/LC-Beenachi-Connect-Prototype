import { StrictMode } from "react";
import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, expect, it, vi } from "vitest";
import DashboardPreview from "../DashboardPreview";

const auth = vi.hoisted(() => ({
  authenticatedFetch: vi.fn(),
  session: {
    role: "treasurer",
    membership: {
      club: { id: 7 },
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
    payment_collection_summary: { posted_payment_total: "42.00" }
  }
});

beforeEach(() => {
  auth.session.role = "treasurer";
  auth.session.membership.member = null;
  auth.session.membership.permissions = grants("club.view", "members.view", "finance.club.view", "finance.manage", "finance.club.statement.view", "events.view", "announcements.view");
  auth.authenticatedFetch.mockReset();
});

afterEach(cleanup);

it("shows one verified officer priority and opens existing finance destinations without writes", async () => {
  auth.authenticatedFetch.mockImplementation(async (path: string) => Response.json(path.endsWith("/reports") ? report("125.00") : { data: [] }));
  const navigate = vi.fn();
  render(<StrictMode><DashboardPreview navigate={navigate} /></StrictMode>);

  expect(await screen.findByRole("heading", { name: "₹125.00 in posted ledger dues" })).toBeVisible();
  fireEvent.click(screen.getByRole("button", { name: /Review posted dues/ }));
  fireEvent.click(screen.getByRole("button", { name: "Record payment" }));
  fireEvent.click(screen.getByRole("button", { name: "Club Account Statement" }));
  fireEvent.click(screen.getByRole("button", { name: "Outstanding dues" }));
  expect(navigate.mock.calls).toEqual([["finance-report"], ["record-payment"], ["statement"], ["outstanding"]]);
  expect(auth.authenticatedFetch.mock.calls.map(([path]) => path)).toEqual([
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

  expect(await screen.findByRole("heading", { name: "₹123.00 in your outstanding dues" })).toBeVisible();
  expect(await screen.findByText("TEST-9")).toBeVisible();
  expect(await screen.findByText("Synthetic meeting")).toBeVisible();
  expect(await screen.findByText("Synthetic announcement")).toBeVisible();
  expect(screen.queryByRole("button", { name: /Record payment|Club Account Statement|Outstanding dues/ })).toBeNull();
  fireEvent.click(screen.getByRole("button", { name: "My dues" }));
  fireEvent.click(screen.getByRole("button", { name: "My receipts" }));
  fireEvent.click(screen.getByRole("button", { name: "My profile" }));
  expect(navigate.mock.calls).toEqual([["my-dues"], ["my-receipts"], ["profile"]]);
  expect(auth.authenticatedFetch.mock.calls.map(([path]) => path)).toEqual([
    "/clubs/7/finance/me/summary", "/clubs/7/finance/me/payments?status=posted&per_page=5",
    "/clubs/7/events?scope=upcoming&per_page=10", "/clubs/7/announcements?scope=current&per_page=3&sort=published_at&direction=desc"
  ]);
});

it.each([true, false])("distinguishes confirmed zero from an unavailable report (available: %s)", async available => {
  auth.session.membership.permissions = grants("club.view", "finance.club.view");
  auth.authenticatedFetch.mockResolvedValue(available ? Response.json(report("0.00")) : Response.json({}, { status: 503 }));
  render(<DashboardPreview navigate={vi.fn()} />);
  expect(screen.getByText("Checking outstanding dues…")).toBeVisible();
  expect(await screen.findByText(available ? "No outstanding dues for the posted periods in this report." : "Unable to verify dues. No priority can be confirmed.")).toBeVisible();
  expect(screen.queryByRole("button", { name: "Record payment" })).toBeNull();
  expect(screen.queryByRole("button", { name: "Club Account Statement" })).toBeNull();
  if (!available) expect(screen.queryByText("₹0.00")).toBeNull();
});
