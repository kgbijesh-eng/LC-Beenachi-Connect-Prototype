import React from "react";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

const auth = vi.hoisted(() => ({ session: { membership: { club: { id: 7 } } } as { membership: { club: { id: number } } } | null, authenticatedFetch: vi.fn() }));
vi.mock("../DevAuthSession", () => ({ useDevAuth: () => auth }));
vi.mock("../ClubExpenseBackend", () => ({ default: ({ correctedFrom }: { correctedFrom: { id: string } }) => <div>Corrected form for {correctedFrom.id}</div> }));
import ClubExpenseHistoryBackend from "../ClubExpenseHistoryBackend";

const expenseId = "11111111-1111-4111-8111-111111111111";
const account = { id: "22222222-2222-4222-8222-222222222222", code: "PRINT", name: "Printing" };
const bank = { id: "33333333-3333-4333-8333-333333333333", code: "BANK", name: "Main Bank" };
const row = (reversed = false) => ({ id: expenseId, accounting_date: "2027-08-10", amount_paise: 450000, currency: "INR", fund: "GENERAL_CLUB_FUND", expense_account: account, payment_account: bank, payee: "Printer", description: "Printing", external_reference: "INV-42", status: reversed ? "reversed" : "posted", journal: { id: "44444444-4444-4444-8444-444444444444", reference: "EXP/JRN/2", lines: [{ id: "line-1", account, direction: "debit", amount_paise: 450000, fund: "GENERAL_CLUB_FUND" }] }, reversal: reversed ? { id: "55555555-5555-4555-8555-555555555555", accounting_date: "2027-08-12", reason: "Incorrect amount", journal: { id: "66666666-6666-4666-8666-666666666666", reference: "EXP/JRN/3", lines: [{ id: "line-2", account: bank, direction: "debit", amount_paise: 450000, fund: "GENERAL_CLUB_FUND" }] } } : null, corrected_expense: null, created_at: "2027-08-10T00:00:00Z" });
const json = (body: unknown, status = 200) => Promise.resolve(new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } }));

describe("Expense history and correction frontend", () => {
  beforeEach(() => { auth.session = { membership: { club: { id: 7 } } }; auth.authenticatedFetch.mockReset(); vi.stubGlobal("crypto", { randomUUID: () => "77777777-7777-4777-8777-777777777777" }); });
  afterEach(() => { cleanup(); vi.unstubAllGlobals(); });

  it("renders history, applies bounded filters, and opens accounting detail", async () => {
    auth.authenticatedFetch.mockImplementationOnce(() => json({ data: [row()], meta: { current_page: 1, last_page: 1, total: 1 } })).mockImplementationOnce(() => json({ data: [row()], meta: { current_page: 1, last_page: 1, total: 1 } })).mockImplementationOnce(() => json({ data: row() }));
    render(<ClubExpenseHistoryBackend role="secretary" />); expect(await screen.findByText("₹4,500.00")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Search"), { target: { value: "Printer" } }); fireEvent.change(screen.getByLabelText("Status"), { target: { value: "posted" } }); fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await waitFor(() => expect(auth.authenticatedFetch.mock.calls[1][0]).toContain("search=Printer")); fireEvent.click(screen.getByRole("button", { name: "View expense" }));
    expect(await screen.findByText("Original journal: EXP/JRN/2")).toBeInTheDocument(); expect(screen.queryByRole("button", { name: "Reverse incorrect expense" })).not.toBeInTheDocument();
  });

  it("confirms a linked reversal, prevents duplicate submit, and shows success provenance", async () => {
    let resolvePost: ((response: Response) => void) | undefined;
    auth.authenticatedFetch.mockImplementationOnce(() => json({ data: [row()], meta: { current_page: 1, last_page: 1, total: 1 } })).mockImplementationOnce(() => json({ data: row() })).mockImplementationOnce(() => new Promise((resolve) => { resolvePost = resolve; }));
    render(<ClubExpenseHistoryBackend role="treasurer" />); await screen.findByText("₹4,500.00"); fireEvent.click(screen.getByRole("button", { name: "View expense" })); await screen.findByText("Original journal: EXP/JRN/2"); fireEvent.click(screen.getByRole("button", { name: "Reverse incorrect expense" }));
    expect(screen.getByText(/original expense and journal will remain/)).toBeInTheDocument(); fireEvent.change(screen.getByLabelText("Reversal date"), { target: { value: "2027-08-12" } }); fireEvent.change(screen.getByLabelText("Reason"), { target: { value: "Incorrect amount" } });
    const submit = screen.getByRole("button", { name: "Post linked reversal" }); fireEvent.click(submit); fireEvent.click(submit); expect(auth.authenticatedFetch).toHaveBeenCalledTimes(3); resolvePost?.(new Response(JSON.stringify({ data: row(true) }), { status: 200 }));
    expect(await screen.findByText("Linked reversal")).toBeInTheDocument(); expect(screen.getByText(/EXP\/JRN\/3/)).toBeInTheDocument(); const body = JSON.parse(auth.authenticatedFetch.mock.calls[2][1].body); expect(body).toEqual({ accounting_date: "2027-08-12", reason: "Incorrect amount", idempotency_key: "77777777-7777-4777-8777-777777777777" }); expect(body).not.toHaveProperty("amount_paise");
  });

  it("offers an explicit corrected-expense handoff after reversal", async () => {
    auth.authenticatedFetch.mockImplementationOnce(() => json({ data: [row(true)], meta: { current_page: 1, last_page: 1, total: 1 } })).mockImplementationOnce(() => json({ data: row(true) })); render(<ClubExpenseHistoryBackend role="president" />); await screen.findByText("₹4,500.00"); fireEvent.click(screen.getByRole("button", { name: "View expense" })); await screen.findByText("Linked reversal"); fireEvent.click(screen.getByRole("button", { name: "Record corrected expense" })); expect(screen.getByText(`Corrected form for ${expenseId}`)).toBeInTheDocument();
  });

  it("blocks normal members without making a request", async () => { render(<ClubExpenseHistoryBackend role="member" />); expect(await screen.findByText("You are not authorized to view club expenses.")).toBeInTheDocument(); expect(auth.authenticatedFetch).not.toHaveBeenCalled(); });
  it("shows backend and network failures without raw internals", async () => { auth.authenticatedFetch.mockImplementationOnce(() => json({ message: "History unavailable" }, 503)); render(<ClubExpenseHistoryBackend role="treasurer" />); expect(await screen.findByText("History unavailable")).toBeInTheDocument(); });
});
