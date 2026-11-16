"use client";

import { useCallback, useEffect, useState } from "react";
import { hasEffectivePermission, useDevAuth } from "./DevAuthSession";
import MemberDashboard from "./MemberDashboard";
import memberStyles from "./MemberDashboard.module.css";
import styles from "./DashboardPreview.module.css";

export type DashboardDestination = "record-payment" | "record-expense" | "statement" | "outstanding" | "finance-report"
  | "digital-card" | "my-dues" | "my-receipts" | "profile" | "events" | "announcements";

type ReadState<T> = { status: "loading" | "unavailable" } | { status: "ready"; data: T };
type ReadSource<T> = { path: string; fetcher: ReturnType<typeof useDevAuth>["authenticatedFetch"]; result: ReadState<T> };
type Dues = { paise: number; members?: number; collected?: number; paymentCount?: number };
type Activity = { id: number; title: string; date: string };
type Payment = { id: number; member_id: number; amount_paise: number; payment_date: string; receipt: { receipt_number: string; status: string } | null };
type Event = { club_id: number; title: string; status: string; starts_at: string | null; start_date: string | null; venue: string | null };

const money = new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR" });
const date = new Intl.DateTimeFormat("en-IN", { day: "numeric", month: "short", year: "numeric", timeZone: "Asia/Kolkata" });
const formatDate = (value: string) => date.format(new Date(value));
const validDate = (value: unknown): value is string => typeof value === "string" && Number.isFinite(Date.parse(value));
const nonnegativeInteger = (value: unknown): value is number => typeof value === "number" && Number.isSafeInteger(value) && value >= 0;

function paise(value: unknown): number {
  if (typeof value !== "string" || !/^\d+\.\d{2}$/.test(value)) throw new Error("Invalid amount");
  const result = Number(value.replace(".", ""));
  if (!nonnegativeInteger(result)) throw new Error("Invalid amount");
  return result;
}

function useSource<T>(path: string | null, read: (body: unknown) => T): ReadState<T> {
  const { authenticatedFetch } = useDevAuth();
  const [source, setSource] = useState<ReadSource<T> | null>(null);

  useEffect(() => {
    if (!path) return;
    const controller = new AbortController();
    // Development effect replay can cancel this read before it reaches the network.
    void Promise.resolve().then(async () => {
      if (controller.signal.aborted) return;
      const response = await authenticatedFetch(path, { signal: controller.signal });
      if (!response.ok) throw new Error("Source unavailable");
      const data = read(await response.json());
      if (!controller.signal.aborted) setSource({ path, fetcher: authenticatedFetch, result: { status: "ready", data } });
    }).catch(() => {
      if (!controller.signal.aborted) setSource({ path, fetcher: authenticatedFetch, result: { status: "unavailable" } });
    });
    return () => controller.abort();
  }, [path, read, authenticatedFetch]);

  if (!path) return { status: "unavailable" };
  return source?.path === path && source.fetcher === authenticatedFetch ? source.result : { status: "loading" };
}

function Amount({ source, value }: { source: ReadState<Dues>; value: (dues: Dues) => number }) {
  return <strong>{source.status === "ready" ? money.format(value(source.data) / 100)
    : source.status === "loading" ? "Loading…" : "Unavailable"}</strong>;
}

export default function DashboardPreview({ navigate }: { navigate: (destination: DashboardDestination) => void }) {
  const { session } = useDevAuth();
  if (!session) return null;
  return <DashboardContent navigate={navigate} />;
}

