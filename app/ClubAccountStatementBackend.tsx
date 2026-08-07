"use client";

import { type FormEvent, useEffect, useMemo, useState } from "react";
import { useDevAuth, type DevRole } from "./DevAuthSession";

type Amounts = {
  opening_balance_paise: number;
  period_debit_paise: number;
  period_credit_paise: number;
  net_movement_paise: number;
  closing_balance_paise: number;
};
type TrialBalance = {
  opening_debits_paise: number;
  opening_credits_paise: number;
  period_debits_paise: number;
  period_credits_paise: number;
  closing_debits_paise: number;
  closing_credits_paise: number;
};
type StatementAccount = Amounts & {
  account_id: string;
  account_code: string;
  account_name: string;
  normal_balance: string;
  statement_classification: string;
  fund_code: string;
  journal_ids: string[];
};
type StatementFund = {
  fund_code: string;
  display_name: string;
  classifications: { classification: string; accounts: StatementAccount[]; totals: Amounts }[];
  totals: Amounts;
  trial_balance: TrialBalance;
};
type StatementJournal = {
  journal_id: string;
  reference: string;
  accounting_date: string;
  journal_type: string;
  description: string;
  debit_total_paise: number;
  credit_total_paise: number;
  reversal_of_journal_id: string | null;
  reversed_by_journal_id: string | null;
  source_type: string | null;
  source_id: string | null;
  contribution: "opening_balance" | "period_movement";
};
type Statement = {
  contract_version: string;
  club_id: number;
  currency: "INR";
  amount_unit: "paise";
  lions_year: { id: number; label: string; starts_on: string; ends_on: string };
  range: { period_id: number | null; from: string; to: string; is_closed: boolean };
  source: { cutover_date: string; journal_count: number; journal_line_count: number };
  funds: StatementFund[];
  overall_trial_balance: TrialBalance;
  traceability: { journals: StatementJournal[] };
  integrity: Record<string, boolean>;
};
type LoadState = "loading" | "ready" | "empty" | "forbidden" | "not-activated" | "integrity" | "validation" | "error";
type FilterMode = "lions-year" | "period" | "custom";

function currentLionsYear(): string {
  const today = new Date();
  const start = today.getMonth() >= 6 ? today.getFullYear() : today.getFullYear() - 1;
  return `${start}-${String((start + 1) % 100).padStart(2, "0")}`;
}

function indianDigits(value: string): string {
  if (value.length <= 3) return value;
  const tail = value.slice(-3);
  const head = value.slice(0, -3);
  const grouped = head.replace(/\B(?=(\d{2})+(?!\d))/g, ",");
  return `${grouped},${tail}`;
}

export function formatInrPaise(value: number): string {
  if (!Number.isSafeInteger(value)) return "Unavailable";
  const paise = BigInt(value);
  const zero = BigInt(0);
  const hundred = BigInt(100);
  const negative = paise < zero;
  const absolute = negative ? -paise : paise;
  const rupees = absolute / hundred;
  const fraction = String(absolute % hundred).padStart(2, "0");
  return `${negative ? "−" : ""}₹${indianDigits(String(rupees))}.${fraction}`;
}

function label(value: string): string {
  return value.toLowerCase().split("_").map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join(" ");
}

function validStatement(value: unknown, clubId: number): value is Statement {
  const row = value as Partial<Statement> | null;
  return Boolean(row && row.contract_version === "CLUB-ACCOUNT-STATEMENT-2026-01" && row.club_id === clubId
    && row.currency === "INR" && row.amount_unit === "paise" && Array.isArray(row.funds)
    && Array.isArray(row.traceability?.journals) && row.integrity
    && Object.values(row.integrity).every((status) => status === true));
}

