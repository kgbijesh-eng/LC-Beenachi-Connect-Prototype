import React from "react";
import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const auth = vi.hoisted(() => ({
  session: {
    token: "statement-token",
    membership: { club: { id: 7, name: "LC Beenachi" } }
  } as { token: string; membership: { club: { id: number; name: string } } } | null,
  authenticatedFetch: vi.fn()
}));

vi.mock("../DevAuthSession", () => ({ useDevAuth: () => auth }));

import ClubAccountStatementBackend, { formatInrPaise } from "../ClubAccountStatementBackend";

const emptyTotals = { opening_balance_paise: 0, period_debit_paise: 0, period_credit_paise: 0, net_movement_paise: 0, closing_balance_paise: 0 };
const trial = { opening_debits_paise: 100000, opening_credits_paise: 100000, period_debits_paise: 25050, period_credits_paise: 25050, closing_debits_paise: 125050, closing_credits_paise: 125050 };

function statement() {
  return {
    contract_version: "CLUB-ACCOUNT-STATEMENT-2026-01",
    snapshot_fingerprint: "a".repeat(64),
    club_id: 7,
    currency: "INR",
    amount_unit: "paise",
    lions_year: { id: 21, label: "2027-28", starts_on: "2027-07-01", ends_on: "2028-06-30" },
    range: { period_id: null, from: "2027-07-01", to: "2028-06-30", is_closed: false },
    source: { cutover_date: "2027-07-01", journal_count: 2, journal_line_count: 4 },
    funds: [
      {
        fund_code: "GENERAL_CLUB_FUND", display_name: "General Club Fund",
        totals: { opening_balance_paise: 100000, period_debit_paise: 25050, period_credit_paise: 0, net_movement_paise: 25050, closing_balance_paise: 125050 }, trial_balance: trial,
        classifications: [{ classification: "CASH_AND_BANK", totals: emptyTotals, accounts: [{ account_id: "acct-public", account_code: "CASH-ON-HAND", account_name: "Cash on hand", normal_balance: "debit", statement_classification: "CASH_AND_BANK", fund_code: "GENERAL_CLUB_FUND", opening_balance_paise: 100000, period_debit_paise: 25050, period_credit_paise: 0, net_movement_paise: 25050, closing_balance_paise: 125050, journal_ids: ["journal-public"] }] }]
      },
      { fund_code: "SERVICE_ACTIVITY_FUND", display_name: "Service Activity Fund", totals: emptyTotals, trial_balance: trial, classifications: [{ classification: "INCOME", totals: emptyTotals, accounts: [{ account_id: "service-income", account_code: "SERVICE-INCOME", account_name: "Service income", normal_balance: "credit", statement_classification: "INCOME", fund_code: "SERVICE_ACTIVITY_FUND", ...emptyTotals, journal_ids: [] }] }] },
      { fund_code: "FELLOWSHIP_MEMBER_ACTIVITY_FUND", display_name: "Fellowship and Member Activity Fund", totals: emptyTotals, trial_balance: trial, classifications: [{ classification: "EXPENSES", totals: emptyTotals, accounts: [{ account_id: "fellowship-expense", account_code: "FELLOWSHIP-EXPENSE", account_name: "Fellowship expense", normal_balance: "debit", statement_classification: "EXPENSES", fund_code: "FELLOWSHIP_MEMBER_ACTIVITY_FUND", ...emptyTotals, journal_ids: [] }] }] }
    ],
    overall_trial_balance: trial,
    traceability: { journals: [{ journal_id: "journal-public", reference: "JRN-2027-0002", accounting_date: "2027-07-15", journal_type: "member_payment", description: "Member receipt RCP-021", debit_total_paise: 25050, credit_total_paise: 25050, reversal_of_journal_id: "journal-original", reversed_by_journal_id: null, source_type: "member_payment", source_id: "payment-public", contribution: "period_movement", lines: [{ journal_line_id: "line-public", opening_schedule_row_id: "opening-row-public", payment_allocations: [{ allocation_id: "allocation-public", payment_id: "payment-public", line_role: "asset_debit", amount_paise: 25050 }] }] }] },
    integrity: { period_debits_equal_credits: true, opening_balances_reconcile: true, closing_balances_reconcile: true, fund_balances_reconcile: true }
  };
}

function response(body: unknown, status = 200) {
  return Promise.resolve(new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } }));
}

