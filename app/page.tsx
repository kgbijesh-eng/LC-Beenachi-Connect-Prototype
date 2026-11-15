import type { Metadata } from "next";
import Link from "next/link";
import styles from "./welcome/welcome.module.css";

export const metadata: Metadata = {
  title: "Welcome | LC Beenachi Connect",
  description: "A private pilot preview for members and officers of the Lions Club of Beenachi."
};

export default function HomePage() {
  return (
    <div className={styles.landing}>
      <a className={styles.skip} href="#main">Skip to content</a>
      <div className={styles["header-band"]} id="top">
        <header className={styles.header + " " + styles.shell}>
          <a className={styles.brand} href="#top"><span className={styles.monogram} aria-hidden="true">LC</span><span>LC Beenachi Connect<small>Lions Club of Beenachi</small></span></a>
          <nav className={styles.nav} aria-label="Main navigation">
            <a className={styles["nav-desktop"]} href="#features">Explore features</a>
            <a className={styles["nav-desktop"]} href="#access">Access</a>
            <a className={styles["nav-cta"]} href="#pilot">Pilot information <span aria-hidden="true">›</span></a>
          </nav>
        </header>
      </div>
      <main id="main" tabIndex={-1}>
        <div className={styles["wrap-dark"]}>
          <section className={styles.hero + " " + styles.shell} aria-labelledby="headline">
            <div>
              <p className={styles.eyebrow + " " + styles.light}>A digital home for the Lions Club of Beenachi</p>
              <h1 id="headline">Your club.<br /><span>In one place.</span></h1>
              <p className={styles.lede}>Find club updates, check your dues, and keep financial records organised. One workspace for members and club officers.</p>
              <div className={styles.actions}>
                <Link className={styles.button + " " + styles.gold} href="/portal" prefetch={false}>Member &amp; Officer Sign In <span aria-hidden="true">›</span></Link><a className={styles.button + " " + styles.secondary} href="#features">Explore features <span className={styles.arrow} aria-hidden="true">↓</span></a>
                <a className={styles.button + " " + styles.secondary} href="#pilot">Private pilot <span aria-hidden="true">›</span></a>
              </div>
              <div className={styles.status}>
                <i aria-hidden="true"></i> In local testing · Not open for member sign-up</div>
            </div>
            <div className={styles["mock-frame"]} role="img" aria-label="Illustrative club statement with synthetic test data">
              <div className={styles["mock-bar"]}>
                <span className={styles.dots} aria-hidden="true"><i></i><i></i><i></i></span><span>LC BEENACHI CONNECT</span><span>PRODUCT PREVIEW</span>
              </div>
              <div className={styles["mock-body"]}>
                <div className={styles["mock-side"]} aria-hidden="true">
                  <b>Club workspace</b><span>Dashboard</span><span>My Member Area</span><span className={styles.selected}>Finance</span><span>Club Visibility</span><span>Reports &amp; Tools</span>
                </div>
                <div className={styles["mock-main"]}>
                  <div className={styles["mock-kicker"]}>Finance / Account overview</div>
                  <h2>Club Account Statement</h2>
                  <div className={styles["mock-meta"]}>
                    <span>Lions Year 2027–28</span><span className={styles.pill}><i></i> Reconciled</span>
                  </div>
                  <div className={styles["mock-kpis"]}>
                    <div>
                      <small>Period debits</small><strong>₹2.00</strong>
                    </div>
                    <div>
                      <small>Period credits</small><strong>₹2.00</strong>
                    </div>
                  </div>
                  <div className={styles["mock-table"]}>
                    <div className={styles["mock-row"]}>
                      <b>Journal traceability</b><span>2 entries</span>
                    </div>
                    <div className={styles["mock-row"]}>
                      <b>Opening entry</b><span>01 Jul 2027</span>
                    </div>
                    <div className={styles["mock-row"]}>
                      <b>Dues entry</b><span>15 Jul 2027</span>
                    </div>
                  </div>
                  <p className={styles["mock-caption"]}>Illustrative view · Synthetic sample data only</p>
                </div>
              </div>
            </div>
          </section>
        </div>
        <div className={styles.strip}>
          <div className={styles.shell}>
            <span><b>Lions Club of Beenachi</b> &nbsp;·&nbsp; District 318E</span><span>Sulthan Bathery, Wayanad</span>
          </div>
        </div>
        <section className={styles.section + " " + styles.shell} id="features" aria-labelledby="feature-title">
          <p className={styles.eyebrow + " " + styles.dark}>What it brings together</p>
          <h2 className={styles["section-title"]} id="feature-title">The work behind a club.</h2>
          <p className={styles.intro}>From finding the latest club announcement to tracing an entry in the accounts, Connect gives members and officers a place to do their everyday work.</p>
          <div className={styles["work-grid"]}>
            <article className={styles.card}>
              <span className={styles.icon} aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h4" /></svg></span><h3>Finance &amp; records</h3>
              <p>Authorized officers can review the club statement, track dues and expenses, and follow entries back to the accounting ledger.</p>
            </article>
            <article className={styles.card}>
              <span className={styles.icon} aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3" /><path d="M5 20v-2a7 7 0 0 1 14 0v2" /></svg></span><h3>Member area</h3>
              <p>Members have a place to check their own dues and payment history and submit profile updates.</p>
            </article>
            <article className={styles.card}>
              <span className={styles.icon} aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="6" width="16" height="14" rx="2" /><path d="M8 4v4m8-4v4M4 11h16m-12 4h3m2 0h3" /></svg></span><h3>Club activity</h3>
              <p>Find announcements, meetings, events, and reminders without hunting through old messages.</p>
            </article>
          </div>
        </section>
        <section className={styles.access + " " + styles.shell} id="access" aria-labelledby="access-title">
          <p className={styles.eyebrow + " " + styles.dark}>Access by role</p>
          <h2 className={styles["section-title"]} id="access-title">One workspace, different views.</h2>
          <div className={styles["access-grid"]}>
            <article className={styles["access-card"]}>
              <h3>For members</h3>
              <p>See your own membership and payment information, plus updates the club makes available to you.</p>
            </article>
            <article className={styles["access-card"]}>
              <h3>For officers</h3>
              <p>See the tools assigned to your role. Club statements and restricted finance actions require the relevant permission.</p>
            </article>
          </div>
        </section>
        <section className={styles.shell} id="pilot" aria-labelledby="pilot-title">
          <div className={styles.cta}>
            <div>
              <p className={styles.eyebrow + " " + styles.light}>Pilot information</p>
              <h2 id="pilot-title">Starting with club officers.</h2>
              <p>The next step is a private pilot with a small group of officers. They’ll try everyday workflows with sample data before the club invites members.</p>
            </div>
            <div className={styles["pilot-notes"]}>
              <strong>Interested in the pilot?</strong><p>Speak with the club committee. Existing members and officers can use their club sign-in.</p>
            </div>
          </div>
        </section>
      </main>
      <footer className={styles.footer}>
        <div className={styles.shell}>
          <span><b>LC Beenachi Connect</b> &nbsp;·&nbsp; Lions Club of Beenachi</span><a href="#top">Back to top ↑</a>
        </div>
      </footer>
    </div>
  );
}