function DashboardContent({ navigate }: { navigate: (destination: DashboardDestination) => void }) {
  const { session } = useDevAuth();
  const clubId = session?.membership.club.id;
  const memberId = session?.membership.member?.id;
  const officer = session?.role !== "member";
  const can = (slug: string) => session?.membership.permissions?.some(permission => permission.slug === slug) ?? false;
  const canClubFinance = hasEffectivePermission(session, "finance.club.view");
  const canClubReport = canClubFinance && can("club.view");
  const canOutstanding = canClubFinance && can("members.view");
  const canRecord = canOutstanding && hasEffectivePermission(session, "finance.manage");
  const canStatement = hasEffectivePermission(session, "finance.club.statement.view");
  const canPersonalFinance = !officer && Boolean(memberId) && can("finance.self.view");
  const canEvents = can("events.view");
  const canAnnouncements = can("announcements.view");
  const eventManagement = hasEffectivePermission(session, "events.manage");
  const announcementManagement = hasEffectivePermission(session, "announcements.management.view");
  const root = `/clubs/${clubId}`;

  // The posted-ledger dues report and the allocated-obligations screen are separate sources.
  // Link the priority to the report that supplied it.
  const readDues = useMemoizedDues(clubId, memberId, officer, session?.role === "treasurer");
  const readPayments = useMemoizedPayments(memberId);
  const readEvents = useMemoizedEvents(clubId);
  const dues = useSource(officer && canClubReport ? root + "/finance/reports" : canPersonalFinance ? root + "/finance/me/summary" : null, readDues);
  const payments = useSource(canPersonalFinance ? root + "/finance/me/payments?status=posted&per_page=5" : null, readPayments);
  const upcoming = useSource(canEvents ? root + "/events?scope=upcoming&per_page=10" + (eventManagement ? "&status=published" : "") : null, readEvents);
  const announcements = useSource(canAnnouncements ? root + "/announcements?scope=current&per_page=3&sort=published_at&direction=desc"
    + (announcementManagement ? "&status=published&audience=club-members&effective_state=current" : "") : null, readAnnouncements);

  const lastPayment = payments.status === "ready" ? payments.data[0] : undefined;
  const recentReceipt = payments.status === "ready" ? payments.data.find(row => row.receipt?.status === "issued") : undefined;
  const nextEvent = upcoming.status === "ready" ? upcoming.data[0] : undefined;
  const hasPriority = dues.status === "ready" && dues.data.paise > 0;

  const events = <section className={officer ? `${styles.panel} ${styles.eventPanel}` : memberStyles.card}>
        <h3>Coming up</h3>
        {nextEvent ? <div className={styles.event}>
          <time dateTime={nextEvent.start_date ?? nextEvent.starts_at ?? undefined}>{formatDate(nextEvent.start_date ?? nextEvent.starts_at!)}</time>
          <h4>{nextEvent.title}</h4>
          {nextEvent.venue && <p>{nextEvent.venue}</p>}
        </div> : <p>{upcoming.status === "loading" ? "Loading club calendar…"
          : upcoming.status === "ready" ? "No published events in the upcoming list." : "Club calendar unavailable."}</p>}
        {canEvents && <button className={styles.textButton} onClick={() => navigate("events")}>Open Events &amp; Meetings ›</button>}
      </section>;
  const notices = <section className={officer ? `${styles.panel} ${styles.activityPanel}` : memberStyles.card}>
        <h3>Club announcements</h3>
        {announcements.status === "ready" && announcements.data.length > 0 ? <ul className={styles.activity}>
          {announcements.data.map(item => <li key={item.id}><strong>{item.title}</strong><time dateTime={item.date}>{formatDate(item.date)}</time></li>)}
        </ul> : <p>{announcements.status === "loading" ? "Loading announcements…"
          : announcements.status === "ready" ? "No current announcements." : "Announcements unavailable."}</p>}
        {canAnnouncements && <button className={styles.textButton} onClick={() => navigate("announcements")}>Open announcements ›</button>}
      </section>;

  if (!officer && session) return <MemberDashboard
    name={session.user.name}
    club={session.membership.club.name ?? session.organisation.name}
    balance={dues.status === "ready" ? { status: "ready", amount: money.format(dues.data.paise / 100), outstanding: hasPriority } : dues}
    payment={lastPayment ? money.format(lastPayment.amount_paise / 100) + " · " + formatDate(lastPayment.payment_date)
      : payments.status === "ready" ? "No posted payments" : payments.status === "loading" ? "Loading…" : "Unavailable"}
    receipt={recentReceipt?.receipt?.receipt_number ?? (payments.status === "ready" ? "None in recent payments"
      : payments.status === "loading" ? "Loading…" : "Unavailable")}
    events={events}
    announcements={notices}
    onCard={memberId ? () => navigate("digital-card") : undefined}
    onDues={canPersonalFinance ? () => navigate("my-dues") : undefined}
    onPayments={canPersonalFinance ? () => navigate("my-receipts") : undefined}
  />;

  if (session?.role === "treasurer") {
    const canExpense = hasEffectivePermission(session, "finance.club.expenses.create");
    const actions: Array<[DashboardDestination, string, boolean]> = [
      ["record-payment", "Record Payment", canRecord],
      ["record-expense", "Record Expense", canExpense],
      ["statement", "Open Statement", canStatement]
    ];
    const members = dues.status === "ready" ? dues.data.members : undefined;
    const paymentCount = dues.status === "ready" ? dues.data.paymentCount : undefined;

    return <section className={styles.preview} aria-label="Treasurer dashboard">
      <header className={styles.treasurerHeader}><h2>Treasurer’s Dashboard</h2></header>
      {canClubReport && <div className={styles.treasurerFigures}>
        <section className={`${styles.metric} ${styles.treasurerDues}`} aria-labelledby="treasurer-dues">
          <h3 id="treasurer-dues">Member dues outstanding</h3>
          <p>All posted periods · Member ledger</p>
          <div role="status">
            <Amount source={dues} value={value => value.paise} />
            {dues.status === "ready" && <p>{hasPriority
              ? members === 1 ? "1 member has outstanding dues" : `${members} members have outstanding dues`
              : "No outstanding dues in the posted member ledger"}</p>}
            {dues.status === "unavailable" && <p>Unable to verify member dues.</p>}
          </div>
          <button type="button" className={hasPriority ? styles.primary : styles.textButton} onClick={() => navigate("finance-report")}>
            {hasPriority ? "Review member-wise dues for follow-up" : "Open Member-wise Dues Summary"} ›
          </button>
        </section>
        <section className={styles.metric} aria-labelledby="treasurer-payments">
          <h3 id="treasurer-payments">Posted payments</h3>
          <p>All posted periods</p>
          <div role="status">
            <strong>{dues.status === "loading" ? "Loading…" : dues.status === "ready" && dues.data.collected !== undefined
              ? money.format(dues.data.collected / 100) : "Unavailable"}</strong>
            <p>Posted payment count: {dues.status === "loading" ? "Loading…" : paymentCount ?? "Unavailable"}</p>
          </div>
        </section>
      </div>}
      <section className={styles.panel}>
        <h3>Quick Actions</h3>
        <div className={styles.quick}>
          {actions.filter(([, , allowed]) => allowed).map(([target, label]) =>
            <button type="button" key={target} onClick={() => navigate(target)}><span aria-hidden="true">↗</span>{label}</button>)}
        </div>
        {!actions.some(([, , allowed]) => allowed) && <p>No finance shortcuts are available for this account.</p>}
      </section>
      <div className={styles.treasurerActivity}>{events}{notices}</div>
    </section>;
  }

  const quick: Array<[DashboardDestination, string, boolean]> = [
    ["record-payment", "Record payment", canRecord],
    ["statement", "Club Account Statement", canStatement],
    ["outstanding", "Outstanding dues", canOutstanding]
  ];

  return <section className={styles.preview} aria-label="Dashboard preview">
    <header className={styles.hero}>
      <p className={styles.kicker}>{officer ? `Officer workspace · ${session?.role} view` : "Member workspace · Personal view"}</p>
      <h2>Your club at a glance.</h2>
      <p>{officer ? "See what needs attention first, then open the tools you use most." : "Check your own dues and receipts, and see what’s happening at the club."}</p>
    </header>

    <section className={styles.metrics} aria-label="Key figures">
      {(canClubReport || !officer) && <article className={styles.metric}>
        <h3>{officer ? "Dues outstanding" : "My dues"}</h3>
        <Amount source={dues} value={value => value.paise} />
        <p>{officer ? "All posted periods · Club member ledger" : "All assessed charges · Personal balance"}</p>
      </article>}
      {canClubReport && <article className={styles.metric}>
        <h3>Posted payments</h3><Amount source={dues} value={value => value.collected ?? 0} />
        <p>All posted periods · Club payments</p>
      </article>}
      {canStatement && <article className={styles.metric}>
        <h3>Club statement</h3><button className={styles.textButton} onClick={() => navigate("statement")}>Open statement ›</button>
      </article>}
    </section>

    <div className={styles.grid}>
      <section className={`${styles.panel} ${styles.priorityPanel}`}>
        <h3>Needs attention</h3>
        <div className={`${styles.priority} ${hasPriority ? styles.actionNeeded : dues.status === "ready" ? styles.caughtUp : ""}`} role="status">
          {dues.status === "loading" ? <p>Checking outstanding dues…</p>
            : dues.status === "unavailable" ? <p>Unable to verify dues. No priority can be confirmed.</p>
            : hasPriority ? <>
              <h4>{money.format(dues.data.paise / 100)} {officer ? "in posted ledger dues" : "in your outstanding dues"}</h4>
              <p>{officer ? "Review the balances in the club finance report before following up." : "Review your dues to see which charges are still open."}</p>
              <button className={styles.primary} onClick={() => navigate(officer ? "finance-report" : "my-dues")}>
                {officer ? "Review posted dues" : "View my dues"} ›
              </button>
            </> : <>
              <h4>You’re all caught up</h4>
              <p>No outstanding dues for {officer ? "the posted periods in this report" : "your assessed charges"}.</p>
            </>}
        </div>
      </section>

      <section className={`${styles.panel} ${styles.quickPanel}`}>
        <h3>Quick actions</h3>
        <div className={styles.quick}>
          {quick.filter(([, , allowed]) => allowed).map(([target, label]) =>
            <button key={target} onClick={() => navigate(target)}><span aria-hidden="true">↗</span>{label}</button>)}
        </div>
        {!quick.some(([, , allowed]) => allowed) && <p>No shortcuts are available for this account.</p>}
      </section>

      {events}
      {notices}
    </div>
  </section>;
}

