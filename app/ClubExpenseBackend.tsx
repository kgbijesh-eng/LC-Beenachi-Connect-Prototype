"use client";

import { type FormEvent, useEffect, useRef, useState } from "react";
import { useDevAuth, type DevRole } from "./DevAuthSession";
import { formatInrPaise } from "./ClubAccountStatementBackend";

type Account = { id: string; code: string; name: string };
type Options = { expense_accounts: Account[]; payment_accounts: Account[]; funds: string[]; currency: "INR" };
type Result = { amount_paise: number; accounting_date: string; fund: string; expense_account: Account; payment_account: Account; journal: { reference: string } };
export type CorrectedExpenseDraft = { id: string; accounting_date: string; amount_paise: number; fund: string; expense_account: Account; payment_account: Account; payee: string | null; description: string; external_reference: string | null };

export function rupeesToPaise(value: string): number | null {
  if (!/^\d+(?:\.\d{1,2})?$/.test(value)) return null;
  const [rupees, fraction = ""] = value.split(".");
  const paise = BigInt(rupees) * BigInt(100) + BigInt(fraction.padEnd(2, "0"));
  return paise > BigInt(Number.MAX_SAFE_INTEGER) || paise < BigInt(1) ? null : Number(paise);
}

export default function ClubExpenseBackend({ role, correctedFrom, onRecorded }: { role: DevRole; correctedFrom?: CorrectedExpenseDraft | null; onRecorded?: () => void }) {
  const { session, authenticatedFetch } = useDevAuth();
  const allowed = role === "president" || role === "secretary" || role === "treasurer";
  const [options, setOptions] = useState<Options | null>(null);
  const [message, setMessage] = useState(allowed ? "Loading expense accounts…" : "You are not authorized to record club expenses.");
  const [submitting, setSubmitting] = useState(false);
  const [result, setResult] = useState<Result | null>(null);
  const retryIdentity = useRef<{ payload: string; key: string } | null>(null);

  useEffect(() => {
    let cancelled = false;
    if (!allowed || !session) return;
    void authenticatedFetch(`/clubs/${session.membership.club.id}/finance/expenses/options`).then(async (response) => {
      const body = await response.json().catch(() => null);
      if (!response.ok) throw new Error(body?.message || "Expense accounts could not be loaded.");
      if (!cancelled) { setOptions(body.data); setMessage("Select the expense facts. Accounting entries are created by the server."); }
    }).catch((error) => { if (!cancelled) setMessage(error instanceof Error ? error.message : "Expense accounts could not be loaded."); });
    return () => { cancelled = true; };
  }, [allowed, authenticatedFetch, session]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); if (!session || !options || submitting) return;
    const data = new FormData(event.currentTarget); const amount = rupeesToPaise(String(data.get("amount")));
    if (amount === null) { setMessage("Enter a positive INR amount with no more than two decimal places."); return; }
    setSubmitting(true); setResult(null);
    try {
      const canonical = { accounting_date: data.get("accounting_date"), expense_account: data.get("expense_account"), payment_account: data.get("payment_account"), fund: data.get("fund"), amount_paise: amount, payee: data.get("payee") || null, description: data.get("description"), external_reference: data.get("external_reference") || null, corrected_from_expense: correctedFrom?.id || null };
      const fingerprint = JSON.stringify(canonical);
      if (retryIdentity.current?.payload !== fingerprint) retryIdentity.current = { payload: fingerprint, key: crypto.randomUUID() };
      const response = await authenticatedFetch(`/clubs/${session.membership.club.id}/finance/expenses`, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ ...canonical, idempotency_key: retryIdentity.current.key }) });
      const body = await response.json().catch(() => null); if (!response.ok) throw new Error(body?.message || "The expense could not be recorded.");
      setResult(body.data); setMessage("Expense recorded and posted to the canonical ledger."); retryIdentity.current = null; onRecorded?.();
    } catch (error) { setMessage(error instanceof Error ? error.message : "The expense could not be recorded."); }
    finally { setSubmitting(false); }
  }

  if (!allowed) return <section className="compactPanel"><h2>Record Expense</h2><div className="notice">{message}</div></section>;
  return <section className="stack expenseBackend"><header className="compactPanel"><h2>{correctedFrom ? "Record Corrected Expense" : "Record Expense"}</h2><p>{correctedFrom ? "Review every value. This creates a new expense and journal; it does not change the reversed original." : "Record an expense paid immediately from a club cash or bank account."}</p></header><div className="notice" role="status">{message}</div>
    {options && <form className="statementFilters expenseForm" aria-label="Record club expense" onSubmit={submit}>
      <label>Accounting date<input name="accounting_date" type="date" required defaultValue={correctedFrom?.accounting_date} /></label>
      <label>Expense category<select name="expense_account" required defaultValue={correctedFrom?.expense_account.id ?? ""}><option value="" disabled>Select expense account</option>{options.expense_accounts.map((a) => <option key={a.id} value={a.id}>{a.name} ({a.code})</option>)}</select></label>
      <label>Paid from<select name="payment_account" required defaultValue={correctedFrom?.payment_account.id ?? ""}><option value="" disabled>Select cash or bank account</option>{options.payment_accounts.map((a) => <option key={a.id} value={a.id}>{a.name} ({a.code})</option>)}</select></label>
      <label>Fund<select name="fund" required defaultValue={correctedFrom?.fund}>{options.funds.map((fund) => <option key={fund}>{fund.replaceAll("_", " ")}</option>)}</select></label>
      <label>Amount (INR)<input name="amount" inputMode="decimal" required placeholder="4500.00" defaultValue={correctedFrom ? (correctedFrom.amount_paise / 100).toFixed(2) : undefined} /></label>
      <label>Payee<input name="payee" maxLength={160} defaultValue={correctedFrom?.payee ?? ""} /></label><label>Description<input name="description" required maxLength={500} defaultValue={correctedFrom?.description} /></label><label>External reference<input name="external_reference" maxLength={120} defaultValue={correctedFrom?.external_reference ?? ""} /></label>
      <button type="submit" disabled={submitting}>{submitting ? "Recording…" : "Record expense"}</button>
    </form>}
    {result && <section className="compactPanel" aria-label="Expense confirmation"><h3>Expense posted</h3><p><strong>{formatInrPaise(result.amount_paise)}</strong> · {result.accounting_date}</p><p>{result.expense_account.name} paid from {result.payment_account.name}</p><p>{result.fund.replaceAll("_", " ")} · Journal {result.journal.reference}</p></section>}
  </section>;
}
