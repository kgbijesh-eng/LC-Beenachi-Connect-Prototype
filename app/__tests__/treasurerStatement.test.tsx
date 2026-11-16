import { expect, it } from "vitest";
import { coveredMonth, financeSnapshot } from "../treasurerStatement";

const requested = { lionsYear: "2027-28", from: "2027-07-01", to: "2027-07-31" };
const fundCode = "GENERAL_CLUB_FUND";
const asset = (direction: string, amount_paise: number) => ({
  classification: "CASH_AND_BANK", direction, amount_paise, fund_code: fundCode
});
const journal = (id: string, source_type: string, lines: ReturnType<typeof asset>[], reversal_of_journal_id: string | null = null) => ({
  journal_id: id, reference: id, accounting_date: "2027-07-20", source_type, lines,
  reversal_of_journal_id, contribution: "period_movement"
});
const account = (account_code: string, opening: number, closing: number) => ({
  account_code, normal_balance: "debit", opening_balance_paise: opening,
  closing_balance_paise: closing, net_movement_paise: closing - opening
});
function fixture() {
  return { data: {
    club_id: 4, currency: "INR", amount_unit: "paise", lions_year: { label: requested.lionsYear },
    range: { from: "2027-07-10", to: requested.to }, source: { cutover_date: "2027-07-10" },
    integrity: { trial_balance_reconciles: true },
    funds: [{ fund_code: fundCode, display_name: "General Club Fund", classifications: [{
      classification: "CASH_AND_BANK", accounts: [account("CASH-ON-HAND", 1000, 900), account("BANK-OPERATING", 2000, 2700)]
    }] }],
    traceability: { journals: [
      { ...journal("opening", "opening", [asset("debit", 3000)]), accounting_date: "2027-07-10", contribution: "opening_balance" },
      journal("payment", "member_payment", [asset("debit", 200)]),
      journal("receipt", "club_ordinary_income_receipt", [asset("debit", 400)]),
      journal("expense", "club_expense", [asset("credit", 150)]),
      journal("transfer", "club_internal_transfer", [asset("credit", 300), asset("debit", 300)]),
      journal("reversal", "club_expense_reversal", [asset("debit", 150)], "expense")
    ] }
  } };
}

it("uses inclusive month ends, caps an open month at today, and rolls Lions Year in July", () => {
  expect(coveredMonth("2028-02", "2028-07-13")).toEqual({ lionsYear: "2027-28", from: "2028-02-01", to: "2028-02-29" });
  expect(coveredMonth("2028-06", "2028-07-13")?.to).toBe("2028-06-30");
  expect(coveredMonth("2028-07", "2028-07-13")).toEqual({ lionsYear: "2028-29", from: "2028-07-01", to: "2028-07-13" });
  expect(coveredMonth("2028-08", "2028-07-13")).toBeNull();
});

it("reconciles partially covered balances with external movements and shows one row per posted source journal", () => {
  const result = financeSnapshot(fixture(), 4, requested);
  expect(result.range.from).toBe("2027-07-10");
  expect(result.cash).toEqual({ opening: 1000, closing: 900 });
  expect(result.bank).toEqual({ opening: 2000, closing: 2700 });
  expect(result.incoming).toBe(750);
  expect(result.outgoing).toBe(150);
  expect(result.funds).toEqual([{ name: "General Club Fund", opening: 3000, closing: 3600, incoming: 750, outgoing: 150 }]);
  expect(result.activity.map(row => [row.label, row.amount])).toEqual([
    ["Member payment", 200], ["Income receipt", 400], ["Expense", -150], ["Expense reversal", 150]
  ]);
});

it("rejects wrong-club, missing, out-of-range and unreconciled projections", () => {
  expect(() => financeSnapshot(fixture(), 5, requested)).toThrow();
  expect(() => financeSnapshot({}, 4, requested)).toThrow();
  const changed = fixture();
  changed.data.traceability.journals[1].accounting_date = "2027-07-09";
  expect(() => financeSnapshot(changed, 4, requested)).toThrow();
  changed.data.traceability.journals[1].accounting_date = "2027-07-20";
  changed.data.traceability.journals[1].lines[0].amount_paise += 1;
  expect(() => financeSnapshot(changed, 4, requested)).toThrow();
});

it("accepts confirmed zero with an intact statement and no posted movements", () => {
  const zero = fixture();
  zero.data.funds[0].classifications[0].accounts = [];
  zero.data.traceability.journals = [];
  const result = financeSnapshot(zero, 4, requested);
  expect([result.cash.closing, result.bank.closing, result.incoming, result.outgoing]).toEqual([0, 0, 0, 0]);
});