function useMemoizedDues(clubId: number | undefined, memberId: number | undefined, officer: boolean, treasurer: boolean) {
  return useCallback((body: unknown): Dues => {
    if (officer) {
      const report = (body as { finance_reports?: { club_id: number; outstanding_dues_summary: { total_outstanding_amount: string; members_with_dues_count: number }; payment_collection_summary: { posted_payment_total: string; posted_payment_count?: number } } })?.finance_reports;
      if (!report || report.club_id !== clubId || !nonnegativeInteger(report.outstanding_dues_summary?.members_with_dues_count)) throw new Error("Invalid club report");
      const outstanding = paise(report.outstanding_dues_summary.total_outstanding_amount);
      const members = report.outstanding_dues_summary.members_with_dues_count;
      const collection = report.payment_collection_summary;
      if (!treasurer) return { paise: outstanding, members, collected: paise(collection?.posted_payment_total) };
      if ((outstanding === 0) !== (members === 0)) throw new Error("Inconsistent member dues");
      const count = collection?.posted_payment_count;
      let collected: number | undefined;
      try {
        collected = paise(collection?.posted_payment_total);
      } catch {
        // Missing payment totals do not invalidate verified member dues.
      }
      return { paise: outstanding, members, collected, paymentCount: nonnegativeInteger(count) ? count : undefined };
    }
    const balance = (body as { data?: { member_id: number; currency: string; outstanding_paise: number } })?.data;
    if (!balance || balance.member_id !== memberId || balance.currency !== "INR" || !nonnegativeInteger(balance.outstanding_paise)) throw new Error("Invalid personal balance");
    return { paise: balance.outstanding_paise };
  }, [clubId, memberId, officer, treasurer]);
}

