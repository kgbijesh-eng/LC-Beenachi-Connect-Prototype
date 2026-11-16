"use client";

import { useEffect, useState } from "react";
import { hasEffectivePermission, useDevAuth } from "./DevAuthSession";
import { formatInrPaise } from "./ClubAccountStatementBackend";
import { coveredMonth, financeSnapshot, type StatementRange } from "./treasurerStatement";
import styles from "./DashboardPreview.module.css";

type State = { kind: "ready"; data: ReturnType<typeof financeSnapshot> } | { kind: "inactive" | "unavailable" };

const indiaDate = new Intl.DateTimeFormat("en-CA", { timeZone: "Asia/Kolkata", year: "numeric", month: "2-digit", day: "2-digit" });
const monthLabel = new Intl.DateTimeFormat("en-IN", { timeZone: "UTC", month: "long", year: "numeric" });

export default function TreasurerAccounting({ openStatement }: { openStatement: (range: StatementRange) => void }) {
  const { session, authenticatedFetch } = useDevAuth();
  const allowed = session?.role === "treasurer" && hasEffectivePermission(session, "finance.club.statement.view");
  const clubId = session?.membership.club.id;
  const today = indiaDate.format(new Date());
  const [month, setMonth] = useState(today.slice(0, 7));
  const range = coveredMonth(month, today);
  const query = range && new URLSearchParams({ lions_year: range.lionsYear, from: range.from, to: range.to, dashboard: "1" }).toString();
  const [response, setResponse] = useState<{ key: string; fetcher: typeof authenticatedFetch; state: State } | null>(null);
  const key = allowed && query && clubId ? clubId + ":" + query : null;

  useEffect(() => {
    if (!key || !query || !clubId) return;
    const controller = new AbortController();
    const store = (state: State) => {
      if (!controller.signal.aborted) setResponse({ key, fetcher: authenticatedFetch, state });
    };
    void Promise.resolve().then(async () => {
      if (controller.signal.aborted) return;
      const result = await authenticatedFetch("/clubs/" + clubId + "/finance/account-statement?" + query, { signal: controller.signal });
      const body = await result.json();
      if (result.status === 409 && body?.code === "ACCOUNTING_NOT_ACTIVATED") {
        store({ kind: "inactive" });
        return;
      }
      if (!result.ok) throw new Error("Statement unavailable");
      const params = new URLSearchParams(query);
      const requested = {
        lionsYear: params.get("lions_year")!,
        from: params.get("from")!,
        to: params.get("to")!
      };
      store({ kind: "ready", data: financeSnapshot(body, clubId, requested) });
    }).catch(() => store({ kind: "unavailable" }));
    return () => controller.abort();
  }, [key, query, clubId, authenticatedFetch]);

  if (!allowed) return null;
  const state = response?.key === key && response.fetcher === authenticatedFetch ? response.state : null;
  const ready = state?.kind === "ready" ? state.data : null;
  const status = !range ? "Select a month through today." : state?.kind === "inactive" ? "Accounting not activated"
    : state?.kind === "unavailable" ? "Unable to verify" : "Loading accounting statement…";

  return <section className={styles.accounting} aria-label="Posted accounting overview">
    <div className={styles.accountingPeriod}>
      <label>Month <input type="month" value={month} max={today.slice(0, 7)} onChange={event => {
        if (/^\d{4}-\d{2}$/.test(event.target.value)) setMonth(event.target.value);
      }} /></label>
      {range && <span>Lions Year {range.lionsYear}</span>}
      {ready && <button type="button" className={styles.textButton} onClick={() => openStatement(ready.range)}>Open statement for this period ›</button>}
    </div>
    {ready ? <p className={styles.coverage}>Accounting coverage: {ready.range.from} to {ready.range.to}.
      {ready.range.from !== range?.from && " Partial month. Opening balances begin at accounting activation."}
      {ready.range.to === today && " Figures through today."}
    </p> : <p className={styles.coverage} role="status">{status}</p>}
    <div className={styles.treasurerActivity}>
      <section className={styles.panel}>
        <h3>Cash &amp; Bank</h3>
        {ready ? <>
          <p>Ledger balances as of {ready.range.to}</p>
          <dl className={styles.accountingFigures}>
            <div><dt>Cash ledger balance</dt><dd>{formatInrPaise(ready.cash.closing)}</dd><small>Opening {formatInrPaise(ready.cash.opening)}</small></div>
            <div><dt>Bank ledger balance</dt><dd>{formatInrPaise(ready.bank.closing)}</dd><small>Opening {formatInrPaise(ready.bank.opening)}</small></div>
          </dl>
        </> : <p>{status}</p>}
      </section>
      <section className={styles.panel}>
        <h3>This Month</h3>
        {ready ? <>
          <p>{monthLabel.format(new Date(month + "-01"))} · Posted journals</p>
          <dl className={styles.accountingFigures}>
            <div><dt>Money in</dt><dd>{formatInrPaise(ready.incoming)}</dd></div>
            <div><dt>Money out</dt><dd>{formatInrPaise(ready.outgoing)}</dd></div>
            <div><dt>Net cash/bank movement</dt><dd>{formatInrPaise(ready.incoming - ready.outgoing)}</dd></div>
          </dl>
          <p>Excludes transfers between club accounts. Reversals count on their journal date.</p>
        </> : <p>{status}</p>}
      </section>
    </div>
    {ready && <section className={styles.panel}>
      <h3>Cash &amp; bank by fund</h3>
      <ul className={styles.activity}>{ready.funds.map(fund => <li key={fund.name}>
        <strong>{fund.name}</strong>
        <span>Closing {formatInrPaise(fund.closing)} · In {formatInrPaise(fund.incoming)} · Out {formatInrPaise(fund.outgoing)}</span>
      </li>)}</ul>
    </section>}
    <section className={styles.panel}>
      <h3>Recent Finance Activity</h3>
      {ready ? ready.activity.length > 0 ? <ul className={styles.activity}>{ready.activity.map(row => <li key={row.id}>
        <span><strong>{row.label}</strong> · <time dateTime={row.date}>{row.date}</time> · {formatInrPaise(row.amount)}</span>
        <button type="button" className={styles.textButton} onClick={() => openStatement({ ...ready.range, journalId: row.id })}>{row.reference} in statement ›</button>
      </li>)}</ul> : <p>No posted payments, receipts or expenses in this period.</p> : <p>{status}</p>}
    </section>
  </section>;
}
