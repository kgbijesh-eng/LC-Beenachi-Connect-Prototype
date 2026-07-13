import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import FinanceBackend from "../FinanceBackend";

const auth = vi.hoisted(() => ({
  session: {
    token: "history-token",
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

const payment = {
  id: 701,
  member_id: 101,
  amount_paise: 125000,
  amount_display: "1250.00",
  currency: "INR",
  payment_date: "2026-07-12",
  payment_mode: "upi",
  external_reference: "UPI-TRACE-701",
  status: "recorded",
  allocations: [{ id: 901, member_charge_id: 501, amount_paise: 125000 }],
  receipt: { id: 801, receipt_number: "LCB/2026-27/RCP/0008", status: "issued" },
  replacement_payment_id: null,
  lock_version: 0
};

function jsonResponse(body: unknown, status = 200) {
  return Promise.resolve(new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" }
  }));
}

function installHistoryFetch(options: { mismatchedDetail?: boolean; withoutReceipt?: boolean } = {}) {
  const listedPayment = options.withoutReceipt ? { ...payment, receipt: null } : payment;
  return vi.spyOn(globalThis, "fetch").mockImplementation((input) => {
    const url = String(input);
    if (url.endsWith("/clubs/7/members?per_page=100&page=1")) {
      return jsonResponse({ members, meta: { current_page: 1, last_page: 1 } });
    }
    if (url.includes("/finance/members/101/payments")) {
      return jsonResponse({ data: [listedPayment], meta: { current_page: 1, last_page: 1, total: 1 } });
    }
    if (url.includes("/finance/members/102/payments")) {
      return jsonResponse({ data: [], meta: { current_page: 1, last_page: 1, total: 0 } });
    }
    if (url.endsWith("/clubs/7/finance/payments/701")) {
      return jsonResponse({ data: options.mismatchedDetail ? { ...payment, member_id: 102 } : listedPayment });
    }
    if (url.endsWith("/clubs/7/finance/receipts/801")) {
      return jsonResponse({ data: { id: 801, payment_id: 701, member_id: 101, receipt_number: "LCB/2026-27/RCP/0008", receipt_date: "2026-07-12", financial_year: "2026-27", status: "issued", amount_paise: 125000, amount_display: "1250.00", currency: "INR" } });
    }
    if (url.endsWith("/clubs/7/finance/me/payments")) {
      return jsonResponse({ data: [listedPayment], meta: { current_page: 1, last_page: 1, total: 1 } });
    }
    throw new Error("Unexpected fetch: " + url);
  });
}

describe("Member payment history and receipt traceability", () => {
  beforeEach(() => vi.restoreAllMocks());
  afterEach(() => cleanup());

  it("does not default an officer to the first member", async () => {
    const fetchMock = installHistoryFetch();
    render(<FinanceBackend role="president" view="payment-history" />);

    expect(await screen.findByRole("combobox", { name: "Member" })).toHaveValue("0");
    expect(screen.getByText("Please select a member to view payment history.")).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/finance/members/"))).toBe(false);
  });

  it("opens the exact canonical payment and shows backend allocations", async () => {
    const fetchMock = installHistoryFetch();
    render(<FinanceBackend role="treasurer" view="payment-history" />);

    fireEvent.change(await screen.findByRole("combobox", { name: "Member" }), { target: { value: "101" } });
    const row = await screen.findByRole("article", { name: "Payment 701" });
    fireEvent.click(within(row).getByRole("button", { name: "View Payment Details" }));

    expect(await screen.findByRole("heading", { name: "Payment Details" })).toBeInTheDocument();
    expect(screen.getByText("UPI-TRACE-701")).toBeInTheDocument();
    expect(screen.getByText(/Allocation ID 901/)).toBeInTheDocument();
    expect(screen.getByText(/Obligation ID 501/)).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith("/clubs/7/finance/payments/701"))).toBe(true);
    expect(fetchMock.mock.calls.every(([, init]) => init?.method !== "POST")).toBe(true);
  });

  it("fails closed when payment detail resolves to another member", async () => {
    installHistoryFetch({ mismatchedDetail: true });
    render(<FinanceBackend role="secretary" view="payment-history" />);

    fireEvent.change(await screen.findByRole("combobox", { name: "Member" }), { target: { value: "101" } });
    fireEvent.click(await screen.findByRole("button", { name: "View Payment Details" }));

    expect(await screen.findByText("The selected payment is unavailable for this member and club.")).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Payment Details" })).not.toBeInTheDocument();
  });

  it("shows a receipt action only for a real backend receipt and resolves its canonical ID", async () => {
    installHistoryFetch();
    render(<FinanceBackend role="president" view="payment-history" />);

    fireEvent.change(await screen.findByRole("combobox", { name: "Member" }), { target: { value: "101" } });
    fireEvent.click(await screen.findByRole("button", { name: "View Payment Details" }));
    fireEvent.click(await screen.findByRole("button", { name: "View Receipt Trace" }));

    expect(await screen.findByRole("heading", { name: "Receipt Trace" })).toBeInTheDocument();
    expect(screen.getByText("LCB/2026-27/RCP/0008")).toBeInTheDocument();
    expect(screen.getByText("Receipt ID 801")).toBeInTheDocument();
    expect(screen.getByText("Payment ID 701")).toBeInTheDocument();
  });

  it("does not offer receipt traceability when the backend payment has no receipt", async () => {
    installHistoryFetch({ withoutReceipt: true });
    render(<FinanceBackend role="president" view="payment-history" />);

    fireEvent.change(await screen.findByRole("combobox", { name: "Member" }), { target: { value: "101" } });
    fireEvent.click(await screen.findByRole("button", { name: "View Payment Details" }));

    await screen.findByRole("heading", { name: "Payment Details" });
    expect(screen.queryByRole("button", { name: "View Receipt Trace" })).not.toBeInTheDocument();
    expect(screen.getByText("Receipt unavailable")).toBeInTheDocument();
  });

  it("keeps a Normal Member on exact self-history endpoints", async () => {
    const fetchMock = installHistoryFetch();
    render(<FinanceBackend role="member" view="payment-history" isPersonal />);

    fireEvent.click(await screen.findByRole("button", { name: "View Payment Details" }));
    expect(await screen.findByRole("heading", { name: "Payment Details" })).toBeInTheDocument();
    expect(screen.queryByRole("combobox", { name: "Member" })).not.toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith("/clubs/7/finance/me/payments"))).toBe(true);
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/clubs/7/members?"))).toBe(false);
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/finance/payments/701"))).toBe(false);
  });
});
