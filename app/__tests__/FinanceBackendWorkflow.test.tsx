import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import FinanceBackend from "../FinanceBackend";
import { DevAuthProvider, useDevAuth } from "../DevAuthSession";

const onRecordPayment = vi.fn();
let useRealSession = false;
const auth = vi.hoisted(() => ({
  authenticatedFetch: (url: string, init?: RequestInit) => fetch(url, init),
  session: {
    token: "test-token",
    role: "president",
    user: { id: 10 },
    membership: {
      authority_as_of: "2020-01-01T00:00:00Z",
      effective_office: { office: "president", effective_until: "2099-01-01T00:00:00Z" },
      permissions: [{ slug: "finance.manage" }],
      club: { id: 7 },
      member: { id: 101 }
    }
  }
}));

vi.mock("../DevAuthSession", async (importOriginal) => {
  const actual = await importOriginal<typeof import("../DevAuthSession")>();
  return { ...actual, useDevAuth: () => useRealSession ? actual.useDevAuth() : auth };
});

const members = [
  { id: 102, full_name: "First Listed Member", member_code: "M102", status: "active" },
  { id: 101, full_name: "Selected Member", member_code: "M101", status: "active" }
];

const selectedObligation = {
  id: 501,
  club_charge_id: 301,
  member_id: 101,
  title: "July Membership Dues",
  category: "membership_dues",
  status: "issued",
  financial_year: "2026-27",
  due_date: "2026-07-31",
  original_amount_paise: 150000,
  allocated_paise: 25000,
  outstanding_paise: 125000,
  outstanding_display: "1250.00",
  currency: "INR",
  payment_state: "part_paid",
  lock_version: 0
};

function jsonResponse(body: unknown, status = 200) {
  return Promise.resolve(new Response(JSON.stringify(body), {
    status,
    headers: { "Content-Type": "application/json" }
  }));
}

function installFinanceFetch() {
  return vi.spyOn(globalThis, "fetch").mockImplementation((input, init) => {
    const url = String(input);

    if (url.endsWith("/clubs/7/members?per_page=100&page=1")) {
      return jsonResponse({ members, meta: { current_page: 1, last_page: 1 } });
    }
    if (url.includes("/finance/members/101/summary")) {
      return jsonResponse({ data: { member_id: 101, currency: "INR", assessed_paise: 150000, assessed_display: "1500.00", paid_paise: 25000, paid_display: "250.00", outstanding_paise: 125000, outstanding_display: "1250.00", open_obligations: 1 } });
    }
    if (url.includes("/finance/members/102/summary")) {
      return jsonResponse({ data: { member_id: 102, currency: "INR", assessed_paise: 0, assessed_display: "0.00", paid_paise: 0, paid_display: "0.00", outstanding_paise: 0, outstanding_display: "0.00", open_obligations: 0 } });
    }
    if (url.includes("/finance/members/101/charges")) {
      return jsonResponse({ data: [selectedObligation], meta: { current_page: 1, last_page: 1 } });
    }
    if (url.includes("/finance/members/102/charges")) {
      return jsonResponse({ data: [], meta: { current_page: 1, last_page: 1 } });
    }
    if (url.endsWith("/clubs/7/finance/members/101/payments") && init?.method === "POST") {
      return jsonResponse({
        data: {
          id: 901,
          amount_paise: 125000,
          receipt: { id: 801, receipt_number: "LCB/2026-27/RCP/0008", status: "issued" }
        },
        replayed: false
      }, 201);
    }
    if (url.includes("/finance/members/101/payments")) {
      return jsonResponse({ data: [] });
    }
    if (url.includes("/finance/members/102/payments")) {
      return jsonResponse({ data: [] });
    }
    if (url.endsWith("/clubs/7/finance/charges")) {
      return jsonResponse({ data: [] });
    }
    if (url.endsWith("/clubs/7/finance/me/summary")) {
      return jsonResponse({ data: { member_id: 101, currency: "INR", assessed_paise: 150000, assessed_display: "1500.00", paid_paise: 25000, paid_display: "250.00", outstanding_paise: 125000, outstanding_display: "1250.00", open_obligations: 1 } });
    }
    if (url.endsWith("/clubs/7/finance/me/charges")) {
      return jsonResponse({ data: [selectedObligation] });
    }
    if (url.endsWith("/clubs/7/finance/me/payments")) {
      return jsonResponse({ data: [] });
    }

    throw new Error("Unexpected fetch: " + url + " " + (init?.method ?? "GET"));
  });
}