function useMemoizedPayments(memberId: number | undefined) {
  return useCallback((body: unknown): Payment[] => {
    const data = (body as { data?: Payment[] })?.data;
    if (!Array.isArray(data) || data.some(row => !row || row.member_id !== memberId || !nonnegativeInteger(row.amount_paise) || !validDate(row.payment_date))) throw new Error("Invalid personal payments");
    return data;
  }, [memberId]);
}

function useMemoizedEvents(clubId: number | undefined) {
  return useCallback((body: unknown): Event[] => {
    const data = (body as { data?: Event[] })?.data;
    if (!Array.isArray(data) || data.some(row => !row || row.club_id !== clubId || typeof row.title !== "string" || !validDate(row.start_date ?? row.starts_at))) throw new Error("Invalid calendar");
    return data.filter(row => row.status === "published");
  }, [clubId]);
}

function readAnnouncements(body: unknown): Activity[] {
  const rows = (body as { data?: Array<{ id: number; title: string; published_at: string }> })?.data;
  if (!Array.isArray(rows) || rows.some(row => !row || !nonnegativeInteger(row.id) || typeof row.title !== "string" || !validDate(row.published_at))) throw new Error("Invalid announcements");
  return rows.map(row => ({ id: row.id, title: row.title, date: row.published_at }));
}
