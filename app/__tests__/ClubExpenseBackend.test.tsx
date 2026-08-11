import React from "react";
import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const auth = vi.hoisted(() => ({ session: { membership: { club: { id: 7 } } } as { membership: { club: { id: number } } } | null, authenticatedFetch: vi.fn() }));
vi.mock("../DevAuthSession", () => ({ useDevAuth: () => auth }));
import ClubExpenseBackend, { rupeesToPaise } from "../ClubExpenseBackend";

const expenseId = "11111111-1111-4111-8111-111111111111";
const bankId = "22222222-2222-4222-8222-222222222222";
const json = (body: unknown, status = 200) => Promise.resolve(new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } }));

describe("Club expense frontend", () => {
  beforeEach(() => { auth.session = { membership: { club: { id: 7 } } }; auth.authenticatedFetch.mockReset(); vi.stubGlobal("crypto", { randomUUID: () => "33333333-3333-4333-8333-333333333333" }); });
  afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

  it("loads eligible tenant accounts, posts integer paise once, and confirms the journal", async () => {
    auth.authenticatedFetch.mockImplementationOnce(() => json({ data: { expense_accounts: [{ id: expenseId, code: "PRINT", name: "Printing" }], payment_accounts: [{ id: bankId, code: "BANK-1", name: "Main Bank" }], funds: ["SERVICE_ACTIVITY_FUND"], currency: "INR" } })).mockImplementationOnce(() => json({ data: { amount_paise: 450000, accounting_date: "2027-08-10", fund: "SERVICE_ACTIVITY_FUND", expense_account: { id: expenseId, code: "PRINT", name: "Printing" }, payment_account: { id: bankId, code: "BANK-1", name: "Main Bank" }, journal: { reference: "CLUB/2027-28/JRN/000002" } } }, 201));
    render(<ClubExpenseBackend role="treasurer" />); await screen.findByText(/Select the expense facts/);
    fireEvent.change(screen.getByLabelText("Accounting date"), { target: { value: "2027-08-10" } }); fireEvent.change(screen.getByLabelText("Expense category"), { target: { value: expenseId } }); fireEvent.change(screen.getByLabelText("Paid from"), { target: { value: bankId } }); fireEvent.change(screen.getByLabelText("Amount (INR)"), { target: { value: "4500.00" } }); fireEvent.change(screen.getByLabelText("Description"), { target: { value: "Printing" } });
    fireEvent.click(screen.getByRole("button", { name: "Record expense" })); await screen.findByText("Expense posted");
    expect(screen.getByText(/₹4,500.00/)).toBeInTheDocument(); expect(screen.getByText(/JRN\/000002/)).toBeInTheDocument();
    const body = JSON.parse(auth.authenticatedFetch.mock.calls[1][1].body); expect(body.amount_paise).toBe(450000); expect(body).not.toHaveProperty("journal_lines");
    expect(auth.authenticatedFetch).toHaveBeenCalledTimes(2);
  });

  it("blocks duplicate submission while pending and shows backend validation", async () => {
    let resolvePost: ((response: Response) => void) | undefined;
    auth.authenticatedFetch.mockImplementationOnce(() => json({ data: { expense_accounts: [{ id: expenseId, code: "PRINT", name: "Printing" }], payment_accounts: [{ id: bankId, code: "BANK-1", name: "Main Bank" }], funds: ["GENERAL_CLUB_FUND"], currency: "INR" } })).mockImplementationOnce(() => new Promise((resolve) => { resolvePost = resolve; }));
    render(<ClubExpenseBackend role="president" />); await screen.findByText(/Select the expense facts/);
    for (const [label, value] of [["Accounting date", "2027-08-10"], ["Expense category", expenseId], ["Paid from", bankId], ["Amount (INR)", "1.25"], ["Description", "Test"]]) fireEvent.change(screen.getByLabelText(label), { target: { value } });
    const button = screen.getByRole("button", { name: "Record expense" }); fireEvent.click(button); fireEvent.click(button); expect(auth.authenticatedFetch).toHaveBeenCalledTimes(2);
    resolvePost?.(new Response(JSON.stringify({ message: "The selected accounting period is closed." }), { status: 422 }));
    expect(await screen.findByText("The selected accounting period is closed.")).toBeInTheDocument();
  });

  it("reuses the same idempotency identity when an unchanged request is retried", async () => {
    auth.authenticatedFetch.mockImplementationOnce(() => json({ data: { expense_accounts: [{ id: expenseId, code: "PRINT", name: "Printing" }], payment_accounts: [{ id: bankId, code: "BANK-1", name: "Main Bank" }], funds: ["GENERAL_CLUB_FUND"], currency: "INR" } })).mockImplementationOnce(() => json({ message: "Network result unavailable" }, 503)).mockImplementationOnce(() => json({ data: { amount_paise: 100, accounting_date: "2027-08-10", fund: "GENERAL_CLUB_FUND", expense_account: { id: expenseId, code: "PRINT", name: "Printing" }, payment_account: { id: bankId, code: "BANK-1", name: "Main Bank" }, journal: { reference: "JRN-1" } } }, 201));
    render(<ClubExpenseBackend role="secretary" />); await screen.findByText(/Select the expense facts/);
    for (const [label, value] of [["Accounting date", "2027-08-10"], ["Expense category", expenseId], ["Paid from", bankId], ["Amount (INR)", "1.00"], ["Description", "Test"]]) fireEvent.change(screen.getByLabelText(label), { target: { value } });
    fireEvent.click(screen.getByRole("button", { name: "Record expense" })); await screen.findByText("Network result unavailable");
    fireEvent.click(screen.getByRole("button", { name: "Record expense" })); await screen.findByText("Expense posted");
    expect(JSON.parse(auth.authenticatedFetch.mock.calls[1][1].body).idempotency_key).toBe(JSON.parse(auth.authenticatedFetch.mock.calls[2][1].body).idempotency_key);
  });

  it("blocks normal members before any request", async () => { render(<ClubExpenseBackend role="member" />); expect(await screen.findByText("You are not authorized to record club expenses.")).toBeInTheDocument(); expect(auth.authenticatedFetch).not.toHaveBeenCalled(); });
  it("converts decimal strings without floating point arithmetic", () => { expect(rupeesToPaise("4500.05")).toBe(450005); expect(rupeesToPaise("0")).toBeNull(); expect(rupeesToPaise("1.234")).toBeNull(); });
});
