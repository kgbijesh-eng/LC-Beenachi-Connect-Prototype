export type StatementRange = { lionsYear: string; from: string; to: string; journalId?: string };

type Balance = { opening: number; closing: number };
type Movement = { incoming: number; outgoing: number };
type Fund = Balance & Movement & { name: string };
type Activity = { id: string; date: string; label: string; reference: string; amount: number };
type Account = {
  account_code: string; normal_balance: string; opening_balance_paise: number;
  closing_balance_paise: number; net_movement_paise: number;
};
type Line = { classification: string; fund_code: string; direction: string; amount_paise: number };
type Journal = {
  journal_id: string; reference: string; accounting_date: string; contribution: string;
  source_type: string; reversal_of_journal_id: string | null; lines: Line[];
};

const activityTypes: Record<string, string> = {
  member_payment: "Member payment",
  club_ordinary_income_receipt: "Income receipt",
  club_expense: "Expense",
  club_expense_reversal: "Expense reversal",
  club_ordinary_income_receipt_correction: "Receipt reversal"
};

export function coveredMonth(month: string, today: string): StatementRange | null {
  if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month)) return null;
  const [year, number] = month.split("-").map(Number);
  const start = number >= 7 ? year : year - 1;
  const from = month + "-01";
  if (from > today) return null;
  const lastDay = new Date(Date.UTC(year, number, 0)).getUTCDate();
  return { lionsYear: start + "-" + String((start + 1) % 100).padStart(2, "0"), from, to: [today, month + "-" + lastDay].sort()[0] };
}

export function financeSnapshot(body: unknown, clubId: number, requested: StatementRange) {
  const data = (body as { data?: {
    club_id: number; currency: string; amount_unit: string; lions_year: { label: string };
    range: { from: string; to: string }; source: { cutover_date: string };
    integrity: Record<string, boolean>;
    funds: { fund_code: string; display_name: string; classifications: { classification: string; accounts: Account[] }[] }[];
    traceability: { journals: Journal[] };
  } })?.data;
  const cutover = data?.source?.cutover_date;
  if (!data || data.club_id !== clubId || data.currency !== "INR" || data.amount_unit !== "paise"
    || data.lions_year?.label !== requested.lionsYear || !cutover || !/^\d{4}-\d{2}-\d{2}$/.test(cutover)
    || data.range?.from !== (cutover > requested.from ? cutover : requested.from) || data.range?.to !== requested.to
    || data.range.from > data.range.to || !data.integrity || Object.values(data.integrity).length === 0
    || Object.values(data.integrity).some(value => value !== true)
    || !Array.isArray(data.funds) || !Array.isArray(data.traceability?.journals)) throw new Error("Invalid statement");

  const cash: Balance = { opening: 0, closing: 0 };
  const bank: Balance = { opening: 0, closing: 0 };
  const funds = new Map<string, Fund>();
  const activity: Activity[] = [];

  for (const fund of data.funds) {
    if (!fund.fund_code || typeof fund.display_name !== "string" || funds.has(fund.fund_code)) throw new Error("Invalid fund");
    const total = { name: fund.display_name, opening: 0, closing: 0, incoming: 0, outgoing: 0 };
    funds.set(fund.fund_code, total);
    for (const group of fund.classifications) {
      if (group.classification !== "CASH_AND_BANK") continue;
      for (const account of group.accounts) {
        const target = account.account_code === "CASH-ON-HAND" ? cash : account.account_code.startsWith("BANK-") ? bank : null;
        const { opening_balance_paise: opening, closing_balance_paise: closing, net_movement_paise: movement } = account;
        if (!target || account.normal_balance !== "debit" || ![opening, closing, movement].every(Number.isSafeInteger)
          || opening + movement !== closing) throw new Error("Invalid cash or bank account");
        target.opening += opening;
        target.closing += closing;
        total.opening += opening;
        total.closing += closing;
      }
    }
  }

  const journalIds = new Set<string>();
  for (const journal of data.traceability.journals) {
    if (journal.contribution === "opening_balance") continue;
    if (journal.contribution !== "period_movement" || journal.accounting_date < data.range.from
      || journal.accounting_date > data.range.to || !journal.journal_id || journalIds.has(journal.journal_id)
      || !Array.isArray(journal.lines) || journal.lines.length === 0) throw new Error("Invalid journal");
    journalIds.add(journal.journal_id);
    const perFund = new Map<string, number>();
    for (const line of journal.lines) {
      if (typeof line.classification !== "string" || !Number.isSafeInteger(line.amount_paise) || line.amount_paise <= 0
        || !["debit", "credit"].includes(line.direction) || !funds.has(line.fund_code)) throw new Error("Missing journal line details");
      if (line.classification !== "CASH_AND_BANK") continue;
      const amount = line.direction === "debit" ? line.amount_paise : -line.amount_paise;
      perFund.set(line.fund_code, (perFund.get(line.fund_code) ?? 0) + amount);
    }
    let net = 0;
    for (const [fundCode, amount] of perFund) {
      if (journal.source_type === "club_internal_transfer" && amount !== 0) throw new Error("Transfer does not reconcile");
      const fund = funds.get(fundCode)!;
      net += amount;
      if (amount > 0) fund.incoming += amount;
      else fund.outgoing -= amount;
    }
    const label = activityTypes[journal.source_type];
    if (label && net !== 0) activity.push({
      id: journal.journal_id, date: journal.accounting_date, reference: journal.reference, amount: net,
      label: journal.source_type === "member_payment" && journal.reversal_of_journal_id ? "Payment reversal" : label
    });
  }

  let incoming = 0;
  let outgoing = 0;
  for (const fund of funds.values()) {
    if (![fund.opening, fund.closing, fund.incoming, fund.outgoing].every(Number.isSafeInteger)
      || fund.closing - fund.opening !== fund.incoming - fund.outgoing) throw new Error("Cash movement does not reconcile");
    incoming += fund.incoming;
    outgoing += fund.outgoing;
  }
  if (![cash.opening, cash.closing, bank.opening, bank.closing, incoming, outgoing].every(Number.isSafeInteger)) throw new Error("Invalid totals");
  return {
    range: { ...requested, from: data.range.from }, cutover, cash, bank, incoming, outgoing,
    funds: Array.from(funds.values()),
    activity: activity.sort((a, b) => b.date.localeCompare(a.date)).slice(0, 5)
  };
}