describe("Club Account Statement frontend integration", () => {
  beforeEach(() => { auth.session = { token: "statement-token", membership: { club: { id: 7, name: "LC Beenachi" } } }; auth.authenticatedFetch.mockReset(); });
  afterEach(() => cleanup());

  it("renders canonical fund balances, classifications, reconciliation, and public traceability", async () => {
    auth.authenticatedFetch.mockImplementation(() => response({ data: statement() }));
    render(<ClubAccountStatementBackend role="treasurer" />);

    expect(await screen.findByText("Canonical statement loaded from the accounting ledger.")).toBeInTheDocument();
    expect(screen.getByText("LC Beenachi")).toBeInTheDocument();
    expect(screen.getByText("2027-07-01", { selector: ".statementMeta span" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "General Club Fund" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Service Activity Fund" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Fellowship and Member Activity Fund" })).toBeInTheDocument();
    const general = screen.getByRole("article", { name: "General Club Fund" });
    expect(within(general).getAllByText("₹1,000.00")).toHaveLength(2);
    expect(screen.getByText("₹250.50", { selector: "strong" })).toBeInTheDocument();
    expect(screen.getAllByText("₹1,250.50").length).toBeGreaterThan(0);
    expect(screen.getByRole("heading", { name: "Cash And Bank" })).toBeInTheDocument();
    expect(screen.getByText(/JRN-2027-0002/)).toBeInTheDocument();
    expect(screen.getByText(/Member receipt RCP-021/)).toBeInTheDocument();
    fireEvent.click(screen.getByText(/JRN-2027-0002/));
    expect(screen.getByText("Reversal of journal journal-original")).toBeInTheDocument();
    expect(screen.getByText("Opening schedule opening-row-public")).toBeInTheDocument();
    expect(screen.getByText(/Payment payment-public · Asset Debit · ₹250.50/)).toBeInTheDocument();
    expect(screen.getAllByText(/Reconciled/).length).toBeGreaterThan(0);
    fireEvent.click(screen.getByText("Technical statement details"));
    expect(screen.getByText("a".repeat(64))).toBeInTheDocument();
  });

  it("sends canonical Lions Year, period, and custom date query parameters", async () => {
    auth.authenticatedFetch.mockImplementation(() => response({ data: statement() }));
    render(<ClubAccountStatementBackend role="president" />);
    await screen.findByText("Canonical statement loaded from the accounting ledger.");

    fireEvent.change(screen.getByLabelText("Lions Year"), { target: { value: "2027-28" } });
    fireEvent.change(screen.getByLabelText("Range type"), { target: { value: "period" } });
    fireEvent.change(screen.getByLabelText("Accounting period ID"), { target: { value: "21" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await waitFor(() => expect(auth.authenticatedFetch).toHaveBeenCalledWith("/clubs/7/finance/account-statement?lions_year=2027-28&period_id=21"));

    fireEvent.change(screen.getByLabelText("Range type"), { target: { value: "custom" } });
    fireEvent.change(screen.getByLabelText("From"), { target: { value: "2027-08-01" } });
    fireEvent.change(screen.getByLabelText("To"), { target: { value: "2027-08-31" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await waitFor(() => expect(auth.authenticatedFetch).toHaveBeenCalledWith("/clubs/7/finance/account-statement?lions_year=2027-28&from=2027-08-01&to=2027-08-31"));
  });

  it("shows loading, valid empty, and backend validation states without stale figures", async () => {
    let resolveRequest: ((value: Response) => void) | undefined;
    auth.authenticatedFetch.mockImplementationOnce(() => new Promise<Response>((resolve) => { resolveRequest = resolve; }));
    const { unmount } = render(<ClubAccountStatementBackend role="secretary" />);
    expect(screen.getByRole("status")).toHaveTextContent("Loading canonical Club Account Statement");
    await waitFor(() => expect(resolveRequest).toBeTypeOf("function"));
    resolveRequest?.(new Response(JSON.stringify({ data: { ...statement(), source: { cutover_date: "2027-07-01", journal_count: 0, journal_line_count: 0 }, funds: statement().funds.map((fund) => ({ ...fund, classifications: fund.classifications.map((classification) => ({ ...classification, accounts: [] })) })), traceability: { journals: [] } } }), { status: 200, headers: { "Content-Type": "application/json" } }));
    expect(await screen.findByText("No statement activity exists for the selected period.")).toBeInTheDocument();
    unmount();

    auth.authenticatedFetch.mockImplementation(() => response({ message: "The selected dates are invalid." }, 422));
    render(<ClubAccountStatementBackend role="secretary" />);
    expect(await screen.findByText("The selected dates are invalid.")).toBeInTheDocument();
    expect(screen.queryByText("Cash on hand")).not.toBeInTheDocument();
  });

  it.each([
    [401, {}, "You are not authorized to view the Club Account Statement."],
    [403, {}, "You are not authorized to view the Club Account Statement."],
    [409, { code: "ACCOUNTING_NOT_ACTIVATED" }, "canonical accounting has not been activated"],
    [409, { code: "STATEMENT_INTEGRITY_FAILURE", message: "Canonical reconciliation failed." }, "Canonical reconciliation failed."],
    [500, { message: "Statement service unavailable." }, "Statement service unavailable."]
  ])("fails closed for backend state %s", async (status, body, expected) => {
    auth.authenticatedFetch.mockImplementation(() => response(body, status));
    render(<ClubAccountStatementBackend role="treasurer" />);
    expect(await screen.findByText(new RegExp(expected, "i"))).toBeInTheDocument();
    expect(screen.queryByText("Cash on hand")).not.toBeInTheDocument();
  });

  it("fails closed when the statement request cannot reach the backend", async () => {
    auth.authenticatedFetch.mockRejectedValue(new TypeError("Network unavailable"));
    render(<ClubAccountStatementBackend role="treasurer" />);
    expect(await screen.findByText("Network unavailable")).toBeInTheDocument();
    expect(screen.queryByText("Cash on hand")).not.toBeInTheDocument();
  });

  it("blocks a normal member before making a statement request", async () => {
    render(<ClubAccountStatementBackend role="member" />);
    expect(await screen.findByText("You are not authorized to view the Club Account Statement.")).toBeInTheDocument();
    expect(auth.authenticatedFetch).not.toHaveBeenCalled();
    expect(screen.queryByLabelText("Statement filters")).not.toBeInTheDocument();
  });

  it("formats integer paise without floating-point reconstruction", () => {
    expect(formatInrPaise(123456789)).toBe("₹12,34,567.89");
    expect(formatInrPaise(-505)).toBe("−₹5.05");
    expect(formatInrPaise(0)).toBe("₹0.00");
  });
});
