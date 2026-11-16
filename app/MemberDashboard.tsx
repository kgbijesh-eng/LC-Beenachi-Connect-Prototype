import type { ReactNode } from "react";
import styles from "./MemberDashboard.module.css";

type Balance = { status: "ready"; amount: string; outstanding: boolean }
  | { status: "loading" | "unavailable" };

export default function MemberDashboard({
  name, club, balance, payment, receipt, events, announcements, onCard, onDues, onPayments
}: {
  name: string;
  club: string;
  balance: Balance;
  payment: string;
  receipt: string;
  events: ReactNode;
  announcements: ReactNode;
  onCard?: () => void;
  onDues?: () => void;
  onPayments?: () => void;
}) {
  const status = balance.status === "ready" ? balance.outstanding ? "Outstanding" : "Up to date"
    : balance.status === "loading" ? "Checking dues" : "Unavailable";
  const tone = balance.status === "ready" ? balance.outstanding ? styles.due : styles.current : "";

  return <section className={styles.dashboard} aria-label="Member dashboard">
    <header className={styles.welcome}>
      <div>
        <p>{club}</p>
        <h2>Welcome, {name}.</h2>
        <p>Check your dues and see what is happening at the club.</p>
      </div>
      {onCard && <button type="button" onClick={onCard}>My digital card</button>}
    </header>
    <div className={styles.grid}>
      <section className={styles.card} aria-labelledby="member-dashboard-dues">
        <div role="status">
          <div className={styles.cardHeader}>
            <h3 id="member-dashboard-dues">My dues</h3>
            <span className={[styles.badge, tone].join(" ")}>{status}</span>
          </div>
          {balance.status === "ready" && <strong className={styles.amount}>{balance.amount}</strong>}
          <p>{balance.status === "ready"
            ? balance.outstanding ? "Outstanding balance across your assessed charges." : "No outstanding dues for your assessed charges."
            : balance.status === "loading" ? "Checking your current balance…" : "Your balance could not be verified."}</p>
        </div>
        {onDues && <button type="button" onClick={onDues}>View my dues ›</button>}
      </section>
      <section className={styles.card} aria-labelledby="member-dashboard-payments">
        <h3 id="member-dashboard-payments">Payments &amp; receipts</h3>
        <dl className={styles.ledger}>
          <div><dt>Last posted payment</dt><dd>{payment}</dd></div>
          <div><dt>Recent receipts</dt><dd>{receipt}</dd></div>
        </dl>
        {onPayments && <button type="button" onClick={onPayments}>View payment history ›</button>}
      </section>
      {events}
      {announcements}
    </div>
  </section>;
}