export default function ClubAccountStatementBackend({ role }: { role: DevRole }) {
  const { session, authenticatedFetch } = useDevAuth();
  const [statement, setStatement] = useState<Statement | null>(null);
  const [state, setState] = useState<LoadState>("loading");
  const [message, setMessage] = useState("Loading canonical Club Account Statement…");
  const [mode, setMode] = useState<FilterMode>("lions-year");
  const [lionsYear, setLionsYear] = useState(currentLionsYear);
  const [periodId, setPeriodId] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [requestQuery, setRequestQuery] = useState(() => `lions_year=${encodeURIComponent(currentLionsYear())}`);

  const allowedRole = role === "president" || role === "secretary" || role === "treasurer";

  useEffect(() => {
    let cancelled = false;
    void (async () => {
      await Promise.resolve();
      if (cancelled) return;
      setStatement(null);
      if (!allowedRole) {
        setState("forbidden");
        setMessage("You are not authorized to view the Club Account Statement.");
        return;
      }
      if (!session) {
        setState("error");
        setMessage("A verified authenticated club session is required.");
        return;
      }
      setState("loading");
      setMessage("Loading canonical Club Account Statement…");
      try {
        const response = await authenticatedFetch(`/clubs/${session.membership.club.id}/finance/account-statement?${requestQuery}`);
        const body = await response.json().catch(() => null);
        if (!response.ok) {
          if (response.status === 401 || response.status === 403) throw Object.assign(new Error("You are not authorized to view the Club Account Statement."), { kind: "forbidden" });
          if (response.status === 409 && body?.code === "ACCOUNTING_NOT_ACTIVATED") throw Object.assign(new Error("The Club Account Statement is unavailable because canonical accounting has not been activated for this club."), { kind: "not-activated" });
          if (response.status === 409 && body?.code === "STATEMENT_INTEGRITY_FAILURE") throw Object.assign(new Error(body?.message || "The canonical statement failed its accounting integrity checks. No figures are shown."), { kind: "integrity" });
          if (response.status === 422) throw Object.assign(new Error(Object.values(body?.errors ?? {}).flat()[0] || body?.message || "The selected statement filters are invalid."), { kind: "validation" });
          throw new Error(body?.message || "The Club Account Statement could not be loaded.");
        }
        if (!validStatement(body?.data, session.membership.club.id)) throw Object.assign(new Error("The canonical statement response failed integrity validation. No figures are shown."), { kind: "integrity" });
        if (cancelled) return;
        const exact = body.data as Statement;
        setStatement(exact);
        const empty = exact.source.journal_count === 0 && exact.funds.every((fund) => fund.classifications.every((classification) => classification.accounts.length === 0));
        setState(empty ? "empty" : "ready");
        setMessage(empty ? "No statement activity exists for the selected period." : "Canonical statement loaded from the accounting ledger.");
      } catch (error) {
        if (cancelled) return;
        const kind = (error as { kind?: LoadState }).kind;
        setState(kind ?? "error");
        setMessage(error instanceof Error ? error.message : "The Club Account Statement could not be loaded.");
      }
    })();
    return () => { cancelled = true; };
  }, [allowedRole, authenticatedFetch, requestQuery, session]);

  const integrityLabels = useMemo(() => statement ? Object.entries(statement.integrity) : [], [statement]);

  function applyFilters(event: FormEvent) {
    event.preventDefault();
    const query = new URLSearchParams({ lions_year: lionsYear });
    if (mode === "period" && periodId) query.set("period_id", periodId);
    if (mode === "custom" && from && to) { query.set("from", from); query.set("to", to); }
    setRequestQuery(query.toString());
  }

  return <section className="stack statementBackend" data-testid="club-account-statement">
    <header className="compactPanel">
      <h2>Club Account Statement</h2>
      <p>Read-only canonical accounting statement · INR</p>
    </header>

    {allowedRole && <form className="statementFilters" onSubmit={applyFilters} aria-label="Statement filters">
      <label>Lions Year<input aria-label="Lions Year" required pattern="\d{4}-\d{2}" value={lionsYear} onChange={(event) => setLionsYear(event.target.value)} /></label>
      <label>Range type<select aria-label="Range type" value={mode} onChange={(event) => setMode(event.target.value as FilterMode)}><option value="lions-year">Entire Lions Year</option><option value="period">Accounting period</option><option value="custom">Custom dates</option></select></label>
      {mode === "period" && <label>Accounting period ID<input aria-label="Accounting period ID" type="number" min="1" required value={periodId} onChange={(event) => setPeriodId(event.target.value)} /></label>}
      {mode === "custom" && <><label>From<input aria-label="From" type="date" required value={from} onChange={(event) => setFrom(event.target.value)} /></label><label>To<input aria-label="To" type="date" required min={from || undefined} value={to} onChange={(event) => setTo(event.target.value)} /></label></>}
      <button type="submit">Apply filters</button>
    </form>}

    <div className={`notice statementState statementState-${state}`} role={state === "loading" ? "status" : "alert"} aria-live="polite">{message}</div>

    {statement && (state === "ready" || state === "empty") && <>
      <section className="statementMeta" aria-label="Statement scope">
        <span><strong>Lions Year</strong> {statement.lions_year.label}</span>
        <span><strong>Range</strong> {statement.range.from} to {statement.range.to}</span>
        <span><strong>Period</strong> {statement.range.is_closed ? "Closed" : "Open"}</span>
        <span><strong>Journals</strong> {statement.source.journal_count}</span>
      </section>

      <section aria-labelledby="funds-heading"><h3 id="funds-heading">Fund statements</h3>
        <div className="statementFunds">{statement.funds.map((fund) => <article className="statementFund" aria-label={fund.display_name} key={fund.fund_code}>
          <header><h4>{fund.display_name}</h4><code>{fund.fund_code}</code></header>
          <div className="statementTotals"><span>Opening <strong>{formatInrPaise(fund.totals.opening_balance_paise)}</strong></span><span>Movement <strong>{formatInrPaise(fund.totals.net_movement_paise)}</strong></span><span>Closing <strong>{formatInrPaise(fund.totals.closing_balance_paise)}</strong></span></div>
          {fund.classifications.filter((classification) => classification.accounts.length > 0).map((classification) => <section className="statementClassification" key={classification.classification}>
            <h5>{label(classification.classification)}</h5>
            <div className="statementAccounts">{classification.accounts.map((account) => <article key={`${fund.fund_code}-${account.account_id}`}>
              <div><strong>{account.account_name}</strong><span>{account.account_code}</span></div>
              <dl><div><dt>Opening</dt><dd>{formatInrPaise(account.opening_balance_paise)}</dd></div><div><dt>Movement</dt><dd>{formatInrPaise(account.net_movement_paise)}</dd></div><div><dt>Closing</dt><dd>{formatInrPaise(account.closing_balance_paise)}</dd></div></dl>
              {account.journal_ids.length > 0 && <small>Journal references: {account.journal_ids.join(", ")}</small>}
            </article>)}</div>
          </section>)}
        </article>)}</div>
      </section>

      <section className="compactPanel" aria-labelledby="reconciliation-heading"><h3 id="reconciliation-heading">Reconciliation</h3>
        <div className="statementReconciliation">{integrityLabels.map(([name, status]) => <span key={name}><strong>{status ? "Reconciled" : "Failed"}</strong> · {label(name)}</span>)}</div>
        <p>Overall opening debits {formatInrPaise(statement.overall_trial_balance.opening_debits_paise)} and credits {formatInrPaise(statement.overall_trial_balance.opening_credits_paise)}. Period debits {formatInrPaise(statement.overall_trial_balance.period_debits_paise)} and credits {formatInrPaise(statement.overall_trial_balance.period_credits_paise)}.</p>
      </section>

      <section aria-labelledby="traceability-heading"><h3 id="traceability-heading">Journal traceability</h3>
        <div className="statementJournals">{statement.traceability.journals.map((journal) => <details key={journal.journal_id}>
          <summary><strong>{journal.reference}</strong> · {journal.accounting_date} · {journal.description}</summary>
          <dl><div><dt>Journal ID</dt><dd>{journal.journal_id}</dd></div><div><dt>Classification</dt><dd>{label(journal.journal_type)}</dd></div><div><dt>Contribution</dt><dd>{label(journal.contribution)}</dd></div><div><dt>Debits</dt><dd>{formatInrPaise(journal.debit_total_paise)}</dd></div><div><dt>Credits</dt><dd>{formatInrPaise(journal.credit_total_paise)}</dd></div></dl>
          {journal.source_type && <p>Source: {journal.source_type}{journal.source_id ? ` · ${journal.source_id}` : ""}</p>}
          {journal.reversal_of_journal_id && <p>Reversal of journal {journal.reversal_of_journal_id}</p>}
          {journal.reversed_by_journal_id && <p>Reversed by journal {journal.reversed_by_journal_id}</p>}
        </details>)}</div>
      </section>
    </>}
  </section>;
}