describe("Outstanding Dues to Record Member Payment", () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    onRecordPayment.mockReset();
  });

  afterEach(() => cleanup());

  it("renders only backend outstanding obligations and carries canonical IDs", async () => {
    const fetchMock = installFinanceFetch();

    render(<FinanceBackend role="president" view="outstanding" onRecordPayment={onRecordPayment} />);

    const due = await screen.findByRole("article", { name: "July Membership Dues for Selected Member" });
    expect(within(due).getByText("Obligation ID 501")).toBeInTheDocument();
    expect(within(due).getByText("INR 1,250.00 outstanding")).toBeInTheDocument();
    fireEvent.click(within(due).getByRole("button", { name: "Record Payment" }));

    expect(onRecordPayment).toHaveBeenCalledWith(101, 501);
    expect(fetchMock.mock.calls.every(([, init]) => init?.method !== "POST")).toBe(true);
  });

  it("prefills the exact selected member and obligation without using list order", async () => {
    const fetchMock = installFinanceFetch();

    render(<FinanceBackend role="president" view="record-payment" memberId={101} obligationId={501} />);

    expect(await screen.findByDisplayValue("Selected Member - M101 - ID 101")).toBeInTheDocument();
    expect(screen.getByDisplayValue("July Membership Dues - ID 501 - INR 1,250.00")).toBeInTheDocument();
    expect(screen.getByLabelText("Amount (paise)")).toHaveValue("125000");
    expect(screen.getByText("Review the exact member, obligation, and amount before submitting.")).toBeInTheDocument();
    expect(fetchMock.mock.calls.every(([, init]) => init?.method !== "POST")).toBe(true);
  });

  it("fails closed when an obligation ID does not resolve for the selected member", async () => {
    installFinanceFetch();

    render(<FinanceBackend role="president" view="record-payment" memberId={101} obligationId={999} />);

    expect(await screen.findByText("The selected obligation is unavailable for this member and club.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Review Payment" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Confirm backend submission" })).not.toBeInTheDocument();
  });

  it("refreshes the selected member views from backend truth after explicit submission", async () => {
    const fetchMock = installFinanceFetch();

    render(<FinanceBackend role="president" view="record-payment" memberId={101} obligationId={501} />);

    await screen.findByDisplayValue("July Membership Dues - ID 501 - INR 1,250.00");
    fireEvent.click(screen.getByRole("button", { name: "Review Payment" }));
    expect(fetchMock.mock.calls.every(([, init]) => init?.method !== "POST")).toBe(true);

    fireEvent.click(screen.getByRole("button", { name: "Confirm backend submission" }));

    await waitFor(() => expect(screen.getByText("Backend confirmed payment and receipt.")).toBeInTheDocument());
    expect(screen.getByText(/LCB\/2026-27\/RCP\/0008/)).toBeInTheDocument();
    const post = fetchMock.mock.calls.find(([, init]) => init?.method === "POST");
    expect(String(post?.[0]).endsWith("/clubs/7/finance/members/101/payments")).toBe(true);
    expect(JSON.parse(String(post?.[1]?.body))).toMatchObject({
      amount_paise: 125000,
      allocations: [{ member_charge_id: 501, amount_paise: 125000 }]
    });
    expect(fetchMock.mock.calls.filter(([input]) => String(input).includes("/finance/members/101/summary"))).toHaveLength(2);
    expect(fetchMock.mock.calls.filter(([input]) => String(input).includes("/finance/members/102/summary"))).toHaveLength(0);
  });

  it("fails closed for a normal member on the officer outstanding view", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");

    render(<FinanceBackend role="member" view="outstanding" onRecordPayment={onRecordPayment} />);

    expect(await screen.findByText("This role is not authorized to view club-wide outstanding dues.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Record Payment" })).not.toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("keeps Normal Member personal dues on the self-view endpoints", async () => {
    const fetchMock = installFinanceFetch();

    render(<FinanceBackend role="member" memberId={101} isPersonal />);

    expect(await screen.findByText("Backend Finance data loaded.")).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "My Personal Dues" })).toBeInTheDocument();
    expect(screen.getByText("₹1250.00")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Record Payment" })).not.toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([input]) => String(input).includes("/clubs/7/members?"))).toBe(false);
    expect(fetchMock.mock.calls.some(([input]) => String(input).endsWith("/clubs/7/finance/me/summary"))).toBe(true);
  });
});


function PaymentFormSession() {
  const auth = useDevAuth();
  return auth.session
    ? <FinanceBackend role={auth.session.role} view="record-payment" memberId={101} obligationId={501} />
    : <button onClick={() => void auth.login("member@example.test", "synthetic-password")}>Sign in</button>;
}

it("keeps the production payment rejection visible through an authority refresh", async () => {
  useRealSession = true;
  vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "http://127.0.0.1:8000");
  const fetchMock = installFinanceFetch();
  const financeFetch = fetchMock.getMockImplementation()!;
  let verifications = 0;
  fetchMock.mockImplementation((input, init) => {
    const url = String(input);
    if (url.endsWith("/auth/login")) return jsonResponse({ token: "synthetic-token" });
    if (url.endsWith("/auth/me")) {
      const initial = ++verifications === 1;
      return jsonResponse({
        user: { id: 10, email: "member@example.test", name: "Synthetic member" },
        instance_organisation: { id: 7, code: "LCB", name: "Club" },
        club_memberships: [{
          id: 1, status: "active", club: { id: 7, code: "LCB" }, role: { slug: "member" },
          member: { id: 101 }, authority_as_of: "2020-01-01T00:00:00Z",
          effective_office: initial ? { office: "president", effective_until: "2099-01-01T00:00:00Z" } : null,
          permissions: initial ? [{ slug: "finance.manage" }] : [{ slug: "finance.self.view" }]
        }]
      });
    }
    if (init?.method === "POST" && url.endsWith("/finance/members/101/payments")) {
      return jsonResponse({ message: "Payment authority has ended." }, 403);
    }
    return financeFetch(input, init);
  });
  try {
    render(<DevAuthProvider><PaymentFormSession /></DevAuthProvider>);
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));
    await screen.findByDisplayValue("July Membership Dues - ID 501 - INR 1,250.00");
    fireEvent.click(screen.getByRole("button", { name: "Review Payment" }));
    fireEvent.click(screen.getByRole("button", { name: "Confirm backend submission" }));
    await waitFor(() => expect(verifications).toBe(2));
    expect(await screen.findByText("Payment authority has ended.")).toBeVisible();
    expect(screen.queryByRole("button", { name: "Review Payment" })).toBeNull();
    expect(fetchMock.mock.calls.filter(([url, init]) => init?.method === "POST"
      && String(url).endsWith("/finance/members/101/payments"))).toHaveLength(1);
  } finally {
    cleanup();
    useRealSession = false;
    vi.unstubAllEnvs();
    vi.restoreAllMocks();
  }
});
