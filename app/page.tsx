"use client";

import { type FormEvent, useEffect, useMemo, useState } from "react";

type Role = "president" | "secretary" | "treasurer" | "member";
type FinanceSummaryStatus = "loading" | "ready" | "error";
type ReceiptLookupStatus = "initializing" | "ready" | "loading" | "success" | "error";
type ClubFinanceReportStatus = "loading" | "ready" | "empty" | "missing-club" | "unauthenticated" | "forbidden" | "error";
type PaymentHistoryStatus = "initializing" | "ready" | "loading" | "success" | "empty" | "error";
type MemberImportPreviewStatus = "initializing" | "ready" | "loading" | "success" | "empty" | "error";
type MemberImportCommitStatus = "idle" | "confirming" | "loading" | "success" | "error";

type MemberFinancePendingItem = {
  source_type: string;
  source_label: string;
  amount: string;
  due_date: string | null;
  status: string;
};

type MemberFinanceSummary = {
  member_id: number;
  member_code: string;
  member_name: string;
  current_dues_amount: string;
  current_dues_label: string;
  payment_status: string;
  last_payment_amount: string | null;
  last_payment_date: string | null;
  receipt_available: boolean | null;
  receipt_status: string | null;
  pending_items: MemberFinancePendingItem[];
  generated_at: string;
};

type ClubFinanceReportMemberSummary = {
  member_id?: number;
  member_code?: string;
  member_name?: string;
  total_debit_amount?: string | null;
  total_credit_amount?: string | null;
  current_dues_amount?: string | null;
  current_dues_label?: string | null;
};

type ClubFinanceReports = {
  club_id?: number;
  club_name?: string | null;
  club_finance_overview?: {
    total_debit_amount?: string | null;
    total_credit_amount?: string | null;
    current_dues_amount?: string | null;
    current_dues_label?: string | null;
    posted_payment_count?: number | null;
    issued_receipt_count?: number | null;
  };
  outstanding_dues_summary?: {
    members_with_dues_count?: number | null;
    total_outstanding_amount?: string | null;
  };
  member_wise_dues_summary?: ClubFinanceReportMemberSummary[];
  payment_collection_summary?: {
    posted_payment_total?: string | null;
    posted_payment_count?: number | null;
  };
  receipt_summary?: {
    issued_receipt_count?: number | null;
  };
  generated_at?: string | null;
};

type ClubBalanceSheet = {
  club_id?: number;
  club_name?: string | null;
  assets?: {
    member_receivables?: string | null;
    cash_collected?: string | null;
  };
  liabilities?: {
    member_advances?: string | null;
  };
  net_position?: {
    amount?: string | null;
    label?: string | null;
  };
  source?: string | null;
  generated_at?: string | null;
};

const API_BASE_URL = "http://127.0.0.1:8000/api/v1";
const LOCAL_MEMBER_FINANCE_LOGIN = {
  email: "local.member@lcbeenachi.test",
  password: "password"
};

type PaymentRecordingStatus = "initializing" | "ready" | "submitting" | "success" | "error";
type PaymentRecordingMode = "cash" | "bank_transfer" | "upi" | "cheque" | "card" | "adjustment";

type PaymentRecordingLogin = {
  email: string;
  password: string;
};

type BackendClubMembership = {
  club?: {
    id?: number;
  };
};

type BackendMemberRecord = {
  id: number;
  club_id: number;
  member_code: string;
  full_name: string;
  status: string;
};

type PaymentRecordingFormState = {
  amount: string;
  payment_date: string;
  payment_mode: PaymentRecordingMode;
  reference_number: string;
  notes: string;
};

type PaymentRecordingApiPayment = {
  id: number;
  amount: string;
  payment_date: string | null;
  payment_mode: string;
  reference_number: string | null;
  notes: string | null;
  status: string;
  ledger_entry_id: number | null;
};

type ReceiptApiReceipt = {
  receipt_id: number;
  receipt_number: string;
  club_id: number;
  member_id: number | null;
  member_name: string | null;
  payment_id: number;
  amount: string | null;
  payment_date: string | null;
  payment_method: string | null;
  receipt_status: string;
  generated_at: string | null;
  generated_by: number | null;
};

type PaymentHistoryApiRecord = {
  payment_id?: number | string | null;
  payment_date?: string | null;
  amount?: string | null;
  payment_mode?: string | null;
  status?: string | null;
  member?: {
    id?: number | null;
    member_code?: string | null;
    name?: string | null;
  } | null;
  receipt?: {
    available?: boolean | null;
    receipt_number?: string | null;
    status?: string | null;
  } | null;
  recorded_by?: {
    id?: number | null;
    name?: string | null;
  } | null;
  created_at?: string | null;
};

type PaymentHistoryMeta = {
  current_page?: number | null;
  per_page?: number | null;
  total?: number | null;
  last_page?: number | null;
};

type PaymentHistoryApiResponse = {
  payment_history?: PaymentHistoryApiRecord[];
  meta?: PaymentHistoryMeta;
};

type MemberImportPreviewSummary = {
  total_rows?: number | null;
  accepted_rows?: number | null;
  rejected_rows?: number | null;
  duplicate_rows?: number | null;
  possible_existing_match_rows?: number | null;
};

type MemberImportPreviewDuplicateMatch = {
  field?: string | null;
  value?: string | null;
  matched_rows?: number[] | null;
  member_id?: number | string | null;
  member_code?: string | null;
  member_name?: string | null;
};

type MemberImportPreviewRow = {
  row_number?: number | null;
  valid?: boolean | null;
  intent?: string | null;
  normalized?: Record<string, string | number | boolean | null> | null;
  errors?: string[] | null;
  duplicate_matches?: {
    batch?: MemberImportPreviewDuplicateMatch[] | null;
    existing?: MemberImportPreviewDuplicateMatch[] | null;
  } | null;
};

type MemberImportPreviewPayload = {
  dry_run?: boolean | null;
  committed?: boolean | null;
  summary?: MemberImportPreviewSummary | null;
  rows?: MemberImportPreviewRow[] | null;
};

type MemberImportPreviewApiResponse = {
  import_preview?: MemberImportPreviewPayload;
};

type MemberImportCommitSummary = {
  total_rows?: number | null;
  created_rows?: number | null;
  skipped_rows?: number | null;
  failed_rows?: number | null;
};

type MemberImportCommitRow = {
  row_number?: number | null;
  status?: string | null;
  member_id?: number | string | null;
  errors?: string[] | null;
  warnings?: string[] | null;
  normalized?: Record<string, string | number | boolean | null> | null;
};

type MemberImportCommitPayload = {
  dry_run?: boolean | null;
  committed?: boolean | null;
  summary?: MemberImportCommitSummary | null;
  rows?: MemberImportCommitRow[] | null;
};

type MemberImportCommitApiResponse = {
  import_commit?: MemberImportCommitPayload;
};

type ApiErrorPayload = {
  message?: string;
  errors?: Record<string, string[]>;
};

const LOCAL_PAYMENT_RECORDING_LOGINS: Record<Exclude<Role, "member">, PaymentRecordingLogin> = {
  president: {
    email: "president@lcbeenachi.test",
    password: "password"
  },
  secretary: {
    email: "secretary@lcbeenachi.test",
    password: "password"
  },
  treasurer: {
    email: "treasurer@lcbeenachi.test",
    password: "password"
  }
};

const PAYMENT_RECORDING_MODES: PaymentRecordingMode[] = [
  "cash",
  "bank_transfer",
  "upi",
  "cheque",
  "card",
  "adjustment"
];

const PAYMENT_RECORDING_MODE_LABELS: Record<PaymentRecordingMode, string> = {
  cash: "Cash",
  bank_transfer: "Bank transfer",
  upi: "UPI",
  cheque: "Cheque",
  card: "Card",
  adjustment: "Adjustment"
};
type Screen =
  | "login"
  | "dashboard"
  | "people-module"
  | "operations-module"
  | "finance-module"
  | "reports-tools-module"
  | "notifications-reminders"
  | "club-visibility-module"
  | "report-detail"
  | "nav-detail"
  | "members"
  | "committees"
  | "member-profile"
  | "digital-card"
  | "fellowship"
  | "fellowship-queue"
  | "confirm-sponsors"
  | "ledger"
  | "member-dues-detail"
  | "special-collection"
  | "record-member-payment"
  | "collection-box"
  | "receipt-list"
  | "payment-history"
  | "member-import-preview"
  | "outstanding-dues"
  | "collection-summary"
  | "fellowship-payments"
  | "events-meetings"
  | "announcements"
  | "announcement-detail"
  | "documents"
  | "projects"
  | "celebrations"
  | "donor-consent"
  | "donor-search"
  | "reports"
  | "settings"
  | "dashboard-detail";

type FellowshipTab = "Overview" | "Queue" | "Selected Sponsors" | "Payments" | "History";
type NavItem = {
  label: string;
  screen?: Screen;
  reportSection?: ReportsSection;
  reportDetail?: string;
  detailParent?: string;
  detailTitle?: string;
  fellowshipTab?: FellowshipTab;
  eventMeetingSection?: EventMeetingSection;
  announcementDetail?: AnnouncementDetail;
  financeSection?: FinanceSection;
  statementSection?: ClubStatementSection;
  collectionDetail?: Exclude<CollectionDetail, null>;
  fellowshipPaymentView?: FellowshipPaymentView;
  children?: NavItem[];
};
type NavGroup = { title: string; items: NavItem[]; direct?: boolean };
type CollectionDetail = "today" | "month" | "pending" | "fellowship" | "split" | null;
type EventMeetingSection = EventMeeting["section"];
type AnnouncementDetail = "Published" | "Draft / Pending" | "High Priority";
type FellowshipPaymentView = "Current Month" | "Pending Sponsors" | "Paid Sponsors";
type PeopleSection = "Members" | "Board & Committees" | "Celebrations";
type OperationsSection = "Events & Meetings" | "Announcements" | "Documents" | "Projects / Activities" | "Fellowship";
type FinanceSection = "Club Account Statement" | "Member Ledger / Dues" | "Payments / Receipts" | "Club Finance Reports" | "Balance Sheet Summary" | "Outstanding Dues" | "Collection Summary" | "Fellowship Payments";
type ClubStatementSection =
  | "Account Overview"
  | "Fund-wise Opening Balance"
  | "Fund-wise Summary"
  | "Recent Transactions"
  | "Central Ledger Structure"
  | "Kuri / Chit Fund - Future Phase";
type ReportsSection =
  | "Member Reports"
  | "Finance / Ledger Reports"
  | "Fellowship Reports"
  | "Events & Meetings Reports"
  | "Club Activity / Service Reports"
  | "Celebration Reports"
  | "Blood Donor Consent Report"
  | "Document Reports";
type ReminderSection =
  | "Reminder Overview"
  | "Dues Reminders"
  | "Meeting Reminders"
  | "Celebration Reminders"
  | "Announcement Reminders"
  | "Message Templates"
  | "Reminder History"
  | "Reminder Settings";
type ClubVisibilitySection = "Members" | "Events & Meetings" | "Announcements" | "Documents" | "Projects / Activities" | "Celebrations";
type DashboardDetail =
  | "pending-member-updates"
  | "upcoming-events"
  | "fellowship-status"
  | "pending-dues"
  | "activity-reports"
  | "published-items"
  | "celebration-reminders"
  | "club-reminders"
  | "active-members"
  | "today-collection"
  | "month-collection"
  | "fellowship-pending"
  | "mode-split"
  | "pst-profiles"
  | "member-dues"
  | "member-fellowship"
  | "member-events"
  | "member-published";

type Member = {
  id: string;
  name: string;
  dashboardName: string;
  clubRole: string;
  status: string;
  bloodGroup: string;
  phone: string;
  consentContact: boolean;
  donorConsent: boolean;
  dues: number;
  birthday: string;
  anniversary: string;
  consentDob: boolean;
  consentAnniversary: boolean;
  wishStatus: "Pending" | "Sent";
};

type EventMeeting = {
  title: string;
  date: string;
  time?: string;
  venue: string;
  type: "Event" | "Meeting";
  section: "Upcoming Events" | "Upcoming Meetings" | "Past Events" | "Meeting Notices";
  visibility: "All Members" | "Board Only" | "PST";
  status: "Published" | "Draft" | "Completed";
};

type Activity = {
  name: string;
  date: string;
  month: string;
  category: string;
  venue: string;
  lead: string;
  description: string;
  peopleServed: number;
  estimatedBudget: number;
  actualExpense: number;
  sponsors: string;
  status: "Planned" | "Completed" | "Cancelled";
  photos: string;
  reportDocument: string;
};

const members: Member[] = [
  {
    id: "MEM-001",
    name: "Lion Bijesh",
    dashboardName: "Lion Bijesh",
    clubRole: "President",
    status: "Active",
    bloodGroup: "O+",
    phone: "+91 98765 10001",
    consentContact: true,
    donorConsent: true,
    dues: 0,
    birthday: "03 Jun",
    anniversary: "20 Jun",
    consentDob: true,
    consentAnniversary: true,
    wishStatus: "Pending"
  },
  {
    id: "MEM-002",
    name: "Lion Secretary User",
    dashboardName: "Lion Secretary User",
    clubRole: "Secretary",
    status: "Active",
    bloodGroup: "A+",
    phone: "+91 98765 10002",
    consentContact: true,
    donorConsent: false,
    dues: 0,
    birthday: "12 Jun",
    anniversary: "Hidden",
    consentDob: true,
    consentAnniversary: false,
    wishStatus: "Sent"
  },
  {
    id: "MEM-003",
    name: "Lion Treasurer User",
    dashboardName: "Lion Treasurer User",
    clubRole: "Treasurer",
    status: "Active",
    bloodGroup: "B+",
    phone: "+91 98765 10003",
    consentContact: false,
    donorConsent: true,
    dues: 0,
    birthday: "22 Jun",
    anniversary: "02 Jul",
    consentDob: true,
    consentAnniversary: true,
    wishStatus: "Pending"
  },
  {
    id: "MEM-004",
    name: "Lion Ajith Kumar - Member",
    dashboardName: "Lion Ajith Kumar",
    clubRole: "Member",
    status: "Active",
    bloodGroup: "AB+",
    phone: "+91 98765 10004",
    consentContact: true,
    donorConsent: true,
    dues: 1250,
    birthday: "Today",
    anniversary: "28 Jun",
    consentDob: true,
    consentAnniversary: true,
    wishStatus: "Pending"
  }
];

const eventsMeetings: EventMeeting[] = [
  {
    title: "Monthly Family Fellowship",
    date: "15 Jun 2026",
    time: "7:00 PM",
    venue: "Club Hall",
    type: "Event",
    section: "Upcoming Events",
    visibility: "All Members",
    status: "Published"
  },
  {
    title: "Board Meeting",
    date: "10 Jun 2026",
    time: "7:30 PM",
    venue: "Club Hall",
    type: "Meeting",
    section: "Upcoming Meetings",
    visibility: "Board Only",
    status: "Published"
  },
  {
    title: "PST Review",
    date: "14 Jun 2026",
    time: "6:30 PM",
    venue: "Online",
    type: "Meeting",
    section: "Upcoming Meetings",
    visibility: "PST",
    status: "Published"
  },
  {
    title: "Medical Camp",
    date: "24 Jun 2026",
    time: "9:00 AM",
    venue: "Community Hall",
    type: "Event",
    section: "Upcoming Events",
    visibility: "All Members",
    status: "Published"
  },
  {
    title: "General Body Meeting",
    date: "28 Jun 2026",
    time: "7:00 PM",
    venue: "Club Hall",
    type: "Meeting",
    section: "Upcoming Meetings",
    visibility: "All Members",
    status: "Published"
  },
  {
    title: "Service Project Visit",
    date: "18 May 2026",
    venue: "Beenachi School",
    type: "Event",
    section: "Past Events",
    visibility: "All Members",
    status: "Completed"
  }
];

const announcements = [
  { title: "District Governor Visit Planning", priority: "High", audience: "Members", status: "Published", date: "03 Jun 2026", preview: "Important announcement for members regarding the upcoming District Governor visit." },
  { title: "Monthly Family Fellowship Sponsorship", priority: "Medium", audience: "Sponsors", status: "Draft", date: "04 Jun 2026", preview: "Sponsor confirmation for the Monthly Family Fellowship." },
  { title: "Annual Plan Updated", priority: "Normal", audience: "Members", status: "Published", date: "02 Jun 2026", preview: "The updated Annual Plan 2026 is now available in Documents." }
];

const documents = [
  { title: "General Body Minutes - May 2026", category: "Meeting minutes", visibility: "Members", date: "31 May 2026" },
  { title: "Annual Plan 2026", category: "Planning document", visibility: "Members", date: "02 Jun 2026" },
  { title: "Member Guidelines", category: "Club document", visibility: "Members", date: "01 Jun 2026" },
  { title: "Public Circular - June Meeting", category: "Member circular", visibility: "Members", date: "03 Jun 2026" },
  { title: "Board Meeting Minutes - May 2026", category: "Board minutes", visibility: "Board", date: "30 May 2026" },
  { title: "Treasurer Internal Finance Note", category: "Internal finance", visibility: "Treasurer", date: "03 Jun 2026" },
  { title: "Project Report Template", category: "Committee template", visibility: "Committee", date: "28 May 2026" }
];

const activities: Activity[] = [
  {
    name: "School Kit Distribution",
    date: "07 Jun 2026",
    month: "June",
    category: "Education",
    venue: "Beenachi School",
    lead: "Education Committee",
    description: "Distribution of school kits to selected students.",
    peopleServed: 45,
    estimatedBudget: 18000,
    actualExpense: 17250,
    sponsors: "Member sponsors",
    status: "Completed",
    photos: "3 photos placeholder",
    reportDocument: "Report draft pending"
  },
  {
    name: "Medical Camp",
    date: "24 Jun 2026",
    month: "June",
    category: "Health",
    venue: "Community Hall",
    lead: "Health Committee",
    description: "General health screening camp.",
    peopleServed: 120,
    estimatedBudget: 35000,
    actualExpense: 0,
    sponsors: "Local clinic",
    status: "Planned",
    photos: "Pending",
    reportDocument: "Not started"
  },
  {
    name: "Tree Planting Drive",
    date: "12 May 2026",
    month: "May",
    category: "Environment",
    venue: "Town Park",
    lead: "Lion Secretary User",
    description: "Tree planting with local youth volunteers.",
    peopleServed: 80,
    estimatedBudget: 12000,
    actualExpense: 10500,
    sponsors: "Nursery partner",
    status: "Completed",
    photos: "6 photos placeholder",
    reportDocument: "Uploaded"
  }
];

const fellowshipQueue = [
  { order: 1, member: "Lion Bijesh", status: "Eligible", last: "Jan 2026" },
  { order: 2, member: "Lion Secretary User", status: "Eligible", last: "Feb 2026" },
  { order: 3, member: "Lion Treasurer User", status: "Eligible", last: "Mar 2026" },
  { order: 4, member: "Lion Ajith Kumar - Member", status: "Hold", last: "Apr 2026" },
  { order: 5, member: "Lion Pradeep", status: "Eligible", last: "May 2026" }
];

const ledgerRows = [
  { id: "ledger-001", date: "01 Jun", member: "Lion Ajith Kumar - Member", type: "Monthly dues", debit: 500, credit: 0 },
  { id: "ledger-002", date: "05 Jun", member: "Lion Ajith Kumar - Member", type: "Payment received", debit: 0, credit: 1000 },
  { id: "ledger-003", date: "08 Jun", member: "Lion Treasurer User", type: "Fellowship sponsor ledger entry", debit: 2500, credit: 0 },
  { id: "ledger-004", date: "12 Jun", member: "Lion Bijesh", type: "Project share", debit: 500, credit: 0 }
];

const outstandingDues = [
  { id: "DUE-001", member: "Lion Ajith Kumar - Member", label: "Monthly dues - June", dueDate: "10 Jun 2026", amount: 500, status: "Pending", selected: true },
  { id: "DUE-002", member: "Lion Ajith Kumar - Member", label: "Special Collection", purpose: "Club service project contribution", dueDate: "20 Jun 2026", amount: 500, status: "Pending", selected: true },
  { id: "DUE-003", member: "Lion Secretary User", label: "Special Collection", purpose: "Club service project contribution", dueDate: "20 Jun 2026", amount: 500, status: "Pending", selected: false },
  { id: "DUE-004", member: "Lion Treasurer User", label: "Fellowship sponsor ledger entry", dueDate: "15 Jun 2026", amount: 2500, status: "Pending", selected: false },
  { id: "DUE-005", member: "Lion Bijesh", label: "Project share", dueDate: "18 Jun 2026", amount: 500, status: "Overdue", selected: false }
];

const specialCollection = {
  purpose: "Club service project contribution",
  amountPerMember: 500,
  applicableMembers: "All active members",
  applicableCount: 40,
  fundSection: "Service Activity Fund",
  dueDate: "20 Jun 2026",
  notes: "Created for June service project support.",
  status: "Posted to Member Dues",
  collectedAmount: 0
};

const voluntaryContribution = {
  date: "15 Jun 2026",
  type: "Voluntary Member Contribution",
  purpose: "Recreational activity collection box",
  amount: 2350,
  mode: "Cash",
  fundSection: "Fellowship & Member Activity Fund",
  voucher: "RCP-045",
  accountHead: "Voluntary Member Contribution",
  description: "Collection box amount for recreational activity",
  countedBy: "Lion Treasurer User",
  verifiedBy: "Lion Bijesh - President",
  depositStatus: "Cash counted, pending bank deposit",
  notes: "No member dues or receivable is created for this entry."
};

const clubAccountFunds = [
  {
    name: "General Club Fund",
    opening: 50000,
    income: 20000,
    expense: 10000,
    closing: 60000,
    transactions: [
      { date: "01 Jun 2026", voucher: "OB-001", head: "Opening Balance", description: "Opening Balance", income: 50000, expense: 0, mode: "Bank" },
      { date: "05 Jun 2026", voucher: "RCP-011", head: "Monthly Dues", description: "Monthly Dues Collected", income: 20000, expense: 0, mode: "UPI / Bank" },
      { date: "08 Jun 2026", voucher: "EXP-004", head: "Office Expense", description: "Printing & Stationery", income: 0, expense: 2000, mode: "Cash" }
    ]
  },
  {
    name: "Service Activity Fund",
    opening: 20000,
    income: 30000,
    expense: 25000,
    closing: 25000,
    transactions: [
      { date: "03 Jun 2026", voucher: "RCP-021", head: "Project Contribution", description: "Project Contribution Received", income: 30000, expense: 0, mode: "Bank" },
      { date: "10 Jun 2026", voucher: "EXP-012", head: "Service Activity", description: "School Support Activity Expense", income: 0, expense: 25000, mode: "Bank" }
    ]
  },
  {
    name: "Fellowship & Member Activity Fund",
    opening: 5000,
    income: 15000,
    expense: 3000,
    closing: 17000,
    transactions: [
      { date: "04 Jun 2026", voucher: "RCP-031", head: "Family Fellowship Contribution", description: "Monthly Family Fellowship Contribution", income: 12650, expense: 0, mode: "UPI" },
      { date: voluntaryContribution.date, voucher: voluntaryContribution.voucher, head: voluntaryContribution.accountHead, description: voluntaryContribution.description, income: voluntaryContribution.amount, expense: 0, mode: voluntaryContribution.mode },
      { date: "12 Jun 2026", voucher: "EXP-018", head: "Fellowship Expense", description: "Fellowship Food Advance", income: 0, expense: 3000, mode: "Cash" }
    ]
  }
];

const rolePendingTasks: Record<Exclude<Role, "member">, string[]> = {
  president: [
    "Approve 3 member profile updates",
    "Confirm June fellowship sponsors",
    "Review 2 pending activity reports",
    "Check dues summary before PST meeting",
    "Send today's celebration wishes"
  ],
  secretary: [
    "Publish 1 meeting notice",
    "Upload 2 pending documents",
    "Complete school kit activity report",
    "Prepare birthday/anniversary wish list",
    "Review member update reminders"
  ],
  treasurer: [
    "Record 5 pending payments",
    "Generate 3 receipt placeholders",
    "Review fellowship payment status",
    "Check outstanding member report",
    "Update project expense summary"
  ]
};

const pendingMemberUpdates = [
  { member: "Lion Ajith Kumar - Member", change: "Phone number update", submitted: "03 Jun 2026", status: "Pending review" },
  { member: "Lion Secretary User", change: "Anniversary visibility consent", submitted: "02 Jun 2026", status: "Pending approval" },
  { member: "Lion Treasurer User", change: "Blood donor consent update", submitted: "01 Jun 2026", status: "Needs confirmation" }
];

const clubReminders = [
  { title: "PST review due", role: "President", due: "14 Jun 2026", priority: "High", status: "Open" },
  { title: "Close May activity reports", role: "Secretary", due: "07 Jun 2026", priority: "Medium", status: "Pending" },
  { title: "Verify fellowship collection", role: "Treasurer", due: "28 Jun 2026", priority: "High", status: "Open" }
];

const roleLabels: Record<Role, string> = {
  president: "President",
  secretary: "Secretary",
  treasurer: "Treasurer",
  member: "Member"
};

const reminderSections: ReminderSection[] = [
  "Reminder Overview",
  "Dues Reminders",
  "Meeting Reminders",
  "Celebration Reminders",
  "Announcement Reminders",
  "Message Templates",
  "Reminder History",
  "Reminder Settings"
];

const duesReminderRows = [
  { member: "Lion Ajith", amount: 1000, type: "Monthly Dues + Special Collection", channel: "WhatsApp", status: "Pending" },
  { member: "Lion Rajesh", amount: 500, type: "Monthly Dues", channel: "WhatsApp", status: "Ready" }
];

const reminderHistoryRows = [
  { date: "03 Jun 2026", title: "June dues reminder preview", channel: "WhatsApp", status: "Prototype previewed" },
  { date: "02 Jun 2026", title: "Family fellowship reminder", channel: "In-app", status: "Scheduled dummy" },
  { date: "01 Jun 2026", title: "Birthday greeting reminder", channel: "WhatsApp", status: "Marked as sent" }
];

const reportMenuItems: NavItem[] = [
  {
    label: "Member Reports",
    children: [
      { label: "Active Member List", screen: "report-detail", reportSection: "Member Reports", reportDetail: "Active Member List" },
      { label: "Inactive Member List", screen: "report-detail", reportSection: "Member Reports", reportDetail: "Inactive Member List" },
      { label: "Profile Update Pending Report", screen: "report-detail", reportSection: "Member Reports", reportDetail: "Profile Update Pending Report" },
      { label: "Birthday / Anniversary Report", screen: "report-detail", reportSection: "Member Reports", reportDetail: "Birthday / Anniversary Report" }
    ]
  },
  {
    label: "Finance / Ledger Reports",
    children: [
      { label: "Monthly Summary", screen: "report-detail", reportSection: "Finance / Ledger Reports", reportDetail: "Monthly Finance Summary" },
      { label: "Collection Report", screen: "report-detail", reportSection: "Finance / Ledger Reports", reportDetail: "Payment Collection Report" },
      { label: "Outstanding Report", screen: "report-detail", reportSection: "Finance / Ledger Reports", reportDetail: "Member-wise Outstanding" },
      { label: "Fellowship Payment Report", screen: "report-detail", reportSection: "Finance / Ledger Reports", reportDetail: "Fellowship Payment Report" }
    ]
  },
  {
    label: "Fellowship Reports",
    children: [
      { label: "Current Month Sponsorship", screen: "report-detail", reportSection: "Fellowship Reports", reportDetail: "Current Month Sponsorship" },
      { label: "Sponsor Queue History", screen: "report-detail", reportSection: "Fellowship Reports", reportDetail: "Sponsor Queue History" },
      { label: "Closed Month Summary", screen: "report-detail", reportSection: "Fellowship Reports", reportDetail: "Closed Month Summary" }
    ]
  },
  {
    label: "Events & Meetings Reports",
    children: [
      { label: "Upcoming Meetings", screen: "report-detail", reportSection: "Events & Meetings Reports", reportDetail: "Upcoming Meetings" },
      { label: "Past Events Summary", screen: "report-detail", reportSection: "Events & Meetings Reports", reportDetail: "Past Events Summary" },
      { label: "Attendance Placeholder Report", screen: "report-detail", reportSection: "Events & Meetings Reports", reportDetail: "Attendance Placeholder Report" }
    ]
  },
  {
    label: "Club Activity / Service Reports",
    children: [
      { label: "Monthly Activity Report", screen: "report-detail", reportSection: "Club Activity / Service Reports", reportDetail: "Monthly Activity Report" },
      { label: "Yearly Consolidated Activity Report", screen: "report-detail", reportSection: "Club Activity / Service Reports", reportDetail: "Yearly Consolidated Activity Report" },
      { label: "Category-wise Activity Report", screen: "report-detail", reportSection: "Club Activity / Service Reports", reportDetail: "Category-wise Activity Report" }
    ]
  },
  {
    label: "Celebration Reports",
    children: [
      { label: "Monthly Birthday Report", screen: "report-detail", reportSection: "Celebration Reports", reportDetail: "Monthly Birthday Report" },
      { label: "Monthly Anniversary Report", screen: "report-detail", reportSection: "Celebration Reports", reportDetail: "Monthly Anniversary Report" },
      { label: "Celebration Register", screen: "report-detail", reportSection: "Celebration Reports", reportDetail: "Celebration Register" }
    ]
  },
  {
    label: "Blood Donor Consent Report",
    children: [
      { label: "Consented Donor List", screen: "report-detail", reportSection: "Blood Donor Consent Report", reportDetail: "Consented Donor List" },
      { label: "Blood Group Summary", screen: "report-detail", reportSection: "Blood Donor Consent Report", reportDetail: "Blood Group Summary" },
      { label: "Contact Visibility Report", screen: "report-detail", reportSection: "Blood Donor Consent Report", reportDetail: "Contact Visibility Report" }
    ]
  },
  {
    label: "Document Reports",
    children: [
      { label: "Published Document Report", screen: "report-detail", reportSection: "Document Reports", reportDetail: "Published Document Report" },
      { label: "Board-only Document Report", screen: "report-detail", reportSection: "Document Reports", reportDetail: "Board-only Document Report" },
      { label: "Pending Upload Report", screen: "report-detail", reportSection: "Document Reports", reportDetail: "Pending Upload Report" }
    ]
  }
];

const roleUser: Record<Role, Member> = {
  president: members[0],
  secretary: members[1],
  treasurer: members[2],
  member: members[3]
};

function detailItem(parent: string, label: string): NavItem {
  return { label, screen: "nav-detail", detailParent: parent, detailTitle: label };
}

const myMemberAreaItems: NavItem[] = [
  { label: "My Profile", screen: "member-profile" },
  { label: "My Digital Card", screen: "digital-card" },
  { label: "My Dues", screen: "ledger" },
  { label: "My Fellowship", screen: "fellowship" },
  { label: "My Documents", screen: "documents" },
  { label: "My Celebrations", screen: "celebrations" },
  { label: "Blood Donor Consent", screen: "donor-consent" }
];

const peopleMenuItems: NavItem[] = [
  {
    label: "Members",
    children: [
      detailItem("People", "Active Members"),
      detailItem("People", "Inactive Members"),
      detailItem("People", "Profile Update Pending"),
      { label: "Member Import Preview", screen: "member-import-preview" }
    ]
  },
  {
    label: "Board & Committees",
    children: [
      detailItem("People", "PST Profiles"),
      detailItem("People", "President Profile"),
      detailItem("People", "Secretary Profile"),
      detailItem("People", "Treasurer Profile"),
      detailItem("People", "Board Members"),
      detailItem("People", "Committee List"),
      detailItem("People", "Committee Members")
    ]
  },
  {
    label: "Celebrations",
    children: [
      detailItem("People", "Today"),
      detailItem("People", "This Week"),
      detailItem("People", "Birthdays"),
      detailItem("People", "Anniversaries")
    ]
  }
];

const operationsMenuItems: NavItem[] = [
  {
    label: "Events & Meetings",
    children: [
      { label: "Upcoming Events", screen: "events-meetings", eventMeetingSection: "Upcoming Events" },
      { label: "Upcoming Meetings", screen: "events-meetings", eventMeetingSection: "Upcoming Meetings" },
      { label: "Past Events", screen: "events-meetings", eventMeetingSection: "Past Events" },
      { label: "Meeting Notices", screen: "events-meetings", eventMeetingSection: "Meeting Notices" }
    ]
  },
  {
    label: "Announcements",
    children: [
      { label: "Published", screen: "announcement-detail", announcementDetail: "Published" },
      { label: "Draft / Pending", screen: "announcement-detail", announcementDetail: "Draft / Pending" },
      { label: "High Priority", screen: "announcement-detail", announcementDetail: "High Priority" }
    ]
  },
  {
    label: "Documents",
    children: [
      detailItem("Operations", "Club Documents"),
      detailItem("Operations", "Meeting Minutes"),
      detailItem("Operations", "Circulars")
    ]
  },
  {
    label: "Projects / Activities",
    children: [
      detailItem("Operations", "This Month"),
      detailItem("Operations", "Pending Reports"),
      detailItem("Operations", "Completed Activities"),
      detailItem("Operations", "Category-wise Activities")
    ]
  },
  {
    label: "Fellowship",
    children: [
      { label: "Overview", screen: "fellowship", fellowshipTab: "Overview" },
      { label: "Queue", screen: "fellowship", fellowshipTab: "Queue" },
      { label: "Selected Sponsors", screen: "fellowship", fellowshipTab: "Selected Sponsors" },
      { label: "Payments", screen: "fellowship", fellowshipTab: "Payments" },
      { label: "History", screen: "fellowship", fellowshipTab: "History" }
    ]
  }
];

const financeMenuItems: NavItem[] = [
  {
    label: "Club Account Statement",
    children: [
      { label: "Account Overview", screen: "finance-module", financeSection: "Club Account Statement", statementSection: "Account Overview" },
      { label: "Fund-wise Opening Balance", screen: "finance-module", financeSection: "Club Account Statement", statementSection: "Fund-wise Opening Balance" },
      { label: "Fund-wise Summary", screen: "finance-module", financeSection: "Club Account Statement", statementSection: "Fund-wise Summary" },
      { label: "Recent Transactions", screen: "finance-module", financeSection: "Club Account Statement", statementSection: "Recent Transactions" },
      { label: "Central Ledger Structure", screen: "finance-module", financeSection: "Club Account Statement", statementSection: "Central Ledger Structure" },
      { label: "Kuri / Chit Fund - Future Phase", screen: "finance-module", financeSection: "Club Account Statement", statementSection: "Kuri / Chit Fund - Future Phase" }
    ]
  },
  {
    label: "Member Ledger / Dues",
    children: [
      { label: "Member Ledger", screen: "ledger" },
      { label: "Special Collection", screen: "special-collection" },
      detailItem("Finance", "Add Due"),
      detailItem("Finance", "Due Categories")
    ]
  },
  {
    label: "Payments / Receipts",
    children: [
      { label: "Record Member Payment", screen: "record-member-payment" },
      { label: "Record Club Income / Donation", screen: "collection-box" },
      { label: "Receipt List", screen: "receipt-list" },
      { label: "Payment History", screen: "payment-history" }
    ]
  },
  {
    label: "Club Finance Reports",
    children: [
      { label: "Club Finance Reports", screen: "finance-module", financeSection: "Club Finance Reports" },
      { label: "Balance Sheet Summary", screen: "finance-module", financeSection: "Balance Sheet Summary" }
    ]
  },
  {
    label: "Outstanding Dues",
    children: [
      { label: "Member-wise Outstanding", screen: "outstanding-dues" },
      detailItem("Finance", "Due Type-wise Outstanding")
    ]
  },
  {
    label: "Collection Summary",
    children: [
      { label: "Today’s Collection", screen: "collection-summary", collectionDetail: "today" },
      { label: "This Month’s Collection", screen: "collection-summary", collectionDetail: "month" },
      { label: "Cash / Bank / UPI Split", screen: "collection-summary", collectionDetail: "split" }
    ]
  },
  {
    label: "Fellowship Payments",
    children: [
      { label: "Current Month", screen: "fellowship-payments", fellowshipPaymentView: "Current Month" },
      { label: "Pending Sponsors", screen: "fellowship-payments", fellowshipPaymentView: "Pending Sponsors" },
      { label: "Paid Sponsors", screen: "fellowship-payments", fellowshipPaymentView: "Paid Sponsors" }
    ]
  }
];

const navByRole: Record<Role, NavGroup[]> = {
  president: [
    { title: "Direct", direct: true, items: [{ label: "Dashboard", screen: "dashboard" }] },
    { title: "My Member Area", items: myMemberAreaItems },
    { title: "People", items: peopleMenuItems },
    { title: "Operations", items: operationsMenuItems },
    { title: "Finance", items: financeMenuItems },
    { title: "Reports & Tools", items: [
      ...reportMenuItems,
      { label: "Notifications & Reminders", screen: "notifications-reminders" },
      { label: "Authorized Donor Search", screen: "donor-search" }
    ] },
    { title: "System", direct: true, items: [{ label: "Settings / Roles", screen: "settings" }] }
  ],
  secretary: [
    { title: "Direct", direct: true, items: [{ label: "Dashboard", screen: "dashboard" }] },
    { title: "My Member Area", items: myMemberAreaItems },
    { title: "People", items: peopleMenuItems },
    { title: "Operations", items: operationsMenuItems },
    { title: "Finance", items: financeMenuItems },
    { title: "Reports & Tools", items: [
      ...reportMenuItems,
      { label: "Notifications & Reminders", screen: "notifications-reminders" },
      { label: "Authorized Donor Search", screen: "donor-search" }
    ] }
  ],
  treasurer: [
    { title: "Direct", direct: true, items: [{ label: "Dashboard", screen: "dashboard" }] },
    { title: "My Member Area", items: myMemberAreaItems },
    { title: "Finance", items: financeMenuItems },
    { title: "Club Visibility", items: [
      ...peopleMenuItems,
      ...operationsMenuItems.filter((item) => item.label !== "Fellowship")
    ] },
    { title: "Reports & Tools", items: [
      ...reportMenuItems,
      { label: "Notifications & Reminders", screen: "notifications-reminders" }
    ] }
  ],
  member: [
    { title: "Member", direct: true, items: [
      { label: "Home", screen: "dashboard" },
      { label: "My Profile", screen: "member-profile" },
      { label: "My Digital Card", screen: "digital-card" },
      { label: "Member Directory", screen: "members" },
      { label: "PST Profiles", screen: "nav-detail", detailParent: "People", detailTitle: "PST Profiles" },
      { label: "My Dues", screen: "ledger" },
      { label: "My Payment History", screen: "payment-history" },
      { label: "My Fellowship", screen: "fellowship" },
      { label: "Events & Meetings", screen: "events-meetings" },
      { label: "Announcements", screen: "announcements" },
      { label: "Documents", screen: "documents" },
      { label: "Celebrations", screen: "celebrations" },
      { label: "Blood Donor Consent", screen: "donor-consent" }
    ] }
  ]
};

function money(value: number) {
  return `₹${value.toLocaleString("en-IN")}`;
}

const memberQuickAccess = [
  { label: "My Dues", short: "DU", screen: "ledger" as Screen },
  { label: "Payments", short: "PH", screen: "payment-history" as Screen },
  { label: "My Fellowship", short: "FE", screen: "fellowship" as Screen },
  { label: "Digital Card", short: "ID", screen: "digital-card" as Screen },
  { label: "Announcements", short: "AN", screen: "announcements" as Screen },
  { label: "Events", short: "EV", screen: "events-meetings" as Screen },
  { label: "Documents", short: "DO", screen: "documents" as Screen },
  { label: "Blood Donor", short: "BD", screen: "donor-consent" as Screen },
  { label: "Directory", short: "DR", screen: "members" as Screen }
];

const pstMemberAreaCards = [
  { label: "My Profile", value: "Member record", action: "Open", screen: "member-profile" as Screen },
  { label: "My Digital Card", value: "In-app card", action: "View", screen: "digital-card" as Screen },
  { label: "My Dues", value: "Own account", action: "Review", screen: "ledger" as Screen },
  { label: "My Fellowship", value: "Own status", action: "Open", screen: "fellowship" as Screen },
  { label: "My Documents", value: "My access", action: "View", screen: "documents" as Screen },
  { label: "My Celebrations", value: "My wishes", action: "Open", screen: "celebrations" as Screen },
  { label: "Blood Donor Consent", value: "My consent", action: "Update", screen: "donor-consent" as Screen }
];

function parentGroupForScreen(role: Role, screen: Screen) {
  const group = navByRole[role].find((item) => !item.direct && item.items.some((child) => child.screen === screen || child.children?.some((grandChild) => grandChild.screen === screen)));
  return group?.title;
}

function ContextBack({ parent, onBack }: { parent: string; onBack: () => void }) {
  return (
    <div className="contextBack">
      <button className="secondaryButton" onClick={onBack}>← {parent}</button>
    </div>
  );
}

export default function Prototype() {
  const [role, setRole] = useState<Role>("president");
  const [screen, setScreen] = useState<Screen>("login");
  const [selectedSponsors, setSelectedSponsors] = useState<string[]>([
    "Lion Bijesh",
    "Lion Secretary User",
    "Lion Treasurer User"
  ]);
  const [bloodGroup, setBloodGroup] = useState("O+");
  const [fellowshipTab, setFellowshipTab] = useState<"Overview" | "Queue" | "Selected Sponsors" | "Payments" | "History">("Overview");
  const [expandedGroups, setExpandedGroups] = useState<string[]>([]);
  const [collectionDetail, setCollectionDetail] = useState<CollectionDetail>(null);
  const [dashboardDetail, setDashboardDetail] = useState<DashboardDetail | null>(null);
  const [peopleSection, setPeopleSection] = useState<PeopleSection>("Members");
  const [operationsSection, setOperationsSection] = useState<OperationsSection>("Events & Meetings");
  const [eventMeetingSection, setEventMeetingSection] = useState<EventMeetingSection | null>(null);
  const [announcementDetail, setAnnouncementDetail] = useState<AnnouncementDetail>("Published");
  const [financeSection, setFinanceSection] = useState<FinanceSection>("Club Account Statement");
  const [fellowshipPaymentView, setFellowshipPaymentView] = useState<FellowshipPaymentView>("Current Month");
  const [clubStatementSection, setClubStatementSection] = useState<ClubStatementSection>("Account Overview");
  const [reportsSection, setReportsSection] = useState<ReportsSection>("Member Reports");
  const [clubVisibilitySection, setClubVisibilitySection] = useState<ClubVisibilitySection>("Members");
  const [expandedSubItems, setExpandedSubItems] = useState<string[]>([]);
  const [reportDetail, setReportDetail] = useState("Active Member List");
  const [navDetail, setNavDetail] = useState({ parent: "Dashboard", title: "Detail" });
  const activeMember = roleUser[role];
  const totalOutstanding = members.reduce((sum, member) => sum + member.dues, 0);
  const allowedDonors = useMemo(
    () => members.filter((member) => member.donorConsent && member.bloodGroup === bloodGroup),
    [bloodGroup]
  );

  function switchRole(nextRole: Role) {
    setRole(nextRole);
    setScreen("dashboard");
    setExpandedGroups([]);
    setExpandedSubItems([]);
    setCollectionDetail(null);
    setEventMeetingSection(null);
    setDashboardDetail(null);
  }

  function navItemMatches(item: NavItem) {
    if (!item.screen || item.screen !== screen) return false;
    if (item.reportDetail && item.reportDetail !== reportDetail) return false;
    if (item.financeSection && item.financeSection !== financeSection) return false;
    if (item.statementSection && item.statementSection !== clubStatementSection) return false;
    if (item.collectionDetail && item.collectionDetail !== collectionDetail) return false;
    if (item.fellowshipPaymentView && item.fellowshipPaymentView !== fellowshipPaymentView) return false;
    if (item.fellowshipTab && item.fellowshipTab !== fellowshipTab) return false;
    if (item.detailTitle && item.detailTitle !== navDetail.title) return false;
    if (item.eventMeetingSection && item.eventMeetingSection !== eventMeetingSection) return false;
    if (item.announcementDetail && item.announcementDetail !== announcementDetail) return false;
    return true;
  }

  function isItemActive(item: NavItem): boolean {
    return navItemMatches(item) || Boolean(item.children?.some((child) => isItemActive(child)));
  }

  function isActiveGroup(group: NavGroup) {
    return group.items.some((item) => isItemActive(item));
  }

  function isExpanded(group: NavGroup) {
    return expandedGroups.includes(group.title);
  }

  function toggleGroup(group: NavGroup) {
    setExpandedGroups((current) => {
      if (current.includes(group.title)) {
        return current.filter((title) => title !== group.title);
      }
      return [...current.slice(-1), group.title];
    });
  }

  function openChild(item: NavItem, group: NavGroup) {
    if (!item.screen) return;
    if (item.reportSection) setReportsSection(item.reportSection);
    if (item.reportDetail) setReportDetail(item.reportDetail);
    if (item.detailTitle) setNavDetail({ parent: item.detailParent || group.title, title: item.detailTitle });
    if (item.eventMeetingSection) setEventMeetingSection(item.eventMeetingSection);
    if (item.announcementDetail) setAnnouncementDetail(item.announcementDetail);
    if (item.fellowshipTab) setFellowshipTab(item.fellowshipTab);
    if (item.financeSection) setFinanceSection(item.financeSection);
    if (item.statementSection) setClubStatementSection(item.statementSection);
    if (item.collectionDetail) setCollectionDetail(item.collectionDetail);
    if (item.fellowshipPaymentView) setFellowshipPaymentView(item.fellowshipPaymentView);
    setScreen(item.screen);
    if (!item.collectionDetail) setCollectionDetail(null);
    if (item.screen !== "events-meetings" || !item.eventMeetingSection) setEventMeetingSection(null);
    setDashboardDetail(null);
    setExpandedGroups([group.title]);
  }

  function openGrandChild(child: NavItem, group: NavGroup, parentItem: NavItem) {
    const key = `${role}-${group.title}-${parentItem.label}`;
    setExpandedSubItems((current) => current.includes(key) ? current : [...current, key]);
    openChild(child, group);
  }

  function openDirect(item: NavItem) {
    if (!item.screen) return;
    setScreen(item.screen);
    setCollectionDetail(null);
    setEventMeetingSection(null);
    setDashboardDetail(null);
    setExpandedGroups([]);
    setExpandedSubItems([]);
  }

  function toggleSubItem(group: NavGroup, item: NavItem) {
    const key = `${role}-${group.title}-${item.label}`;
    setExpandedGroups([group.title]);
    setExpandedSubItems((current) => current.includes(key) ? current.filter((entry) => entry !== key) : [...current, key]);
  }

  function isSubItemExpanded(group: NavGroup, item: NavItem) {
    return expandedSubItems.includes(`${role}-${group.title}-${item.label}`);
  }

  function openDashboardDetail(detail: DashboardDetail) {
    setDashboardDetail(detail);
    setCollectionDetail(null);
    setScreen("dashboard-detail");
    setExpandedGroups([]);
  }

  const activeParent = parentGroupForScreen(role, screen);

  return (
    <main className="shell">
      <aside className="sidebar" aria-label="Prototype navigation">
        <div className="brand">
          <div className="brandMark">LC</div>
          <div>
            <strong>LC Beenachi Connect</strong>
            <span>Milestone 1A prototype</span>
          </div>
        </div>

        <div className="prototypeSwitcher">
          <span>Developer testing role switcher</span>
          <small>Hidden in production login flow</small>
          <select
            aria-label="Prototype-only role switcher"
            value={role}
            onChange={(event) => switchRole(event.target.value as Role)}
          >
            {Object.entries(roleLabels).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>

        <nav className={role === "member" ? "memberNav" : undefined}>
          {navByRole[role].map((group) => (
            <div className="navGroup" key={`${role}-${group.title}`}>
              {group.direct ? (
                group.items.map((item) => (
                  <button
                    key={`${role}-${item.screen}-${item.label}`}
                    className={screen === item.screen ? "active navButton" : "navButton"}
                    onClick={() => openDirect(item)}
                  >
                    {item.label}
                  </button>
                ))
              ) : (
                <>
                  <button
                    className={isActiveGroup(group) ? "navGroupToggle activeGroup" : "navGroupToggle"}
                    onClick={() => toggleGroup(group)}
                    aria-expanded={isExpanded(group)}
                  >
                    <span>{group.title}</span>
                    <span>{isExpanded(group) ? "▾" : "▸"}</span>
                  </button>
                  {isExpanded(group) && (
                    <div className="navChildren">
                      {group.items.map((item) => (
                        <div className="navChildGroup" key={`${role}-${group.title}-${item.label}`}>
                          {item.children ? (
                            <>
                              <button
                                className={isItemActive(item) ? "navButton childNavButton activeParentButton" : "navButton childNavButton"}
                                onClick={() => toggleSubItem(group, item)}
                                aria-expanded={isSubItemExpanded(group, item)}
                              >
                                <span>{item.label}</span>
                                <span>{isSubItemExpanded(group, item) ? "▾" : "▸"}</span>
                              </button>
                              {isSubItemExpanded(group, item) && (
                                <div className="navGrandChildren">
                                  {item.children.map((child) => (
                                    <button
                                      key={`${role}-${group.title}-${item.label}-${child.label}`}
                                      className={navItemMatches(child) ? "active navButton grandChildNavButton" : "navButton grandChildNavButton"}
                                      onClick={() => openGrandChild(child, group, item)}
                                    >
                                      {child.label}
                                    </button>
                                  ))}
                                </div>
                              )}
                            </>
                          ) : (
                            <button
                              className={navItemMatches(item) ? "active navButton childNavButton" : "navButton childNavButton"}
                              onClick={() => openChild(item, group)}
                            >
                              {item.label}
                            </button>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </>
              )}
            </div>
          ))}
        </nav>
      </aside>

      <section className="workspace">
        <Header role={roleLabels[role]} user={activeMember.dashboardName} screen={screen} reportDetail={reportDetail} navTitle={navDetailTitle(navDetail.parent, navDetail.title)} />
        {activeParent && screen !== "dashboard" && screen !== "login" && screen !== "nav-detail" && screen !== "report-detail" && screen !== "announcement-detail" && (
          <ContextBack
            parent={activeParent}
            onBack={() => {
              setScreen(parentScreen(activeParent));
              setCollectionDetail(null);
              setEventMeetingSection(null);
              setExpandedGroups([activeParent]);
            }}
          />
        )}
        {screen === "login" && <LoginScreen switchRole={switchRole} />}
        {screen === "dashboard" && (
          <Dashboard
            role={role}
            member={activeMember}
            setScreen={setScreen}
            openDashboardDetail={openDashboardDetail}
            totalOutstanding={totalOutstanding}
          />
        )}
        {screen === "dashboard-detail" && dashboardDetail && (
          <DashboardDetailScreen
            detail={dashboardDetail}
            role={role}
            member={activeMember}
            setScreen={setScreen}
            openDashboardDetail={openDashboardDetail}
          />
        )}
        {screen === "people-module" && (
          <PeopleModule section={peopleSection} setSection={setPeopleSection} role={role} setScreen={setScreen} />
        )}
        {screen === "operations-module" && (
          <OperationsModule
            section={operationsSection}
            setSection={setOperationsSection}
            role={role}
            member={activeMember}
            setScreen={setScreen}
            tab={fellowshipTab}
            setTab={setFellowshipTab}
            selectedSponsors={selectedSponsors}
          />
        )}
        {screen === "finance-module" && (
          <FinanceModule
            section={financeSection}
            statementSection={clubStatementSection}
            role={role}
            member={activeMember}
            setScreen={setScreen}
            collectionDetail={collectionDetail}
            setCollectionDetail={setCollectionDetail}
            fellowshipPaymentView={fellowshipPaymentView}
          />
        )}
        {screen === "reports-tools-module" && (
          <ReportsToolsModule section={reportsSection} setSection={setReportsSection} role={role} setScreen={setScreen} />
        )}
        {screen === "notifications-reminders" && (
          <NotificationsReminders role={role} setScreen={setScreen} />
        )}
        {screen === "report-detail" && (
          <ReportDetailScreen section={reportsSection} detail={reportDetail} setScreen={setScreen} />
        )}
        {screen === "nav-detail" && (
          <NavDetailScreen parent={navDetail.parent} title={navDetail.title} role={role} setScreen={setScreen} />
        )}
        {screen === "announcement-detail" && (
          <AnnouncementDetailScreen detail={announcementDetail} setScreen={setScreen} />
        )}
        {screen === "club-visibility-module" && (
          <ClubVisibilityModule section={clubVisibilitySection} setSection={setClubVisibilitySection} role={role} member={activeMember} selectedSponsors={selectedSponsors} setScreen={setScreen} />
        )}
        {screen === "members" && <MembersList setScreen={setScreen} role={role} />}
        {screen === "committees" && <Committees />}
        {screen === "member-profile" && <MemberProfile member={activeMember} role={role} />}
        {screen === "digital-card" && <DigitalCard member={activeMember} />}
        {screen === "fellowship" && <FellowshipDashboard role={role} member={activeMember} setScreen={setScreen} tab={fellowshipTab} setTab={setFellowshipTab} selectedSponsors={selectedSponsors} />}
        {screen === "fellowship-queue" && (
          <FellowshipQueue selectedSponsors={selectedSponsors} setSelectedSponsors={setSelectedSponsors} setScreen={setScreen} />
        )}
        {screen === "confirm-sponsors" && <ConfirmSponsors selectedSponsors={selectedSponsors} setScreen={setScreen} />}
        {screen === "ledger" && <Ledger role={role} member={activeMember} setScreen={setScreen} />}
        {screen === "member-dues-detail" && <MemberDuesDetail member={activeMember} setScreen={setScreen} />}
        {screen === "special-collection" && <SpecialCollection setScreen={setScreen} />}
        {screen === "record-member-payment" && <RecordMemberPayment role={role} />}
        {screen === "collection-box" && <CollectionBoxReceipt role={role} />}
        {screen === "receipt-list" && <ReceiptList role={role} />}
        {screen === "payment-history" && <PaymentHistory role={role} />}
        {screen === "member-import-preview" && <MemberImportPreview role={role} />}
        {screen === "outstanding-dues" && <OutstandingDues setScreen={setScreen} />}
        {screen === "collection-summary" && <CollectionSummary detail={collectionDetail} setDetail={setCollectionDetail} />}
        {screen === "fellowship-payments" && <FellowshipPayments view={fellowshipPaymentView} setScreen={setScreen} />}
        {screen === "events-meetings" && <EventsMeetings role={role} member={activeMember} selectedSponsors={selectedSponsors} setScreen={setScreen} selectedSection={eventMeetingSection} />}
        {screen === "announcements" && <Announcements role={role} member={activeMember} selectedSponsors={selectedSponsors} />}
        {screen === "documents" && <Documents role={role} />}
        {screen === "projects" && <ProjectsActivities setScreen={setScreen} />}
        {screen === "celebrations" && <Celebrations role={role} setScreen={setScreen} />}
        {screen === "donor-consent" && <DonorConsent member={activeMember} />}
        {screen === "donor-search" && <DonorSearch bloodGroup={bloodGroup} setBloodGroup={setBloodGroup} allowedDonors={allowedDonors} />}
        {screen === "reports" && <Reports />}
        {screen === "settings" && <Settings setScreen={setScreen} />}
      </section>
    </main>
  );
}

function Header({ role, user, screen, reportDetail, navTitle }: { role: string; user: string; screen: Screen; reportDetail: string; navTitle: string }) {
  const title = role === "Member" && screen === "ledger" ? "My Dues" : screen === "report-detail" ? reportDetail : screen === "nav-detail" ? navTitle : screenTitle(screen);
  return (
    <header className="topbar">
      <div>
        <p className="eyebrow">Clickable prototype - dummy data only</p>
        <h1>{title}</h1>
      </div>
      <div className="statusPill">{user} - {role}</div>
    </header>
  );
}

function screenTitle(screen: Screen) {
  const names: Record<Screen, string> = {
    login: "Prototype Entry",
    dashboard: "Dashboard",
    "people-module": "People",
    "operations-module": "Operations",
    "finance-module": "Finance",
    "reports-tools-module": "Reports & Tools",
    "notifications-reminders": "Notifications & Reminders",
    "club-visibility-module": "Club Visibility",
    "report-detail": "Report Detail",
    "nav-detail": "Detail",
    members: "Members",
    committees: "Board & Committees",
    "member-profile": "My Profile",
    "digital-card": "My Digital Card",
    fellowship: "Fellowship Sponsorship",
    "fellowship-queue": "Fellowship Queue",
    "confirm-sponsors": "Confirm Sponsor Group",
    ledger: "Member Ledger / Dues",
    "member-dues-detail": "Current Dues Details",
    "special-collection": "Special Collection",
    "record-member-payment": "Record Member Payment",
    "collection-box": "Record Club Income / Donation",
    "receipt-list": "Receipt List",
    "payment-history": "Transaction History",
    "member-import-preview": "Member Import Preview",
    "outstanding-dues": "Outstanding Dues",
    "collection-summary": "Collection Summary",
    "fellowship-payments": "Fellowship Payments",
    "events-meetings": "Events & Meetings",
    announcements: "Announcements",
    "announcement-detail": "Announcements",
    documents: "Documents",
    projects: "Projects / Activities",
    celebrations: "Celebrations",
    "donor-consent": "Blood Donor Consent",
    "donor-search": "Authorized Donor Search",
    reports: "Reports",
    settings: "Settings / Roles",
    "dashboard-detail": "Dashboard Detail"
  };
  return names[screen];
}

function LoginScreen({ switchRole }: { switchRole: (role: Role) => void }) {
  return (
    <section className="loginGrid">
      <div className="loginPanel">
        <p className="eyebrow">LC Beenachi Connect</p>
        <h2>Mobile-first club management PWA</h2>
        <div className="formGrid">
          <label>
            Email or mobile
            <input value="prototype@lcbeenachi.local" readOnly />
          </label>
          <label>
            Password or OTP
            <input value="prototype-only" readOnly type="password" />
          </label>
        </div>
        <div className="buttonRow">
          <button onClick={() => switchRole("president")}>Enter as President</button>
          <button onClick={() => switchRole("member")} className="secondaryButton">Enter as Member</button>
        </div>
      </div>
      <div className="loginRoles">
        {Object.entries(roleLabels).map(([key, label]) => (
          <button key={key} onClick={() => switchRole(key as Role)}>
            {label}
            <span>Open prototype role flow</span>
          </button>
        ))}
      </div>
    </section>
  );
}

function ModuleHeader<T extends string>({
  title,
  label,
  value,
  options,
  onChange,
  setScreen
}: {
  title: string;
  label: string;
  value: T;
  options: T[];
  onChange: (value: T) => void;
  setScreen?: (screen: Screen) => void;
}) {
  return (
    <div className="compactPanel moduleHeader">
      <Toolbar title={title} actionLabel="Choose one section" />
      <div className="buttonRow">
        {setScreen && <button onClick={() => setScreen("dashboard")}>Back to Dashboard</button>}
        {setScreen && <button className="secondaryButton" onClick={() => setScreen("dashboard")}>Back to Menu</button>}
      </div>
      <label className="moduleSelect">
        {label}
        <select value={value} onChange={(event) => onChange(event.target.value as T)}>
          {options.map((option) => <option key={option}>{option}</option>)}
        </select>
      </label>
    </div>
  );
}

function PeopleModule({
  section,
  setSection,
  role,
  setScreen
}: {
  section: PeopleSection;
  setSection: (section: PeopleSection) => void;
  role: Role;
  setScreen: (screen: Screen) => void;
}) {
  const options: PeopleSection[] = ["Members", "Board & Committees", "Celebrations"];
  return (
    <section className="stack">
      <ModuleHeader title="People" label="Choose people section" value={section} options={options} onChange={setSection} setScreen={setScreen} />
      {section === "Members" && <MembersList setScreen={setScreen} role={role} />}
      {section === "Board & Committees" && <Committees />}
      {section === "Celebrations" && <Celebrations role={role} setScreen={setScreen} />}
    </section>
  );
}

function OperationsModule({
  section,
  setSection,
  role,
  member,
  setScreen,
  tab,
  setTab,
  selectedSponsors
}: {
  section: OperationsSection;
  setSection: (section: OperationsSection) => void;
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  tab: "Overview" | "Queue" | "Selected Sponsors" | "Payments" | "History";
  setTab: (tab: "Overview" | "Queue" | "Selected Sponsors" | "Payments" | "History") => void;
  selectedSponsors: string[];
}) {
  const options: OperationsSection[] = ["Events & Meetings", "Announcements", "Documents", "Projects / Activities", "Fellowship"];
  return (
    <section className="stack">
      <ModuleHeader title="Operations" label="Choose operations section" value={section} options={options} onChange={setSection} setScreen={setScreen} />
      {section === "Events & Meetings" && <EventsMeetings role={role} member={member} selectedSponsors={selectedSponsors} setScreen={setScreen} />}
      {section === "Announcements" && <Announcements role={role} member={member} selectedSponsors={selectedSponsors} />}
      {section === "Documents" && <Documents role={role} />}
      {section === "Projects / Activities" && <ProjectsActivities setScreen={setScreen} />}
      {section === "Fellowship" && <FellowshipDashboard role={role} member={member} setScreen={setScreen} tab={tab} setTab={setTab} selectedSponsors={selectedSponsors} />}
    </section>
  );
}

function FinanceModule({
  section,
  statementSection,
  role,
  member,
  setScreen,
  collectionDetail,
  setCollectionDetail,
  fellowshipPaymentView
}: {
  section: FinanceSection;
  statementSection: ClubStatementSection;
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  collectionDetail: CollectionDetail;
  setCollectionDetail: (detail: CollectionDetail) => void;
  fellowshipPaymentView: FellowshipPaymentView;
}) {
  return (
    <section className="stack">
      {section === "Club Account Statement" && <ClubAccountStatement section={statementSection} />}
      {section === "Member Ledger / Dues" && <Ledger role={role} member={member} setScreen={setScreen} />}
      {section === "Payments / Receipts" && <PaymentsReceiptsHub setScreen={setScreen} />}
      {section === "Club Finance Reports" && <ClubFinanceReportsPanel role={role} displayMode="reports" />}
      {section === "Balance Sheet Summary" && <ClubFinanceReportsPanel role={role} displayMode="balance-sheet" />}
      {section === "Outstanding Dues" && <OutstandingDues setScreen={setScreen} />}
      {section === "Collection Summary" && <CollectionSummary detail={collectionDetail} setDetail={setCollectionDetail} />}
      {section === "Fellowship Payments" && <FellowshipPayments view={fellowshipPaymentView} setScreen={setScreen} />}
    </section>
  );
}

function ClubFinanceReportsPanel({
  role,
  displayMode
}: {
  role: Role;
  displayMode: "reports" | "balance-sheet";
}) {
  const canViewClubFinanceReports = role === "president" || role === "secretary" || role === "treasurer";
  const [status, setStatus] = useState<ClubFinanceReportStatus>(canViewClubFinanceReports ? "loading" : "forbidden");
  const [report, setReport] = useState<ClubFinanceReports | null>(null);
  const [balanceSheet, setBalanceSheet] = useState<ClubBalanceSheet | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function loadClubFinanceReports() {
      if (!canViewClubFinanceReports) {
        setStatus("forbidden");
        setReport(null);
        setBalanceSheet(null);
        return;
      }

      setStatus("loading");
      setReport(null);
      setBalanceSheet(null);

      try {
        const login = LOCAL_PAYMENT_RECORDING_LOGINS[role as Exclude<Role, "member">];

        const loginResponse = await fetch(API_BASE_URL + "/auth/login", {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json"
          },
          body: JSON.stringify(login)
        });

        if (loginResponse.status === 401) {
          throw new Error("unauthenticated");
        }

        if (!loginResponse.ok) {
          throw new Error("error");
        }

        const loginPayload = await loginResponse.json();
        const token = loginPayload.token as string | undefined;

        if (!token) {
          throw new Error("unauthenticated");
        }

        const meResponse = await fetch(API_BASE_URL + "/auth/me", {
          headers: {
            Accept: "application/json",
            Authorization: "Bearer " + token
          }
        });

        if (meResponse.status === 401) {
          throw new Error("unauthenticated");
        }

        if (!meResponse.ok) {
          throw new Error("error");
        }

        const mePayload = await meResponse.json();
        const memberships = mePayload.club_memberships as BackendClubMembership[] | undefined;
        const loadedClubId = memberships?.[0]?.club?.id;

        if (typeof loadedClubId !== "number") {
          throw new Error("missing-club");
        }

        const [reportResponse, balanceSheetResponse] = await Promise.all([
          fetch(API_BASE_URL + "/clubs/" + loadedClubId + "/finance/reports", {
            headers: {
              Accept: "application/json",
              Authorization: "Bearer " + token
            }
          }),
          fetch(API_BASE_URL + "/clubs/" + loadedClubId + "/finance/balance-sheet", {
            headers: {
              Accept: "application/json",
              Authorization: "Bearer " + token
            }
          })
        ]);

        if (reportResponse.status === 401 || balanceSheetResponse.status === 401) {
          throw new Error("unauthenticated");
        }

        if (reportResponse.status === 403 || balanceSheetResponse.status === 403) {
          throw new Error("forbidden");
        }

        if (!reportResponse.ok || !balanceSheetResponse.ok) {
          throw new Error("error");
        }

        const reportPayload = await reportResponse.json();
        const balanceSheetPayload = await balanceSheetResponse.json();
        const loadedReport = reportPayload.finance_reports as ClubFinanceReports | undefined;
        const loadedBalanceSheet = balanceSheetPayload.balance_sheet as ClubBalanceSheet | undefined;

        if (!cancelled) {
          setReport(loadedReport ?? null);
          setBalanceSheet(loadedBalanceSheet ?? null);
          setStatus(hasClubFinanceReportValues(loadedReport ?? null) || hasClubBalanceSheetValues(loadedBalanceSheet ?? null) ? "ready" : "empty");
        }
      } catch (error) {
        if (!cancelled) {
          setReport(null);
          setBalanceSheet(null);

          if (error instanceof Error && error.message === "unauthenticated") {
            setStatus("unauthenticated");
          } else if (error instanceof Error && error.message === "forbidden") {
            setStatus("forbidden");
          } else if (error instanceof Error && error.message === "missing-club") {
            setStatus("missing-club");
          } else {
            setStatus("error");
          }
        }
      }
    }

    loadClubFinanceReports();

    return () => {
      cancelled = true;
    };
  }, [canViewClubFinanceReports, role]);

  const title = displayMode === "reports" ? "Club Finance Reports" : "Balance Sheet Summary";

  if (!canViewClubFinanceReports) {
    return (
      <section className="stack">
        <Toolbar title={title} actionLabel="Restricted" />
        <div className="notice">Club-level finance reports and balance sheet are visible only to President, Secretary, and Treasurer roles.</div>
        <div className="notice">Members continue to see only member-safe finance information.</div>
      </section>
    );
  }

  return (
    <section className="stack">
      <Toolbar title={title} actionLabel="Read-only backend reports" />
      <div className="notice">This display uses only the existing club finance reports and balance sheet APIs. Backend values remain the source of truth.</div>

      {status === "loading" && <div className="notice memberHelperText">Loading club finance report context...</div>}
      {status === "missing-club" && <div className="notice">No active club context is available for this finance report.</div>}
      {status === "unauthenticated" && <div className="notice">Please sign in again to view club finance reports.</div>}
      {status === "forbidden" && <div className="notice">This role is not authorized to view club-level finance reports.</div>}
      {status === "error" && <div className="notice">Club finance reports are unavailable right now. Please try again later.</div>}
      {status === "empty" && <div className="notice">No finance report values are available yet.</div>}

      {status === "ready" && displayMode === "reports" && <ClubFinanceReportDisplay report={report} />}
      {status === "ready" && displayMode === "balance-sheet" && <ClubBalanceSheetDisplay balanceSheet={balanceSheet} />}
    </section>
  );
}

function ClubFinanceReportDisplay({ report }: { report: ClubFinanceReports | null }) {
  const overview = report?.club_finance_overview;
  const outstanding = report?.outstanding_dues_summary;
  const collection = report?.payment_collection_summary;
  const receipts = report?.receipt_summary;
  const members = report?.member_wise_dues_summary ?? [];

  if (!report) {
    return <div className="notice">Club finance report response is empty.</div>;
  }

  return (
    <>
      <section className="compactPanel">
        <Toolbar title={financeDisplayValue(report.club_name) + " Reports"} actionLabel="Existing API response" />
        <div className="metricsGrid">
          <Metric label="Total Dues" value={financeDisplayAmount(overview?.total_debit_amount)} />
          <Metric label="Amount Collected" value={financeDisplayAmount(overview?.total_credit_amount)} />
          <Metric label="Current Dues" value={financeDisplayAmount(overview?.current_dues_amount)} />
          <Metric label="Current Dues Label" value={financeDisplayValue(overview?.current_dues_label)} />
        </div>
      </section>

      <section className="compactPanel">
        <Toolbar title="Outstanding Dues Summary" actionLabel="Backend calculated" />
        <div className="metricsGrid">
          <Metric label="Members with Dues" value={financeDisplayValue(outstanding?.members_with_dues_count)} />
          <Metric label="Total Outstanding" value={financeDisplayAmount(outstanding?.total_outstanding_amount)} />
          <Metric label="Posted Payment Total" value={financeDisplayAmount(collection?.posted_payment_total)} />
          <Metric label="Posted Payment Count" value={financeDisplayValue(collection?.posted_payment_count ?? overview?.posted_payment_count)} />
          <Metric label="Issued Receipt Count" value={financeDisplayValue(receipts?.issued_receipt_count ?? overview?.issued_receipt_count)} />
        </div>
      </section>

      <section className="compactPanel">
        <Toolbar title="Member-wise Dues Summary" actionLabel={`${members.length} rows`} />
        {members.length === 0 && <div className="memberEmptyState">No member-wise dues rows are available.</div>}
        {members.length > 0 && (
          <div className="financeCardList">
            {members.map((member, index) => (
              <article className="financeStackCard" key={`${member.member_id ?? "member"}-${index}`}>
                <h3>{financeDisplayValue(member.member_name)}</h3>
                <span>Member Code: {financeDisplayValue(member.member_code)}</span>
                <span>Total Dues: {financeDisplayAmount(member.total_debit_amount)}</span>
                <span>Amount Collected: {financeDisplayAmount(member.total_credit_amount)}</span>
                <span>Current Dues: {financeDisplayAmount(member.current_dues_amount)}</span>
                <span>Status: {financeDisplayValue(member.current_dues_label)}</span>
              </article>
            ))}
          </div>
        )}
        <div className="notice">Generated at: {financeDisplayValue(report.generated_at)}</div>
      </section>
    </>
  );
}

function ClubBalanceSheetDisplay({ balanceSheet }: { balanceSheet: ClubBalanceSheet | null }) {
  if (!balanceSheet) {
    return <div className="notice">Balance sheet response is empty.</div>;
  }

  return (
    <>
      <section className="compactPanel">
        <Toolbar title={financeDisplayValue(balanceSheet.club_name) + " Balance Sheet Summary"} actionLabel="Existing API response" />
        <div className="metricsGrid">
          <Metric label="Member Receivables" value={financeDisplayAmount(balanceSheet.assets?.member_receivables)} />
          <Metric label="Cash Collected" value={financeDisplayAmount(balanceSheet.assets?.cash_collected)} />
          <Metric label="Member Advances" value={financeDisplayAmount(balanceSheet.liabilities?.member_advances)} />
          <Metric label="Net Position" value={financeDisplayAmount(balanceSheet.net_position?.amount)} />
          <Metric label="Net Position Label" value={financeDisplayValue(balanceSheet.net_position?.label)} />
        </div>
      </section>
      <section className="compactPanel">
        <Toolbar title="Balance Sheet Source" actionLabel="Read-only" />
        <div className="fieldGrid">
          <Field label="Source" value={financeDisplayValue(balanceSheet.source)} />
          <Field label="Generated At" value={financeDisplayValue(balanceSheet.generated_at)} />
        </div>
      </section>
    </>
  );
}

function ClubAccountStatement({ section }: { section: ClubStatementSection }) {
  const opening = clubAccountFunds.reduce((sum, fund) => sum + fund.opening, 0);
  const income = clubAccountFunds.reduce((sum, fund) => sum + fund.income, 0);
  const expense = clubAccountFunds.reduce((sum, fund) => sum + fund.expense, 0);
  const closing = clubAccountFunds.reduce((sum, fund) => sum + fund.closing, 0);
  const openingBreakupTotal = clubAccountFunds.reduce((sum, fund) => sum + fund.opening, 0);
  const closingBreakupTotal = clubAccountFunds.reduce((sum, fund) => sum + fund.closing, 0);

  return (
    <section className="stack">
      <section className="compactPanel">
        <Toolbar title="Club Account Statement" actionLabel={section} />

        {section === "Account Overview" && (
          <div className="statementSectionPanel">
            <h3>Main Summary</h3>
            <div className="metricsGrid">
              <Metric label="Opening Balance" value={money(opening)} />
              <Metric label="Total Income" value={money(income)} />
              <Metric label="Total Expense" value={money(expense)} />
              <Metric label="Closing Balance" value={money(closing)} />
            </div>
            <div className="notice">
              One central Club Account Ledger is used. Fund sections are used only for classification and reporting.
            </div>
          </div>
        )}

        {section === "Fund-wise Opening Balance" && (
          <div className="statementSectionPanel">
            <div className="fundOpeningGrid">
              {clubAccountFunds.map((fund) => (
                <article className="fundOpeningCard" key={`${fund.name}-opening`}>
                  <h3>{fund.name}</h3>
                  <span>Opening Balance: {money(fund.opening)}</span>
                </article>
              ))}
              <article className="fundOpeningCard totalFundCard">
                <h3>Total Opening Balance</h3>
                <span>{money(openingBreakupTotal)}</span>
                <small>Must equal main Opening Balance</small>
              </article>
            </div>
          </div>
        )}

        {section === "Fund-wise Summary" && (
          <div className="statementSectionPanel">
            <div className="fundSummaryGrid">
              {clubAccountFunds.map((fund) => (
                <article className="fundSummaryCard" key={fund.name}>
                  <h3>{fund.name}</h3>
                  <div className="fundMetricGrid">
                    <Metric label="Opening" value={money(fund.opening)} />
                    <Metric label="Income" value={money(fund.income)} />
                    <Metric label="Expense" value={money(fund.expense)} />
                    <Metric label="Closing" value={money(fund.closing)} />
                  </div>
                </article>
              ))}
              <div className="notice">Fund-wise closing total: {money(closingBreakupTotal)}. This must equal the main Closing Balance.</div>
            </div>
          </div>
        )}

        {section === "Recent Transactions" && (
          <div className="statementSectionPanel">
            {clubAccountFunds.map((fund) => (
              <section className="fundTransactionGroup" key={`${fund.name}-transactions`}>
                <Toolbar title={fund.name} actionLabel="Recent Transactions" />
                <div className="transactionCardList">
                  {fund.transactions.map((transaction) => (
                    <article className="transactionCard" key={`${fund.name}-${transaction.voucher}`}>
                      <h3>{transaction.date}</h3>
                      <span>Voucher No.: {transaction.voucher}</span>
                      <span>Account Head: {transaction.head}</span>
                      <span>Description: {transaction.description}</span>
                      <span>Amount: {money(transaction.income || transaction.expense)}</span>
                      <span>Mode: {transaction.mode}</span>
                    </article>
                  ))}
                </div>
              </section>
            ))}
          </div>
        )}

        {section === "Central Ledger Structure" && (
          <div className="statementSectionPanel">
            <div className="notice">Future ledger fields: Date, Voucher No., Fund Section, Account Head, Description, Income, Expense, Payment Mode, Cash/Bank, Reference, Created By.</div>
          </div>
        )}

        {section === "Kuri / Chit Fund - Future Phase" && (
          <div className="statementSectionPanel">
            <div className="notice">
              Future Module: Member Kuri / Rotating Support Fund. Status: Deferred to Milestone 2 / V2. Accounting treatment will be handled through separate ledger heads after backend, CA, and legal review.
            </div>
            <div className="metricsGrid">
              <Metric label="Milestone 1A status" value="Deferred" />
              <Metric label="Real operations" value="Not active" />
              <Metric label="Accounting mode" value="Separate ledger heads" />
              <Metric label="Next design phase" value="Milestone 2 / V2" />
            </div>
            <section className="compactPanel">
              <Toolbar title="Planned Accounting Treatment" actionLabel="Roadmap note only" />
              <div className="tableLike">
                {[
                  ["Member kuri collections", "Kuri Member Pool Liability"],
                  ["Kuri payout to winning member", "Liability settlement"],
                  ["5% retained by club", "Kuri Administration Fee Income"],
                  ["Member dues deducted", "Member dues recovery / member ledger credit"],
                  ["Net paid to member", "Bank / Cash payout"]
                ].map(([particular, treatment]) => (
                  <div className="staticRow fundOpeningRow" key={particular}>
                    <strong>{particular}</strong>
                    <span>{treatment}</span>
                    <span className="tag">Future</span>
                  </div>
                ))}
              </div>
            </section>
            <section className="compactPanel">
              <Toolbar title="Recommended Ledger Heads" actionLabel="Future backend" />
              <div className="permissionGrid">
                {[
                  "Kuri Member Pool Liability",
                  "Kuri Administration Fee Income",
                  "Member Dues Receivable",
                  "Bank / Cash",
                  "Kuri Payout Payable"
                ].map((head) => (
                  <div className="permissionItem" key={head}>
                    <strong>{head}</strong>
                    <span>Future kuri ledger head. Not posted in Milestone 1A.</span>
                  </div>
                ))}
              </div>
            </section>
            <section className="compactPanel">
              <Toolbar title="Example Settlement View" actionLabel="Dummy accounting example" />
              <div className="summaryList">
                <Field label="Gross kuri amount" value={money(50000)} />
                <Field label="Less: Kuri Administration Fee @ 5%" value={money(2500)} />
                <Field label="Less: Member pending dues deducted" value={money(1000)} />
                <Field label="Net paid to winning member" value={money(46500)} />
              </div>
              <div className="notice">
                Do not treat the full kuri amount as club income. Only the retained administration fee is planned as club income.
              </div>
            </section>
            <section className="compactPanel">
              <Toolbar title="Milestone 2 Planning" actionLabel="Backend design later" />
              <div className="flowStrip">
                {[
                  "Kuri Groups",
                  "Kuri Members",
                  "Kuri Installments",
                  "Kuri Draws / Selections",
                  "Kuri Payouts",
                  "Kuri Payout Deductions",
                  "Kuri Ledger Entries",
                  "Member + Club Ledgers"
                ].map((item) => <span key={item}>{item}</span>)}
              </div>
              <div className="notice">
                Member dues deducted from a kuri payout should clear member dues as a ledger credit. It should not be recorded again as fresh income.
              </div>
            </section>
          </div>
        )}
      </section>
    </section>
  );
}

function ReportsToolsModule({
  section,
  setSection,
  role,
  setScreen
}: {
  section: ReportsSection;
  setSection: (section: ReportsSection) => void;
  role: Role;
  setScreen: (screen: Screen) => void;
}) {
  const options: ReportsSection[] = [
    "Member Reports",
    "Finance / Ledger Reports",
    "Fellowship Reports",
    "Events & Meetings Reports",
    "Club Activity / Service Reports",
    "Celebration Reports",
    "Blood Donor Consent Report",
    "Document Reports"
  ];
  return (
    <section className="stack">
      <ModuleHeader title="Reports & Tools" label="Select report category" value={section} options={options} onChange={setSection} setScreen={setScreen} />
      <div className="actionGrid">
        {role !== "member" && (
          <Action
            title="Notifications & Reminders"
            body="Prototype-only dues, meeting, celebration and announcement reminder control screen."
            onClick={() => setScreen("notifications-reminders")}
          />
        )}
      </div>
      <ReportCategory section={section} />
    </section>
  );
}

function NotificationsReminders({ role, setScreen }: { role: Role; setScreen: (screen: Screen) => void }) {
  const [section, setSection] = useState<ReminderSection>("Reminder Overview");
  const defaultPreviewTitle = role === "secretary" ? "Meeting reminder preview" : "Dues reminder preview";
  const [previewTitle, setPreviewTitle] = useState(defaultPreviewTitle);
  const [prototypeStatus, setPrototypeStatus] = useState("No real reminder has been sent.");
  const allowedSections = reminderSections.filter((item) => {
    if (role === "president") return true;
    if (role === "secretary") return !["Dues Reminders", "Reminder Settings"].includes(item);
    if (role === "treasurer") return ["Reminder Overview", "Dues Reminders", "Message Templates", "Reminder History"].includes(item);
    return false;
  });

  function openPreview(title: string, status = "Dummy preview opened.") {
    setPreviewTitle(title);
    setPrototypeStatus(status);
  }

  if (role === "member") {
    return (
      <section className="stack">
        <Toolbar title="Notifications & Reminders" actionLabel="No control access" />
        <div className="notice">Members only receive reminders. They do not have access to this control screen.</div>
        <button className="secondaryButton" onClick={() => setScreen("dashboard")}>Back to Home</button>
      </section>
    );
  }

  return (
    <section className="stack">
      <Toolbar title="Notifications & Reminders" actionLabel="Milestone 1A prototype only" />
      <div className="notice">
        Prototype only. Real WhatsApp/SMS/in-app automation will be implemented in backend phase.
      </div>

      <div className="reminderTabs">
        {allowedSections.map((item) => (
          <button
            className={section === item ? "filterButton activeFilter" : "filterButton"}
            key={item}
            onClick={() => setSection(item)}
          >
            {item}
          </button>
        ))}
      </div>

      {section === "Reminder Overview" && (
        <section className="stack">
          <div className="metricsGrid">
            <button className="clickableMetric reminderOverviewCard" onClick={() => setSection("Dues Reminders")} disabled={role === "secretary"}>
              <span>Pending Due Reminders</span>
              <strong>12</strong>
              <small>{role === "secretary" ? "Treasurer access required" : "Open dues reminders"}</small>
            </button>
            <button className="clickableMetric reminderOverviewCard" onClick={() => setSection("Meeting Reminders")} disabled={role === "treasurer"}>
              <span>Meeting Reminders Scheduled</span>
              <strong>2</strong>
              <small>{role === "treasurer" ? "Secretary access" : "Open meeting reminders"}</small>
            </button>
            <button className="clickableMetric reminderOverviewCard" onClick={() => setSection("Celebration Reminders")} disabled={role === "treasurer"}>
              <span>Celebrations Today</span>
              <strong>3</strong>
              <small>{role === "treasurer" ? "Secretary access" : "Open celebrations"}</small>
            </button>
            <button className="clickableMetric reminderOverviewCard" onClick={() => setSection("Announcement Reminders")} disabled={role === "treasurer"}>
              <span>Announcements Pending</span>
              <strong>1</strong>
              <small>{role === "treasurer" ? "Secretary access" : "Open announcements"}</small>
            </button>
            <button className="clickableMetric reminderOverviewCard" onClick={() => setSection("Reminder History")}>
              <span>Failed / Not Sent</span>
              <strong>0</strong>
              <small>Open history</small>
            </button>
          </div>
          <ReminderPreview title={previewTitle} status={prototypeStatus} />
        </section>
      )}

      {section === "Dues Reminders" && (
        <section className="compactPanel">
          <Toolbar title="Dues Reminders" actionLabel="Treasurer workflow" />
          <div className="tableLike">
            {duesReminderRows.map((row, index) => (
              <div className="staticRow reminderDueRow" key={`${row.member}-${row.type}-${index}`}>
                <strong>{row.member}</strong>
                <span>{money(row.amount)}</span>
                <span>{row.type}</span>
                <span>{row.channel}</span>
                <span className="tag">{row.status}</span>
                <button className="filterButton" onClick={() => openPreview(`${row.member} dues reminder`, "Preview panel only. No WhatsApp message sent.")}>Preview</button>
              </div>
            ))}
          </div>
          <ReminderActions
            onPreview={() => openPreview("Dues reminder message", "Preview panel only.")}
            onSend={() => openPreview("Dues reminder send now", "Prototype status: send now clicked, no real sending.")}
            onSchedule={() => openPreview("Dues reminder schedule", "Prototype status: dummy reminder scheduled.")}
            onMark={() => openPreview("Dues reminder contacted", "Prototype status: marked as contacted.")}
          />
          <ReminderPreview title={previewTitle} status={prototypeStatus} />
        </section>
      )}

      {section === "Meeting Reminders" && (
        <section className="compactPanel">
          <Toolbar title="Meeting Reminders" actionLabel="Secretary workflow" />
          <div className="summaryList">
            <Field label="Meeting/Event" value="Monthly Family Fellowship" />
            <Field label="Date" value="15 June 2026" />
            <Field label="Time" value="7:00 PM" />
            <Field label="Venue" value="Club Hall" />
            <Field label="Audience" value="Members & Family" />
            <Field label="Reminder Timing" value="1 day before / Same day morning / 1 hour before" />
          </div>
          <div className="buttonRow">
            <button onClick={() => openPreview("Monthly Family Fellowship reminder", "Preview panel only. No message sent.")}>Preview Reminder</button>
            <button className="secondaryButton" onClick={() => openPreview("Meeting reminder schedule", "Prototype status: dummy schedule saved.")}>Schedule Reminder</button>
            <button className="secondaryButton" onClick={() => openPreview("Meeting test reminder", "Prototype status: test reminder simulated.")}>Send Test Reminder</button>
          </div>
          <ReminderPreview title={previewTitle} status={prototypeStatus} />
        </section>
      )}

      {section === "Celebration Reminders" && (
        <section className="compactPanel">
          <Toolbar title="Celebration Reminders" actionLabel="Official greeting tracking" />
          <div className="notice">Members see only today's celebrations and recently completed celebrations. Secretary and President manage official greeting status here.</div>
          <div className="tableLike">
            {["Lion Ajith - Birthday today", "Lion Pradeep - Anniversary recently completed", "Lion Rajesh - Birthday today"].map((item, index) => (
              <div className="staticRow reminderSimpleRow" key={item}>
                <strong>{item}</strong>
                <span>{index === 1 ? "Greeting sent" : "Official greeting pending"}</span>
                <span className="tag">{index === 1 ? "Sent" : "Pending"}</span>
                <button className="filterButton" onClick={() => openPreview(item, "Prototype status: greeting reminder opened.")}>Preview</button>
                <button className="filterButton" onClick={() => openPreview(item, "Prototype status: marked as sent.")}>Mark as sent</button>
              </div>
            ))}
          </div>
          <ReminderPreview title={previewTitle} status={prototypeStatus} />
        </section>
      )}

      {section === "Announcement Reminders" && (
        <section className="compactPanel">
          <Toolbar title="Announcement Reminders" actionLabel="Secretary workflow" />
          <div className="tableLike">
            {announcements.map((item) => (
              <div className="staticRow reminderSimpleRow" key={item.title}>
                <strong>{item.title}</strong>
                <span>{item.audience}</span>
                <span className="tag">{item.status}</span>
                <button className="filterButton" onClick={() => openPreview(item.title, "Prototype status: announcement reminder preview opened.")}>Preview</button>
                <button className="filterButton" onClick={() => openPreview(item.title, "Prototype status: dummy schedule saved.")}>Schedule</button>
              </div>
            ))}
          </div>
          <ReminderPreview title={previewTitle} status={prototypeStatus} />
        </section>
      )}

      {section === "Message Templates" && (
        <section className="compactPanel">
          <Toolbar title="Message Templates" actionLabel="Dummy templates" />
          <div className="templateGrid">
            {["Dues Reminder", "Meeting Reminder", "Celebration Greeting", "Announcement Follow-up"].map((template) => (
              <article className="templateCard" key={template}>
                <h3>{template}</h3>
                <p>Dear Lion, this is a prototype template preview. Real approved WhatsApp templates belong to the backend/integration phase.</p>
                <button className="filterButton" onClick={() => openPreview(template, "Prototype template opened. No API/template asset created.")}>Preview Template</button>
              </article>
            ))}
          </div>
        </section>
      )}

      {section === "Reminder History" && (
        <section className="compactPanel">
          <Toolbar title="Reminder History" actionLabel="Dummy log" />
          <div className="tableLike">
            {reminderHistoryRows.map((row) => (
              <div className="staticRow reminderHistoryRow" key={`${row.date}-${row.title}`}>
                <strong>{row.date}</strong>
                <span>{row.title}</span>
                <span>{row.channel}</span>
                <span className="tag">{row.status}</span>
              </div>
            ))}
          </div>
        </section>
      )}

      {section === "Reminder Settings" && (
        <section className="compactPanel">
          <Toolbar title="Reminder Settings" actionLabel="Auto-send off" />
          <div className="settingsGrid">
            <Field label="Enable Dues Reminders" value="Yes" />
            <Field label="Enable Meeting Reminders" value="Yes" />
            <Field label="Enable Celebration Reminders" value="Yes" />
            <Field label="Enable Announcement Reminders" value="Yes" />
            <Field label="Primary Channel" value="WhatsApp" />
            <Field label="Secondary Channel" value="In-app Notification" />
            <Field label="Optional Backup" value="SMS" />
            <Field label="Auto-send" value="Off by default" />
            <Field label="Approval required before sending" value="Yes" />
          </div>
          <div className="buttonRow">
            <button disabled>Auto-send Off</button>
            <button className="secondaryButton" onClick={() => openPreview("Reminder settings", "Prototype status: settings preview only.")}>Preview Settings</button>
          </div>
          <div className="notice">Auto-send remains OFF by default to avoid accidental or embarrassing reminders from wrong dues or meeting data.</div>
        </section>
      )}
    </section>
  );
}

function ReminderActions({
  onPreview,
  onSend,
  onSchedule,
  onMark
}: {
  onPreview: () => void;
  onSend: () => void;
  onSchedule: () => void;
  onMark: () => void;
}) {
  return (
    <div className="buttonRow">
      <button onClick={onPreview}>Preview Message</button>
      <button className="secondaryButton" onClick={onSend}>Send Now</button>
      <button className="secondaryButton" onClick={onSchedule}>Schedule Reminder</button>
      <button className="secondaryButton" onClick={onMark}>Mark as Contacted</button>
    </div>
  );
}

function ReminderPreview({ title, status }: { title: string; status: string }) {
  return (
    <aside className="compactPanel reminderPreview">
      <Toolbar title="Dummy Preview Panel" actionLabel="No real sending" />
      <h3>{title}</h3>
      <p>Dear Lion, this is a prototype reminder message for LC Beenachi Connect Milestone 1A review.</p>
      <span>{status}</span>
    </aside>
  );
}

function ClubVisibilityModule({
  section,
  setSection,
  role,
  member,
  selectedSponsors,
  setScreen
}: {
  section: ClubVisibilitySection;
  setSection: (section: ClubVisibilitySection) => void;
  role: Role;
  member: Member;
  selectedSponsors: string[];
  setScreen: (screen: Screen) => void;
}) {
  const options: ClubVisibilitySection[] = ["Members", "Events & Meetings", "Announcements", "Documents", "Projects / Activities", "Celebrations"];
  return (
    <section className="stack">
      <ModuleHeader title="Club Visibility" label="Choose view-only section" value={section} options={options} onChange={setSection} setScreen={setScreen} />
      <div className="notice">Treasurer view is mostly read-only outside Finance.</div>
      {section === "Members" && <MembersList setScreen={setScreen} role={role} />}
      {section === "Events & Meetings" && <EventsMeetings role={role} member={member} selectedSponsors={selectedSponsors} setScreen={setScreen} />}
      {section === "Announcements" && <Announcements role={role} member={member} selectedSponsors={selectedSponsors} />}
      {section === "Documents" && <Documents role={role} />}
      {section === "Projects / Activities" && <ProjectsActivities setScreen={setScreen} />}
      {section === "Celebrations" && <Celebrations role={role} setScreen={setScreen} />}
    </section>
  );
}

function ReportCategory({ section }: { section: ReportsSection }) {
  const rows: Record<ReportsSection, string[]> = {
    "Member Reports": ["Active member list", "Profile update pending report", "Role-wise member report"],
    "Finance / Ledger Reports": ["Member-wise outstanding report", "Payment collection report", "Monthly finance summary"],
    "Fellowship Reports": ["Current month sponsorship report", "Sponsor payment report", "Queue history report"],
    "Events & Meetings Reports": ["Upcoming meetings report", "Attendance placeholder report", "Past event summary"],
    "Club Activity / Service Reports": ["Monthly Activity Report", "Yearly Consolidated Activity Report", "Category-wise Activity Report"],
    "Celebration Reports": ["Monthly birthday report", "Monthly anniversary report", "Celebration register"],
    "Blood Donor Consent Report": ["Consented donor list", "Blood group summary", "Contact visibility report"],
    "Document Reports": ["Published document report", "Board-only document report", "Pending upload report"]
  };
  return (
    <div className="reportGrid">
      {rows[section].map((report) => (
        <button className="reportCard" key={report}>
          <span>{section}</span>
          <strong>{report}</strong>
        </button>
      ))}
    </div>
  );
}

function ReportDetailScreen({ section, detail, setScreen }: { section: ReportsSection; detail: string; setScreen: (screen: Screen) => void }) {
  if (section === "Finance / Ledger Reports") {
    return <FinanceLedgerReportDetail detail={detail} setScreen={setScreen} />;
  }
  if (section === "Fellowship Reports" && detail === "Current Month Sponsorship") {
    return <CurrentMonthSponsorshipReport setScreen={setScreen} />;
  }
  if (section === "Fellowship Reports" && detail === "Sponsor Queue History") {
    return <SponsorQueueHistoryReport setScreen={setScreen} />;
  }

  const cards = reportDetailCards(section, detail);
  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title={detail} actionLabel={section} />
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("reports-tools-module")}>← Reports & Tools</button>
        <div className="notice">Dummy data only. Real report generation, filters, export, and approval logic will be implemented in backend phase.</div>
      </div>
      <div className="financeCardList">
        {cards.map((card, index) => (
          <article className="financeStackCard" key={`${section}-${detail}-${card.title}-${index}`}>
            <h3>{card.title}</h3>
            {card.meta.map((line) => <span key={line}>{line}</span>)}
          </article>
        ))}
      </div>
    </section>
  );
}

function SponsorQueueHistoryReport({ setScreen }: { setScreen: (screen: Screen) => void }) {
  const queueMovements = [
    {
      month: "June 2026",
      member: "Lion Bijesh",
      position: "1",
      action: "Selected for sponsorship",
      result: "Completed",
      note: "Selected for June fellowship sponsor group",
      nextStatus: "Moved to end of queue"
    },
    {
      month: "June 2026",
      member: "Lion Secretary User",
      position: "2",
      action: "Selected for sponsorship",
      result: "Pending completion",
      note: "Selected for June fellowship sponsor group",
      nextStatus: "Remains active until month is closed"
    },
    {
      month: "May 2026",
      member: "Lion Rajesh",
      position: "1",
      action: "Skipped",
      result: "Deferred",
      note: "Temporarily unavailable",
      nextStatus: "Carried forward to next eligible month"
    }
  ];

  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title="Sponsor Queue History" actionLabel="Fellowship Reports" />
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("reports-tools-module")}>← Reports & Tools</button>
        <div className="notice">Dummy data only. This report shows fellowship sponsor queue movement, not payment status.</div>
      </div>
      <div className="financeCardList">
        {queueMovements.map((movement) => (
          <article className="financeStackCard" key={`${movement.month}-${movement.member}-${movement.action}`}>
            <h3>Month: {movement.month}</h3>
            <span>Member: {movement.member}</span>
            <span>Queue Position: {movement.position}</span>
            <span>Queue Action: {movement.action}</span>
            <span>Queue Result: {movement.result}</span>
            <span>Reason / Note: {movement.note}</span>
            <span>Next Queue Status: {movement.nextStatus}</span>
          </article>
        ))}
      </div>
    </section>
  );
}

function CurrentMonthSponsorshipReport({ setScreen }: { setScreen: (screen: Screen) => void }) {
  const sponsors = [
    { month: "June 2026", sponsor: "Lion Bijesh", amount: 2500, paymentStatus: "Paid" },
    { month: "June 2026", sponsor: "Lion Secretary User", amount: 2500, paymentStatus: "Pending" },
    { month: "June 2026", sponsor: "Lion Treasurer User", amount: 2500, paymentStatus: "Pending" }
  ];
  const paidSponsors = sponsors.filter((sponsor) => sponsor.paymentStatus === "Paid").length;
  const pendingSponsors = sponsors.length - paidSponsors;
  const totalAmount = sponsors.reduce((sum, sponsor) => sum + sponsor.amount, 0);
  const collectedAmount = sponsors.filter((sponsor) => sponsor.paymentStatus === "Paid").reduce((sum, sponsor) => sum + sponsor.amount, 0);

  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title="Current Month Sponsorship" actionLabel="Fellowship Reports" />
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("reports-tools-module")}>← Reports & Tools</button>
        <div className="notice">Dummy data only. Real report generation, filters, export, and approval logic will be implemented in backend phase.</div>
      </div>
      <div className="metricsGrid">
        <Metric label="Required Sponsors" value="3" />
        <Metric label="Total Sponsors Selected" value={`${sponsors.length}`} />
        <Metric label="Paid Sponsors" value={`${paidSponsors}`} />
        <Metric label="Pending Sponsors" value={`${pendingSponsors}`} />
        <Metric label="Total Sponsorship Amount" value={money(totalAmount)} />
        <Metric label="Collected" value={money(collectedAmount)} />
        <Metric label="Pending Collection" value={money(totalAmount - collectedAmount)} />
      </div>
      <div className="financeCardList">
        {sponsors.map((sponsor) => (
          <article className="financeStackCard" key={`${sponsor.month}-${sponsor.sponsor}`}>
            <h3>Month: {sponsor.month}</h3>
            <span>Sponsor: {sponsor.sponsor}</span>
            <span>Amount: {money(sponsor.amount)}</span>
            <span>Payment Status: {sponsor.paymentStatus}</span>
          </article>
        ))}
      </div>
    </section>
  );
}

function FinanceLedgerReportDetail({ detail, setScreen }: { detail: string; setScreen: (screen: Screen) => void }) {
  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title={detail} actionLabel="Finance / Ledger Reports" />
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("reports-tools-module")}>← Reports & Tools</button>
        <div className="notice">Dummy data only. Real finance reports will be generated from backend ledger later.</div>
      </div>
      {detail === "Monthly Finance Summary" && <MonthlyFinanceSummaryReport />}
      {detail === "Payment Collection Report" && <PaymentCollectionReport />}
      {detail === "Member-wise Outstanding" && <MemberWiseOutstandingReport />}
      {detail === "Fellowship Payment Report" && <FellowshipPaymentReport />}
    </section>
  );
}

function MonthlyFinanceSummaryReport() {
  const summary = [
    ["Total Income", money(35000)],
    ["Total Expenses", money(18000)],
    ["Net Balance", money(17000)],
    ["Pending Dues", money(8000)]
  ];
  const breakdown = [
    "Monthly dues collected: ₹12,000",
    "Fellowship contributions: ₹7,500",
    "Collection box income: ₹2,350",
    "Project share collected: ₹5,000"
  ];

  return (
    <>
      <div className="summaryList">
        {summary.map(([label, value]) => <Field key={label} label={label} value={value} />)}
      </div>
      <div className="compactPanel">
        <Toolbar title="Breakdown" actionLabel="Prototype summary" />
        <div className="financeCardList">
          {breakdown.map((line) => (
            <article className="financeStackCard" key={line}>
              <h3>{line}</h3>
            </article>
          ))}
        </div>
      </div>
    </>
  );
}

function PaymentCollectionReport() {
  const receipts = [
    { receiptNo: "RCP-001", party: "Lion Ajith Kumar", type: "Member dues", amount: money(1000), mode: "UPI", date: "03 Jun 2026", recordedBy: "Lion Treasurer User" },
    { receiptNo: "RCP-045", party: "Collection Box", type: "Club income / donation", amount: money(2350), mode: "Cash", date: "02 Jun 2026", recordedBy: "Lion Treasurer User" },
    { receiptNo: "RCP-044", party: "Lion Bijesh", type: "Monthly dues", amount: money(500), mode: "Bank", date: "01 Jun 2026", recordedBy: "Lion Treasurer User" }
  ];

  return (
    <div className="financeCardList">
      {receipts.map((receipt) => (
        <article className="financeStackCard" key={receipt.receiptNo}>
          <h3>Receipt No: {receipt.receiptNo}</h3>
          <span>Member / Source: {receipt.party}</span>
          <span>Type: {receipt.type}</span>
          <span>Amount: {receipt.amount}</span>
          <span>Mode: {receipt.mode}</span>
          <span>Date: {receipt.date}</span>
          <span>Recorded By: {receipt.recordedBy}</span>
        </article>
      ))}
    </div>
  );
}

function MemberWiseOutstandingReport() {
  const rows = [
    { member: "Lion Ajith Kumar - Member", outstanding: money(1000), items: ["Monthly dues - June: ₹500", "Special Collection: ₹500"], status: "Pending" },
    { member: "Lion Treasurer User", outstanding: money(2500), items: ["Fellowship contribution: ₹2,500"], status: "Pending" },
    { member: "Lion Bijesh", outstanding: money(500), items: ["Project share: ₹500"], status: "Overdue" }
  ];

  return (
    <div className="financeCardList">
      {rows.map((row) => (
        <article className="financeStackCard" key={row.member}>
          <h3>Member: {row.member}</h3>
          <span>Outstanding Amount: {row.outstanding}</span>
          <span>Pending Items: {row.items.join("; ")}</span>
          <span>Status: {row.status}</span>
        </article>
      ))}
    </div>
  );
}

function FellowshipPaymentReport() {
  const rows = [
    { sponsor: "Lion Bijesh", month: "June 2026 Fellowship", amount: money(2500), status: "Paid", receipt: "RCP-010" },
    { sponsor: "Lion Secretary User", month: "June 2026 Fellowship", amount: money(2500), status: "Pending", receipt: "Not issued" },
    { sponsor: "Lion Treasurer User", month: "June 2026 Fellowship", amount: money(2500), status: "Pending", receipt: "Not issued" }
  ];

  return (
    <div className="financeCardList">
      {rows.map((row) => (
        <article className="financeStackCard" key={`${row.month}-${row.sponsor}`}>
          <h3>Sponsor: {row.sponsor}</h3>
          <span>Fellowship Month: {row.month}</span>
          <span>Amount: {row.amount}</span>
          <span>Payment Status: {row.status}</span>
          <span>Receipt: {row.receipt}</span>
        </article>
      ))}
    </div>
  );
}

function NavDetailScreen({ parent, title, role, setScreen }: { parent: string; title: string; role: Role; setScreen: (screen: Screen) => void }) {
  if (parent === "Operations" && title === "Draft / Pending") {
    return <DraftPendingAnnouncements setScreen={setScreen} />;
  }

  const rows = navDetailCards(parent, title, role);
  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title={navDetailTitle(parent, title)} actionLabel={parent} />
        <button className="secondaryButton compactBackButton" onClick={() => setScreen(parentScreen(parent))}>← {parent}</button>
        <div className="notice">{navDetailNote(parent, title)}</div>
      </div>
      <div className="draftPendingGrid">
        {rows.map((row, index) => (
          <article className="draftPendingCard" key={`${parent}-${title}-${row.title}-${index}`}>
            <div>
              <h3>{row.title}</h3>
              {row.meta.map((line) => <span key={line}>{line}</span>)}
            </div>
            <button className="filterButton">{row.action}</button>
          </article>
        ))}
      </div>
    </section>
  );
}

function navDetailTitle(parent: string, title: string) {
  if (parent === "People") {
    const titles: Record<string, string> = {
      Today: "Today’s Celebrations",
      "This Week": "This Week’s Celebrations",
      Birthdays: "Birthday List",
      Anniversaries: "Anniversary List"
    };
    return titles[title] || title;
  }
  if (parent === "Operations") {
    const titles: Record<string, string> = {
      "This Month": "This Month’s Activities",
      "Pending Reports": "Pending Activity Reports",
      "Completed Activities": "Completed Activities",
      "Category-wise Activities": "Category-wise Activities"
    };
    return titles[title] || title;
  }
  return title;
}

function DraftPendingAnnouncements({ setScreen }: { setScreen: (screen: Screen) => void }) {
  return <AnnouncementDetailScreen detail="Draft / Pending" setScreen={setScreen} />;
}

function AnnouncementDetailScreen({ detail, setScreen }: { detail: AnnouncementDetail; setScreen: (screen: Screen) => void }) {
  const config: Record<AnnouncementDetail, {
    title: string;
    note: string;
    action: string;
    rows: { title: string; meta: string[] }[];
  }> = {
    Published: {
      title: "Published Announcements",
      note: "Dummy data only.",
      action: "View",
      rows: [
        { title: "District Governor Visit Update", meta: ["Audience: All Members", "Status: Published"] },
        { title: "Monthly Fellowship Notice", meta: ["Audience: Members & Family", "Status: Published"] }
      ]
    },
    "Draft / Pending": {
      title: "Draft / Pending Announcements",
      note: "Dummy data only. Real approval workflow will be implemented in backend phase.",
      action: "Review",
      rows: [
        { title: "Board Meeting Notice", meta: ["Type: Meeting Notice", "Owner: Secretary", "Status: Draft"] },
        { title: "Medical Camp Announcement", meta: ["Type: Event / Activity", "Owner: President", "Status: Pending Approval"] },
        { title: "Fellowship Reminder", meta: ["Type: Announcement", "Owner: Secretary", "Status: Draft"] }
      ]
    },
    "High Priority": {
      title: "High Priority Announcements",
      note: "Dummy data only.",
      action: "View",
      rows: [
        { title: "Emergency Blood Requirement", meta: ["Audience: Members with donor consent", "Priority: High"] },
        { title: "District Visit Preparation", meta: ["Audience: Board & PST", "Priority: High"] }
      ]
    }
  };
  const current = config[detail];

  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title={current.title} actionLabel="Operations / Announcements" />
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("events-meetings")}>← Operations</button>
        <div className="notice">{current.note}</div>
      </div>
      <div className="draftPendingGrid">
        {current.rows.map((item) => (
          <article className="draftPendingCard" key={item.title}>
            <div>
              <h3>{item.title}</h3>
              {item.meta.map((line) => <span key={line}>{line}</span>)}
            </div>
            <button className="filterButton">{current.action}</button>
          </article>
        ))}
      </div>
    </section>
  );
}

function parentScreen(parent: string): Screen {
  if (parent === "People") return "members";
  if (parent === "Operations") return "events-meetings";
  if (parent === "Finance") return "ledger";
  return "dashboard";
}

function navDetailNote(parent: string, title: string) {
  if (parent === "Operations" && title.includes("Documents")) {
    return "Dummy data only. Real document upload, approval, and access control will be implemented in backend phase.";
  }
  return "Dummy data only. This screen validates layout and navigation before backend implementation.";
}

function navDetailCards(parent: string, title: string, role: Role = "president"): { title: string; meta: string[]; action: string }[] {
  if (parent === "People" && ["Today", "This Week", "Birthdays", "Anniversaries"].includes(title)) {
    return peopleCelebrationCards(title);
  }
  if (parent === "People" && ["PST Profiles", "President Profile", "Secretary Profile", "Treasurer Profile"].includes(title)) {
    return pstProfileCards(title, role);
  }
  if (parent === "Operations" && ["This Month", "Pending Reports", "Completed Activities", "Category-wise Activities"].includes(title)) {
    return projectActivityCards(title);
  }
  if (parent === "Finance" && title === "Add Due") {
    return outstandingDues.map((due) => ({
      title: due.member,
      meta: [
        `Due Type: ${due.label}`,
        `Due Date: ${due.dueDate}`,
        `Amount: ${money(due.amount)}`,
        `Status: ${due.status}`,
        ...("purpose" in due && due.purpose ? [`Purpose: ${due.purpose}`] : [])
      ],
      action: "Review"
    }));
  }
  if (parent === "Finance" && title === "Due Categories") {
    return [
      { title: "Monthly Dues", meta: ["Default Amount: ₹500", "Applies to: Active members", "Status: Active"], action: "View" },
      { title: "Special Collection", meta: ["Amount: Configurable", "Purpose: Club-approved contribution", "Status: Active"], action: "View" },
      { title: "Family Fellowship Contribution", meta: ["Amount: Sponsor based", "Applies to: Selected sponsors", "Status: Preview only"], action: "View" },
      { title: "Membership Fee", meta: ["Amount: On admission", "Applies to: New members", "Status: Future setup"], action: "View" }
    ];
  }
  if (parent === "Operations" && title === "Club Documents") {
    return [
      { title: "General Body Minutes", meta: ["Type: Minutes", "Visibility: Members", "Status: Published"], action: "View" },
      { title: "Member Guidelines", meta: ["Type: Guidelines", "Visibility: All Members", "Status: Published"], action: "View" },
      { title: "Board Meeting Minutes", meta: ["Type: Minutes", "Visibility: Board / PST only", "Status: Restricted"], action: "View" }
    ];
  }
  if (parent === "Operations" && title === "Meeting Minutes") {
    return [
      { title: "General Body Minutes - May 2026", meta: ["Type: Minutes", "Visibility: Members", "Status: Published"], action: "View" },
      { title: "Board Meeting Minutes - May 2026", meta: ["Type: Minutes", "Visibility: Board / PST only", "Status: Restricted"], action: "View" }
    ];
  }
  if (parent === "Operations" && title === "Circulars") {
    return [
      { title: "Public Circular - June Meeting", meta: ["Type: Member Circular", "Visibility: Members", "Status: Published"], action: "View" },
      { title: "Annual Plan Update Circular", meta: ["Type: Club Circular", "Visibility: All Members", "Status: Published"], action: "View" }
    ];
  }
  const rows = navDetailRows(parent, title);
  const labels = parent === "Finance"
    ? ["Due Type", "Due Date", "Amount", "Status"]
    : ["Type", "Date", "Audience", "Status"];
  return rows.map((row) => ({
    title: row[0],
    meta: row.slice(1).map((item, index) => `${labels[index] || "Detail"}: ${item}`),
    action: "View"
  }));
}

function projectActivityCards(title: string): { title: string; meta: string[]; action: string }[] {
  const filtered = activities.filter((activity) => {
    if (title === "This Month") return activity.month === "June";
    if (title === "Pending Reports") return activity.reportDocument !== "Uploaded";
    if (title === "Completed Activities") return activity.status === "Completed";
    return true;
  });

  return filtered.map((activity) => ({
    title: `Activity: ${activity.name}`,
    meta: [
      `Date: ${activity.date}`,
      `Category / Committee: ${activityCommitteeLabel(activity)}`,
      `People Served: ${activity.peopleServed}`,
      `Report Status: ${activity.reportDocument}`
    ],
    action: "View"
  }));
}

function activityCommitteeLabel(activity: Activity) {
  if (activity.lead.endsWith("Committee")) return activity.lead;
  if (activity.lead.includes("Secretary")) return `${activity.category} / Secretary`;
  return `${activity.category} / ${activity.lead}`;
}

function pstProfileCards(title: string, viewerRole: Role = "president"): { title: string; meta: string[]; action: string }[] {
  const profiles = [
    {
      member: members[0],
      role: "President",
      designation: "Club President / Admin",
      responsibility: "Overall club coordination, approvals, board oversight",
      documentAccess: "Full PST + board documents",
      financeAccess: "Full finance visibility"
    },
    {
      member: members[1],
      role: "Secretary",
      designation: "Club Secretary",
      responsibility: "Meetings, announcements, documents, activity reports",
      documentAccess: "PST + board/secretary documents",
      financeAccess: "View-only finance summary"
    },
    {
      member: members[2],
      role: "Treasurer",
      designation: "Club Treasurer",
      responsibility: "Ledger, payments, receipts, dues and finance reports",
      documentAccess: "Treasurer + PST documents",
      financeAccess: "Payment and ledger workflow access"
    }
  ];
  const selected = title === "President Profile"
    ? profiles.filter((profile) => profile.role === "President")
    : title === "Secretary Profile"
      ? profiles.filter((profile) => profile.role === "Secretary")
      : title === "Treasurer Profile"
        ? profiles.filter((profile) => profile.role === "Treasurer")
        : profiles;

  return selected.map((profile) => {
    const fullMeta = [
      "Photo: Placeholder",
      `Mobile: ${profile.member.consentContact ? profile.member.phone : "Contact hidden"}`,
      `Email: ${profile.member.id.toLowerCase()}@lcbeenachi.example`,
      `Role: ${profile.role}`,
      "Term / Lionistic Year: 2026–27",
      `Designation: ${profile.designation}`,
      `Committee Responsibility: ${profile.responsibility}`,
      `Blood Group Visibility: ${profile.member.donorConsent ? profile.member.bloodGroup : "Not shared"}`,
      `Document Access Level: ${profile.documentAccess}`,
      `Finance Access Level: ${profile.financeAccess}`,
      "Profile Completion Status: Dummy profile ready"
    ];
    const publicMeta = fullMeta.filter((line) => !line.startsWith("Blood Group") && !line.startsWith("Document") && !line.startsWith("Finance"));

    return {
      title: profile.member.name,
      meta: viewerRole === "member" ? publicMeta : fullMeta,
      action: viewerRole === "member" ? "View public PST profile" : "View linked member profile"
    };
  });
}

function peopleCelebrationCards(title: string): { title: string; meta: string[]; action: string }[] {
  const items = celebrationsForRole("president").filter((item) => {
    if (title === "Today") return item.date === "Today";
    if (title === "This Week") return item.date === "Today" || item.date === "12 Jun";
    if (title === "Birthdays") return item.type === "Birthday";
    if (title === "Anniversaries") return item.type === "Wedding Anniversary";
    return true;
  });

  if (title === "Today" && items.length === 0) {
    return [{ title: "No celebrations today", meta: [], action: "View" }];
  }

  return items.map((item) => ({
    title: `Member: ${item.name}`,
    meta: [
      `Type: ${item.type}`,
      `Date: ${item.date === "Today" ? "Today, 05 Jun" : item.date}`,
      `Contact: ${item.contact === "Hidden" ? "Hidden" : item.contact}`,
      `Status: ${item.wishStatus}`
    ],
    action: "View"
  }));
}

function navDetailRows(parent: string, title: string) {
  if (parent === "People" && title.includes("Member")) {
    return members.map((member) => [member.name, member.clubRole, member.status, member.bloodGroup, member.consentContact ? member.phone : "Contact hidden"]);
  }
  if (parent === "People" && title.includes("Celebration")) {
    return celebrationsForRole("president").map((item) => [item.name, item.type, item.date, item.contact, item.wishStatus]);
  }
  if (parent === "Operations" && (title.includes("Event") || title.includes("Meeting"))) {
    return eventsMeetings.map((item) => [item.title, item.date, item.venue, item.visibility, item.status]);
  }
  if (parent === "Operations" && title.includes("Activities") || title.includes("Reports") || title === "This Month") {
    return activities.map((activity) => [activity.name, activity.date, activity.lead, `${activity.peopleServed} served`, activity.reportDocument]);
  }
  if (parent === "Finance") {
    return outstandingDues.map((due) => [due.member, due.label, due.dueDate, money(due.amount), due.status]);
  }
  return [
    [title, parent, "Prototype placeholder", "Dummy data only", "View"],
    [`${title} item 2`, parent, "Prototype placeholder", "Dummy data only", "View"],
    [`${title} item 3`, parent, "Prototype placeholder", "Dummy data only", "View"]
  ];
}

function reportDetailCards(section: ReportsSection, detail: string) {
  if (section === "Member Reports") {
    return [
      { title: "Member: Lion Bijesh", meta: ["Role: President", "Status: Active", `Report: ${detail}`, "Action: Review"] },
      { title: "Member: Lion Secretary User", meta: ["Role: Secretary", "Status: Active", `Report: ${detail}`, "Action: Review"] },
      { title: "Member: Lion Ajith Kumar - Member", meta: ["Role: Member", "Status: Active", `Report: ${detail}`, "Action: Review"] }
    ];
  }
  if (section === "Club Activity / Service Reports") {
    return activities.map((activity) => ({
      title: activity.name,
      meta: [
        `Date: ${activity.date}`,
        `Lead: ${activity.lead}`,
        `People Served: ${activity.peopleServed}`,
        `Report Document: ${activity.reportDocument}`,
        `Report: ${detail}`
      ]
    }));
  }
  if (section === "Celebration Reports") {
    const celebrations = celebrationsForRole("president").filter((item) => {
      if (detail === "Monthly Birthday Report") return item.type === "Birthday";
      if (detail === "Monthly Anniversary Report") return item.type === "Wedding Anniversary" && isCurrentPrototypeMonth(item.date);
      return true;
    });
    return celebrations.map((item) => ({
      title: item.name,
      meta: [
        `Celebration Type: ${item.type}`,
        `Date: ${item.date}`,
        `Contact: ${item.contact}`,
        `Greeting Status: ${item.wishStatus}`
      ]
    }));
  }
  if (section === "Blood Donor Consent Report") {
    return bloodDonorReportCards(detail);
  }
  if (section === "Document Reports") {
    return documentReportCards(detail);
  }
  if (section === "Events & Meetings Reports") {
    return eventsMeetings.map((item) => ({
      title: item.title,
      meta: [
        `Date: ${item.date}${item.time ? ` · ${item.time}` : ""}`,
        `Venue: ${item.venue}`,
        `Audience: ${item.visibility}`,
        `Status: ${item.status}`,
        `Report: ${detail}`
      ]
    }));
  }
  return [
    { title: "Month: June 2026", meta: ["Sponsor: Lion Bijesh", `Amount: ${money(2500)}`, "Status: Paid", `Report: ${detail}`] },
    { title: "Month: June 2026", meta: ["Sponsor: Lion Secretary User", `Amount: ${money(2500)}`, "Status: Pending", `Report: ${detail}`] },
    { title: "Month: June 2026", meta: ["Sponsor: Lion Treasurer User", `Amount: ${money(2500)}`, "Status: Pending", `Report: ${detail}`] }
  ];
}

function isCurrentPrototypeMonth(date: string) {
  return date === "Today" || date.endsWith("Jun");
}

function bloodDonorReportCards(detail: string): { title: string; meta: string[] }[] {
  if (detail === "Blood Group Summary") {
    const groups = [...new Set(members.map((member) => member.bloodGroup))];
    return groups.map((group) => {
      const groupMembers = members.filter((member) => member.bloodGroup === group);
      const consented = groupMembers.filter((member) => member.donorConsent);
      const contactVisible = consented.filter((member) => member.consentContact).length;
      const contactHidden = consented.length - contactVisible;
      return {
        title: `Blood Group: ${group}`,
        meta: [
          `Total Members: ${groupMembers.length}`,
          `Consented Donors: ${consented.length}`,
          `Contact Visible: ${contactVisible}`,
          `Contact Hidden: ${contactHidden}`
        ]
      };
    });
  }

  if (detail === "Contact Visibility Report") {
    const consented = members.filter((member) => member.donorConsent);
    return [
      {
        title: "Contact Visibility Summary",
        meta: [
          `Contact Visible: ${consented.filter((member) => member.consentContact).length}`,
          `Contact Hidden: ${consented.filter((member) => !member.consentContact).length}`,
          `Consent Pending: ${members.filter((member) => !member.donorConsent).length}`,
          "Consent Denied: 0"
        ]
      }
    ];
  }

  return members.filter((member) => member.donorConsent).map((member) => ({
    title: `Donor Name: ${member.name}`,
    meta: [
      `Blood Group: ${member.bloodGroup}`,
      `Contact: ${member.consentContact ? member.phone : "Contact hidden"}`,
      "Consent Status: Consented"
    ]
  }));
}

function documentReportCards(detail: string): { title: string; meta: string[] }[] {
  const boardDocuments = [
    { title: "Board Meeting Minutes - May 2026", category: "Board minutes", visibility: "Board", status: "Published" },
    { title: "Board Resolution - June Planning", category: "Board resolution", visibility: "Board", status: "Published" },
    { title: "PST / Board Internal Planning Notes", category: "Internal planning", visibility: "Board", status: "Restricted" }
  ];
  const pendingDocuments = [
    { title: "Draft minutes pending upload", category: "Meeting minutes", visibility: "PST", status: "Pending Upload" },
    { title: "Pending project report attachment", category: "Project report", visibility: "Committee", status: "Pending Upload" },
    { title: "Pending circular/document upload", category: "Circular", visibility: "Members", status: "Pending Upload" }
  ];
  const publishedDocuments = documents.filter((item) => item.visibility === "Members" || item.title === "Project Report Template");
  const records = detail === "Board-only Document Report"
    ? boardDocuments
    : detail === "Pending Upload Report"
      ? pendingDocuments
      : publishedDocuments.map((item) => ({ ...item, status: "Published" }));

  return records.map((item) => ({
    title: item.title,
    meta: [
      `Category: ${item.category}`,
      `Visibility: ${item.visibility}`,
      `Status: ${item.status}`
    ]
  }));
}

function financeApiAmountText(amount: string | null): string | null {
  if (!amount) return null;

  const parsed = Number(amount);

  return Number.isFinite(parsed) ? money(parsed) : amount;
}

function financeDisplayAmount(amount: string | null | undefined): string {
  return financeApiAmountText(amount ?? null) ?? "Unavailable";
}

function financeDisplayValue(value: string | number | null | undefined): string {
  if (typeof value === "number") return String(value);
  if (typeof value === "string" && value.trim()) return value;
  return "Unavailable";
}

function hasClubFinanceReportValues(report: ClubFinanceReports | null): boolean {
  if (!report) return false;

  return Boolean(
    report.club_finance_overview ||
    report.outstanding_dues_summary ||
    report.member_wise_dues_summary?.length ||
    report.payment_collection_summary ||
    report.receipt_summary
  );
}

function hasClubBalanceSheetValues(balanceSheet: ClubBalanceSheet | null): boolean {
  if (!balanceSheet) return false;

  return Boolean(
    balanceSheet.assets ||
    balanceSheet.liabilities ||
    balanceSheet.net_position ||
    balanceSheet.source
  );
}

function financeCurrentDuesText(summary: MemberFinanceSummary): string {
  const parsed = Number(summary.current_dues_amount);

  if (Number.isFinite(parsed) && parsed <= 0) {
    return "No current dues";
  }

  return financeApiAmountText(summary.current_dues_amount) ?? "No current dues";
}

function financeLastPaymentText(summary: MemberFinanceSummary): string {
  const amount = financeApiAmountText(summary.last_payment_amount);

  if (!amount) {
    return "No payment recorded yet";
  }

  return summary.last_payment_date ? amount + " on " + summary.last_payment_date : amount;
}

function financeReceiptText(summary: MemberFinanceSummary): string {
  if (summary.receipt_available === true) {
    return summary.receipt_status ? "Receipt Available (" + summary.receipt_status + ")" : "Receipt Available";
  }

  return "Receipt not available";
}

function MemberFinanceSummaryPanel({
  compact = false,
  showPendingItems = false
}: {
  compact?: boolean;
  showPendingItems?: boolean;
}) {
  const [status, setStatus] = useState<FinanceSummaryStatus>("loading");
  const [summary, setSummary] = useState<MemberFinanceSummary | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function loadFinanceSummary() {
      setStatus("loading");

      try {
        const loginResponse = await fetch(API_BASE_URL + "/auth/login", {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json"
          },
          body: JSON.stringify(LOCAL_MEMBER_FINANCE_LOGIN)
        });

        if (!loginResponse.ok) {
          throw new Error("Member login failed");
        }

        const loginPayload = await loginResponse.json();
        const token = loginPayload.token as string | undefined;

        if (!token) {
          throw new Error("Member token missing");
        }

        const meResponse = await fetch(API_BASE_URL + "/auth/me", {
          headers: {
            Accept: "application/json",
            Authorization: "Bearer " + token
          }
        });

        if (!meResponse.ok) {
          throw new Error("Club lookup failed");
        }

        const mePayload = await meResponse.json();
        const clubId = mePayload.club_memberships?.[0]?.club?.id;

        if (!clubId) {
          throw new Error("Club missing");
        }

        const summaryResponse = await fetch(
          API_BASE_URL + "/clubs/" + clubId + "/finance/member-summary?include_pending_items=true&include_last_payment=true&include_receipt_status=true",
          {
            headers: {
              Accept: "application/json",
              Authorization: "Bearer " + token
            }
          }
        );

        if (!summaryResponse.ok) {
          throw new Error("Finance summary unavailable");
        }

        const summaryPayload = await summaryResponse.json();
        const financeSummary = summaryPayload.finance_summary as MemberFinanceSummary | undefined;

        if (!financeSummary) {
          throw new Error("Finance summary missing");
        }

        if (!cancelled) {
          setSummary(financeSummary);
          setStatus("ready");
        }
      } catch {
        if (!cancelled) {
          setSummary(null);
          setStatus("error");
        }
      }
    }

    loadFinanceSummary();

    return () => {
      cancelled = true;
    };
  }, []);

  if (status === "loading") {
    return (
      <section className="memberPanel">
        <Toolbar title="Member Finance Summary" actionLabel="Read-only API" />
        <div className="notice memberHelperText">Loading finance summary...</div>
      </section>
    );
  }

  if (status === "error" || !summary) {
    return (
      <section className="memberPanel">
        <Toolbar title="Member Finance Summary" actionLabel="Read-only API" />
        <div className="notice memberHelperText">Finance summary unavailable.</div>
      </section>
    );
  }

  return (
    <section className="memberPanel">
      <Toolbar title="Member Finance Summary" actionLabel="Read-only API" />
      <div className="memberDuesSummary">
        <Metric label="Current Dues" value={financeCurrentDuesText(summary)} />
        <Metric label="Payment Status" value={summary.payment_status} />
        {!compact && <Metric label="Last Payment" value={financeLastPaymentText(summary)} />}
        {!compact && <Metric label="Receipt Status" value={financeReceiptText(summary)} />}
      </div>

      {showPendingItems && (
        <section className="memberPanel">
          <Toolbar title="Pending Items" actionLabel={summary.pending_items.length ? "Payment needed" : "Clear"} />
          {summary.pending_items.length ? (
            <div className="memberLedgerList">
              {summary.pending_items.map((item, index) => (
                <div className="memberLedgerCard" key={item.source_type + "-" + (item.due_date ?? "no-date") + "-" + index}>
                  <div className="memberLedgerDate">{item.due_date ?? "Due"}</div>
                  <div>
                    <strong>{item.source_label}</strong>
                    <span>{financeApiAmountText(item.amount) ?? item.amount}</span>
                    <small>Status: {item.status}</small>
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="notice memberHelperText">No current dues.</div>
          )}
        </section>
      )}
    </section>
  );
}

function Dashboard({
  role,
  member,
  setScreen,
  openDashboardDetail,
  totalOutstanding
}: {
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  openDashboardDetail: (detail: DashboardDetail) => void;
  totalOutstanding: number;
}) {
  if (role === "member") {
    const paidThisMonth = ledgerRows
      .filter((row) => row.member === member.name)
      .reduce((sum, row) => sum + row.credit, 0);
    const memberDebits = ledgerRows
      .filter((row) => row.member === member.name)
      .reduce((sum, row) => sum + row.debit, 0);
    const netBalance = paidThisMonth - memberDebits;
    const currentDues = netBalance < 0 ? Math.abs(netBalance) : 0;
    const advanceBalance = netBalance > 0 ? netBalance : 0;
    return (
      <section className="memberHome">
        <div className="memberHero">
          <Avatar name={member.name} />
          <div>
            <p className="eyebrow">Welcome</p>
            <h2>{member.dashboardName}</h2>
            <p>{member.clubRole} - {member.status}</p>
          </div>
        </div>
        <section className="memberPanel">
          <Toolbar title="Important" actionLabel="Own records only" />
          <div className="memberImportantGrid">
            <button onClick={() => setScreen("member-dues-detail")}><span>Current Dues</span><strong>{money(currentDues)}</strong><small>Tap to view details</small></button>
            <button onClick={() => openDashboardDetail("member-events")}><span>Next Meeting</span><strong>12 Jun</strong><small>View details</small></button>
            <button onClick={() => setScreen("fellowship")}><span>Fellowship</span><strong>June</strong><small>{advanceBalance ? `${money(advanceBalance)} advance available` : "Sponsorship pending"}</small></button>
          </div>
        </section>
        <MemberFinanceSummaryPanel compact />
        <section className="memberPanel">
          <Toolbar title="Quick Access" actionLabel="Member tools" />
          <div className="memberQuickGrid">
            {memberQuickAccess.map((item) => (
              <button key={item.label} onClick={() => setScreen(item.screen)}>
                <span>{item.short}</span>
                <strong>{item.label}</strong>
              </button>
            ))}
          </div>
        </section>
        <section className="memberPanel">
          <Toolbar title="Recent / Upcoming" actionLabel="Today" />
          <div className="memberUpdateList">
            <button onClick={() => setScreen("announcements")}><strong>District Governor Visit Planning</strong><span>Priority: High</span></button>
            <button onClick={() => setScreen("documents")}><strong>June meeting notice published</strong><span>Document available</span></button>
            <button onClick={() => setScreen("celebrations")}><strong>Celebrations this week</strong><span>2 wishes pending</span></button>
          </div>
        </section>
      </section>
    );
  }

  if (role === "president") {
    return (
      <PresidentDashboard
        setScreen={setScreen}
        openDashboardDetail={openDashboardDetail}
        totalOutstanding={totalOutstanding}
      />
    );
  }

  return (
    <section className="stack">
      <PstOverview role={role} totalOutstanding={totalOutstanding} openDashboardDetail={openDashboardDetail} />
      <MyMemberAreaPanel setScreen={setScreen} />
      <section className="compactPanel">
        <div className="toolbar">
          <h2>What needs my attention today?</h2>
          <span className="statusPill">{roleLabels[role]}</span>
        </div>
        <div className="taskGrid">
          {rolePendingTasks[role].map((task) => (
            <button className="taskItem" key={task} onClick={() => openDashboardDetail(taskDetailForRole(role, task))}>
              <strong>{task}</strong>
              <span>{taskActionLabel(role, task)}</span>
            </button>
          ))}
        </div>
      </section>
      <div className="metricsGrid">
        <ClickableMetric label="Active members" value="64" action="Open" onClick={() => openDashboardDetail("active-members")} />
        <ClickableMetric label={role === "treasurer" ? "Payments pending" : role === "secretary" ? "Pending dues summary (view only)" : "Pending dues summary"} value={role === "treasurer" ? "5 receipts" : money(totalOutstanding)} action="Review" onClick={() => openDashboardDetail("pending-dues")} />
        <ClickableMetric label="Fellowship month status" value={role === "treasurer" ? "2 payments pending" : "Confirmation pending"} action="Continue" onClick={() => openDashboardDetail(role === "treasurer" ? "fellowship-pending" : "fellowship-status")} />
        <ClickableMetric label={role === "secretary" ? "Pending documents" : "Pending activity reports"} value="2" action="Review" onClick={() => openDashboardDetail(role === "secretary" ? "published-items" : "activity-reports")} />
        <ClickableMetric label="Upcoming events/meetings" value="3" action="View details" onClick={() => openDashboardDetail("upcoming-events")} />
        <ClickableMetric label={role === "treasurer" ? "Today’s collection" : "Recent announcements/docs"} value={role === "treasurer" ? money(8500) : "6"} action="Open" onClick={() => openDashboardDetail(role === "treasurer" ? "today-collection" : "published-items")} />
        <ClickableMetric label="Today's celebrations" value="1" action="Review" onClick={() => openDashboardDetail("celebration-reminders")} />
        <ClickableMetric label={role === "treasurer" ? "This month’s collection" : "Important club reminders"} value={role === "treasurer" ? money(42500) : "PST review due"} action="Review" onClick={() => openDashboardDetail(role === "treasurer" ? "month-collection" : "club-reminders")} />
      </div>
      {role === "treasurer" && (
        <div className="metricsGrid">
          <ClickableMetric label="Fellowship pending" value={money(5000)} action="Open" onClick={() => openDashboardDetail("fellowship-pending")} />
          <ClickableMetric label="Cash / Bank / UPI split" value="3 modes" action="View split" onClick={() => openDashboardDetail("mode-split")} />
        </div>
      )}
      <CelebrationWidget role={role} openDashboardDetail={openDashboardDetail} />
      <div className="actionGrid">
        {role === "secretary" && <>
          <Action title="Create meeting/event" body="Use default date and visibility options." onClick={() => setScreen("events-meetings")} />
          <Action title="Create announcement" body="Use communication template." onClick={() => setScreen("announcements")} />
          <Action title="Upload document" body="Attach file and select visibility." onClick={() => setScreen("documents")} />
          <Action title="Update activity" body="Use annual plan or template." onClick={() => setScreen("projects")} />
        </>}
        {role === "treasurer" && <>
          <Action title="Record payment" body="Select outstanding due and auto-allocate." onClick={() => setScreen("record-member-payment")} />
          <Action title="Add due" body="Use due category defaults." onClick={() => setScreen("ledger")} />
          <Action title="View member ledger" body="Search member account history." onClick={() => setScreen("ledger")} />
          <Action title="Fellowship payment status" body="Track sponsor payments." onClick={() => setScreen("fellowship")} />
        </>}
      </div>
    </section>
  );
}

function PresidentDashboard({
  setScreen,
  openDashboardDetail,
  totalOutstanding
}: {
  setScreen: (screen: Screen) => void;
  openDashboardDetail: (detail: DashboardDetail) => void;
  totalOutstanding: number;
}) {
  const priorityItems = [
    { title: "Confirm June fellowship sponsors", meta: "Group confirmation pending", action: "Continue", detail: "fellowship-status" as DashboardDetail },
    { title: "Approve 3 member profile updates", meta: "Submitted this week", action: "Review", detail: "pending-member-updates" as DashboardDetail },
    { title: "Review 2 pending activity reports", meta: "School kit and medical camp", action: "Update", detail: "activity-reports" as DashboardDetail },
    { title: "Send today’s celebration wishes", meta: "1 birthday today", action: "Open", detail: "celebration-reminders" as DashboardDetail },
    { title: "Check dues summary", meta: `${money(totalOutstanding)} pending`, action: "Review", detail: "pending-dues" as DashboardDetail }
  ];

  const snapshotItems = [
    { label: "Active Members", value: "64", action: "Open", detail: "active-members" as DashboardDetail },
    { label: "Pending Dues", value: money(totalOutstanding), action: "Review", detail: "pending-dues" as DashboardDetail },
    { label: "Fellowship", value: "Confirmation Pending", action: "Continue", detail: "fellowship-status" as DashboardDetail },
    { label: "Upcoming Events", value: "3", action: "View details", detail: "upcoming-events" as DashboardDetail },
    { label: "Activity Reports Pending", value: "2", action: "Update", detail: "activity-reports" as DashboardDetail },
    { label: "Celebrations This Week", value: "4", action: "Open", detail: "celebration-reminders" as DashboardDetail }
  ];

  return (
    <section className="dashboardStack">
      <div className="dashboardIntro">
        <div>
          <p className="eyebrow">President Dashboard</p>
          <h2>Today, 03 Jun 2026</h2>
        </div>
        <span className="statusPill">Control room view</span>
      </div>

      <section className="compactPanel dashboardPanel">
        <DashboardSectionHeader title="Today’s Priority Actions" label="Needs attention now" />
        <div className="priorityList">
          {priorityItems.map((item) => (
            <button className="priorityAction" key={item.title} onClick={() => openDashboardDetail(item.detail)}>
              <strong>{item.title}</strong>
              <span>{item.meta}</span>
              <small>{item.action}</small>
            </button>
          ))}
        </div>
      </section>

      <section className="compactPanel dashboardPanel">
        <DashboardSectionHeader title="Club Snapshot" label="Today’s status" />
        <div className="compactDashboardGrid">
          {snapshotItems.map((item) => (
            <CompactDashboardCard
              key={item.label}
              label={item.label}
              value={item.value}
              action={item.action}
              onClick={() => openDashboardDetail(item.detail)}
            />
          ))}
        </div>
      </section>

      <section className="compactPanel dashboardPanel">
        <DashboardSectionHeader title="PST Coordination Overview" label="Role-linked member profiles" />
        <div className="compactDashboardGrid">
          <CompactDashboardCard
            label="View PST Profiles"
            value="3 roles"
            action="Open"
            onClick={() => openDashboardDetail("pst-profiles")}
          />
        </div>
      </section>

      <MyMemberAreaPanel setScreen={setScreen} />

      <section className="compactPanel dashboardPanel">
        <DashboardSectionHeader title="Quick Actions" label="Common President tasks" />
        <div className="quickActionGrid">
          <button onClick={() => setScreen("members")}>Add Member</button>
          <button onClick={() => setScreen("announcements")}>Create Announcement</button>
          <button onClick={() => setScreen("record-member-payment")}>Record Payment</button>
          <button onClick={() => setScreen("fellowship")}>Open Fellowship</button>
          <button onClick={() => setScreen("reports-tools-module")}>Open Reports</button>
          <button onClick={() => setScreen("celebrations")}>Open Celebrations</button>
        </div>
      </section>

      <section className="compactPanel dashboardPanel">
        <DashboardSectionHeader title="Recent / Upcoming Updates" label="Latest movement" />
        <div className="updateList">
          <button onClick={() => openDashboardDetail("published-items")}><strong>Latest announcement</strong><span>District Governor Visit Planning</span><small>Open</small></button>
          <button onClick={() => openDashboardDetail("published-items")}><strong>Latest document</strong><span>Annual Plan 2026 published</span><small>View details</small></button>
          <button onClick={() => openDashboardDetail("today-collection")}><strong>Last payment</strong><span>₹3,000 via UPI</span><small>Review</small></button>
          <button onClick={() => openDashboardDetail("upcoming-events")}><strong>Upcoming meeting</strong><span>Board Meeting - 10 Jun 2026</span><small>View details</small></button>
        </div>
      </section>
    </section>
  );
}

function MyMemberAreaPanel({ setScreen }: { setScreen: (screen: Screen) => void }) {
  return (
    <section className="compactPanel dashboardPanel">
      <DashboardSectionHeader title="My Member Area" label="Personal member access" />
      <div className="compactDashboardGrid">
        {pstMemberAreaCards.map((item) => (
          <CompactDashboardCard
            key={item.label}
            label={item.label}
            value={item.value}
            action={item.action}
            onClick={() => setScreen(item.screen)}
          />
        ))}
      </div>
    </section>
  );
}

function DashboardSectionHeader({ title, label }: { title: string; label: string }) {
  return (
    <div className="toolbar">
      <h2>{title}</h2>
      <span className="statusPill">{label}</span>
    </div>
  );
}

function CompactDashboardCard({ label, value, action, onClick }: { label: string; value: string; action: string; onClick: () => void }) {
  return (
    <button className="compactDashboardCard" onClick={onClick}>
      <span>{label}</span>
      <strong>{value}</strong>
      <small>{action}</small>
    </button>
  );
}

function PstOverview({ role, totalOutstanding, openDashboardDetail }: { role: Role; totalOutstanding: number; openDashboardDetail: (detail: DashboardDetail) => void }) {
  const items: { label: string; value: string; detail: DashboardDetail; action: string }[] = [
    { label: "Pending member updates", value: "3", detail: "pending-member-updates", action: "Review" },
    { label: "Upcoming meetings/events", value: "3", detail: "upcoming-events", action: "View details" },
    { label: "Fellowship month status", value: "Draft", detail: "fellowship-status", action: "Continue" },
    { label: role === "secretary" ? "Pending dues summary (view only)" : "Pending dues summary", value: money(totalOutstanding), detail: "pending-dues", action: "Review" },
    { label: "Pending activity/project reports", value: "2", detail: "activity-reports", action: "Update" },
    { label: "Recent announcements/docs", value: "6", detail: "published-items", action: "Open" },
    { label: "View PST Profiles", value: "3 roles", detail: "pst-profiles", action: "Open" },
    { label: "Celebration reminders", value: "4 this week", detail: "celebration-reminders", action: "Review" },
    { label: "Important club reminders", value: "PST review due", detail: "club-reminders", action: "Review" }
  ];
  return (
    <section className="pstPanel">
      <div className="toolbar">
        <h2>PST Coordination Overview</h2>
        <span className="statusPill">President + Secretary + Treasurer</span>
      </div>
      <div className="pstGrid">
        {items.map((item) => (
          <ClickableMetric
            key={item.label}
            label={item.label}
            value={item.value}
            action={item.action}
            onClick={() => openDashboardDetail(item.detail)}
          />
        ))}
      </div>
    </section>
  );
}

function taskDetailForRole(role: Exclude<Role, "member">, task: string): DashboardDetail {
  if (task.includes("member")) return "pending-member-updates";
  if (task.includes("fellowship")) return role === "treasurer" ? "fellowship-pending" : "fellowship-status";
  if (task.includes("activity") || task.includes("project")) return "activity-reports";
  if (task.includes("dues") || task.includes("outstanding") || task.includes("payment") || task.includes("receipt")) return "pending-dues";
  if (task.includes("document") || task.includes("notice")) return "published-items";
  if (task.includes("celebration") || task.includes("birthday")) return "celebration-reminders";
  return "club-reminders";
}

function taskActionLabel(role: Exclude<Role, "member">, task: string) {
  if (role === "treasurer" && (task.includes("payment") || task.includes("receipt"))) return "Record / review";
  if (task.includes("fellowship")) return "Continue";
  if (task.includes("activity") || task.includes("project")) return "Update";
  if (task.includes("document") || task.includes("notice")) return "Open";
  return "Review";
}

function CelebrationWidget({ role, openDashboardDetail }: { role: Role; openDashboardDetail: (detail: DashboardDetail) => void }) {
  const visible = celebrationsForRole(role).slice(0, 3);
  return (
    <section className="compactPanel">
      <div className="toolbar">
        <h2>Celebration Reminders</h2>
        <span className="statusPill">Birthdays + anniversaries</span>
      </div>
      <div className="tableLike">
        {visible.map((item) => (
          <button className="staticRow compactRow clickableRow" key={`${item.name}-${item.type}`} onClick={() => openDashboardDetail("celebration-reminders")}>
            <Avatar name={item.name} />
            <strong>{item.name}</strong>
            <span>{item.type}</span>
            <span>{item.date}</span>
            <span className="tag">{item.wishStatus}</span>
          </button>
        ))}
      </div>
    </section>
  );
}

function celebrationsForRole(role: Role) {
  return members.flatMap((member) => {
    const rows = [];
    if (role !== "member" || member.consentDob) {
      rows.push({ name: member.name, type: "Birthday", date: member.birthday, remaining: member.birthday === "Today" ? "Today" : "This month", contact: member.consentContact ? member.phone : "Hidden", wishStatus: member.wishStatus });
    }
    if ((role !== "member" || member.consentAnniversary) && member.anniversary !== "Hidden") {
      rows.push({ name: member.name, type: "Wedding Anniversary", date: member.anniversary, remaining: "This month", contact: member.consentContact ? member.phone : "Hidden", wishStatus: member.wishStatus });
    }
    return rows;
  });
}

function memberCelebrationSections() {
  return {
    today: [
      {
        name: "Lion Ajith Kumar",
        type: "Birthday",
        status: "Today",
        phone: "+91 98765 10004"
      }
    ],
    recent: [
      {
        name: "Lion Secretary User",
        type: "Birthday",
        status: "Yesterday",
        phone: "+91 98765 10002"
      },
      {
        name: "Lion Bijesh",
        type: "Wedding Anniversary",
        status: "2 days ago",
        phone: "+91 98765 10001"
      }
    ]
  };
}

function DashboardDetailScreen({
  detail,
  role,
  member,
  setScreen,
  openDashboardDetail
}: {
  detail: DashboardDetail;
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  openDashboardDetail: (detail: DashboardDetail) => void;
}) {
  const allowed = allowedDashboardDetail(role, detail);
  if (!allowed) {
    return (
      <section className="stack">
        <DetailHeader title="Privacy-safe member view" label="Restricted dashboard detail" setScreen={setScreen} />
        <div className="notice">This dashboard detail is not visible for Member role in the final app flow.</div>
      </section>
    );
  }

  return (
    <section className="stack">
      <DetailHeader title={dashboardDetailTitle(detail)} label={dashboardDetailLabel(role, detail)} setScreen={setScreen} />
      {detail === "pending-member-updates" && <PendingMemberUpdatesDetail />}
      {detail === "upcoming-events" && <UpcomingEventsDetail role={role} />}
      {detail === "fellowship-status" && <FellowshipStatusDetail setScreen={setScreen} />}
      {detail === "pending-dues" && <PendingDuesDetail role={role} member={member} setScreen={setScreen} />}
      {detail === "activity-reports" && <ActivityReportsDetail role={role} />}
      {detail === "published-items" && <PublishedItemsDetail role={role} />}
      {detail === "celebration-reminders" && <CelebrationRemindersDetail role={role} />}
      {detail === "club-reminders" && <ClubRemindersDetail />}
      {detail === "active-members" && <ActiveMembersDetail setScreen={setScreen} />}
      {detail === "today-collection" && <CollectionDetailPanel title="Today’s Collection">{todayCollectionRows()}</CollectionDetailPanel>}
      {detail === "month-collection" && <CollectionDetailPanel title="This Month’s Collection">{monthlyCollectionRows()}</CollectionDetailPanel>}
      {detail === "fellowship-pending" && <CollectionDetailPanel title="Fellowship Pending">{fellowshipPendingRows()}</CollectionDetailPanel>}
      {detail === "mode-split" && <CollectionDetailPanel title="Cash / Bank / UPI Split">{modeSplitRows()}</CollectionDetailPanel>}
      {detail === "pst-profiles" && <PstProfilesDetail role={role} />}
      {detail === "member-dues" && <PendingDuesDetail role={role} member={member} setScreen={setScreen} />}
      {detail === "member-fellowship" && <MemberFellowshipDetail member={member} setScreen={setScreen} />}
      {detail === "member-events" && <UpcomingEventsDetail role={role} />}
      {detail === "member-published" && <PublishedItemsDetail role={role} />}
      {role === "treasurer" && (
        <div className="buttonRow">
          <button className="secondaryButton" onClick={() => openDashboardDetail("today-collection")}>Today’s Collection</button>
          <button className="secondaryButton" onClick={() => openDashboardDetail("month-collection")}>This Month’s Collection</button>
          <button className="secondaryButton" onClick={() => openDashboardDetail("mode-split")}>Cash / Bank / UPI Split</button>
        </div>
      )}
    </section>
  );
}

function DetailHeader({ title, label, setScreen }: { title: string; label: string; setScreen: (screen: Screen) => void }) {
  return (
    <div className="compactPanel">
      <Toolbar title={title} actionLabel={label} />
      <div className="buttonRow">
        <button onClick={() => setScreen("dashboard")}>Back to Dashboard</button>
        <button className="secondaryButton" onClick={() => setScreen("dashboard")}>Back to Menu</button>
      </div>
    </div>
  );
}

function allowedDashboardDetail(role: Role, detail: DashboardDetail) {
  const memberAllowed: DashboardDetail[] = [
    "member-dues",
    "member-fellowship",
    "member-events",
    "member-published",
    "celebration-reminders"
  ];
  if (role === "member") return memberAllowed.includes(detail);
  if (role === "treasurer") return detail !== "pending-member-updates";
  return true;
}

function dashboardDetailTitle(detail: DashboardDetail) {
  const titles: Record<DashboardDetail, string> = {
    "pending-member-updates": "Pending Member Updates",
    "upcoming-events": "Upcoming Meetings / Events",
    "fellowship-status": "Fellowship Month Status",
    "pending-dues": "Pending Dues Summary",
    "activity-reports": "Pending Activity / Project Reports",
    "published-items": "Published Announcements / Documents",
    "celebration-reminders": "Celebration Reminders",
    "club-reminders": "Important Club Reminders",
    "active-members": "Active Members",
    "today-collection": "Today’s Collection",
    "month-collection": "This Month’s Collection",
    "fellowship-pending": "Fellowship Pending",
    "mode-split": "Cash / Bank / UPI Split",
    "pst-profiles": "PST Profiles",
    "member-dues": "My Dues",
    "member-fellowship": "My Fellowship",
    "member-events": "Events & Meetings",
    "member-published": "Announcements / Documents"
  };
  return titles[detail];
}

function dashboardDetailLabel(role: Role, detail: DashboardDetail) {
  if (role === "member") return "Member permitted detail";
  if (role === "treasurer" && ["pending-dues", "today-collection", "month-collection", "fellowship-pending", "mode-split"].includes(detail)) {
    return "Treasurer working detail";
  }
  if (role === "secretary") return "Secretary operational detail";
  return "President review detail";
}

function PstProfilesDetail({ role }: { role: Role }) {
  return (
    <div className="financeCardList">
      {pstProfileCards("PST Profiles", role).map((profile) => (
        <article className="financeStackCard" key={profile.title}>
          <h3>{profile.title}</h3>
          {profile.meta.map((line) => <span key={line}>{line}</span>)}
        </article>
      ))}
    </div>
  );
}

function PendingMemberUpdatesDetail() {
  return (
    <div className="tableLike">
      {pendingMemberUpdates.map((item) => (
        <div className="staticRow dashboardDetailRow" key={`${item.member}-${item.change}`}>
          <strong>{item.member}</strong>
          <span>{item.change}</span>
          <span>{item.submitted}</span>
          <span className="tag">{item.status}</span>
          <div className="miniActions">
            <button className="filterButton">Review</button>
            <button className="filterButton">Approve</button>
            <button className="filterButton">Reject</button>
          </div>
        </div>
      ))}
    </div>
  );
}

function UpcomingEventsDetail({ role }: { role: Role }) {
  const visible = role === "member" ? eventsMeetings.filter((item) => item.visibility === "All Members") : eventsMeetings;
  return (
    <div className="tableLike">
      {visible.map((item) => (
        <div className="staticRow dashboardDetailRow" key={`${item.title}-${item.date}`}>
          <strong>{item.title}</strong>
          <span>{item.date} - {item.type}</span>
          <span>{item.venue}</span>
          <span>{item.visibility}</span>
          <span className="tag">{item.status}</span>
          <button className="filterButton">View details</button>
        </div>
      ))}
    </div>
  );
}

function FellowshipStatusDetail({ setScreen }: { setScreen: (screen: Screen) => void }) {
  const rows = [
    ["Month", "June 2026"],
    ["Required sponsors", "3 sponsors"],
    ["Suggested queue", "Lion Bijesh, Lion Secretary User, Lion Treasurer User"],
    ["Selected sponsors", "3 selected"],
    ["Confirmation status", "Pending President approval"],
    ["Ledger status", "Sponsor rows prepared"],
    ["Payment status", "2 pending / 1 paid"]
  ];
  return (
    <div className="compactPanel">
      <div className="fieldGrid">{rows.map(([label, value]) => <Field key={label} label={label} value={value} />)}</div>
      <button onClick={() => setScreen("fellowship")}>Continue fellowship workflow</button>
    </div>
  );
}

function PendingDuesDetail({ role, member, setScreen }: { role: Role; member: Member; setScreen: (screen: Screen) => void }) {
  const rows = role === "member" ? outstandingDues.filter((due) => due.member === member.name) : outstandingDues;
  return (
    <div className="tableLike">
      {rows.map((due) => (
        <div className="staticRow financeDueRow" key={due.id}>
          <strong>{due.member}</strong>
          <span>{due.label}</span>
          <span>{due.dueDate}</span>
          <span>{money(due.amount)}</span>
          <span className="tag">{due.status}</span>
          <button className="filterButton" disabled={role === "member"} onClick={() => setScreen("record-member-payment")}>Record Payment</button>
        </div>
      ))}
    </div>
  );
}

function ActivityReportsDetail({ role }: { role: Role }) {
  return (
    <div className="tableLike">
      {activities.map((activity) => (
        <div className="staticRow dashboardDetailRow" key={activity.name}>
          <strong>{activity.name}</strong>
          <span>{activity.date} - {activity.lead}</span>
          <span>Served: {activity.peopleServed}</span>
          <span>{money(activity.estimatedBudget)} / {money(activity.actualExpense)}</span>
          <span className="tag">{activity.reportDocument}</span>
          <button className="filterButton" disabled={role === "treasurer"}>Update report</button>
        </div>
      ))}
    </div>
  );
}

function PublishedItemsDetail({ role }: { role: Role }) {
  const rows = [
    ...announcements.map((item) => ({ title: item.title, type: "Announcement", visibility: item.audience, date: item.date, status: item.status })),
    ...documents.map((item) => ({ title: item.title, type: "Document", visibility: item.visibility, date: "03 Jun 2026", status: "Published" }))
  ].filter((item) => role !== "member" || item.visibility.includes("Members"));
  return (
    <div className="tableLike">
      {rows.map((item) => (
        <div className="staticRow dashboardDetailRow" key={`${item.type}-${item.title}`}>
          <strong>{item.title}</strong>
          <span>{item.type}</span>
          <span>Visible to: {item.visibility}</span>
          <span>{item.date}</span>
          <span className="tag">{item.status}</span>
          <button className="filterButton">View</button>
        </div>
      ))}
    </div>
  );
}

function CelebrationRemindersDetail({ role }: { role: Role }) {
  return (
    <div className="tableLike">
      {celebrationsForRole(role).map((item) => (
        <div className="staticRow dashboardDetailRow" key={`${item.name}-${item.type}-${item.date}`}>
          <strong>{item.name}</strong>
          <span>{item.type}</span>
          <span>{item.date}</span>
          <span>{item.contact}</span>
          <span className="tag">{item.wishStatus}</span>
          <button className="filterButton">Mark as sent</button>
        </div>
      ))}
    </div>
  );
}

function ClubRemindersDetail() {
  return (
    <div className="tableLike">
      {clubReminders.map((item) => (
        <div className="staticRow dashboardDetailRow" key={item.title}>
          <strong>{item.title}</strong>
          <span>{item.role}</span>
          <span>{item.due}</span>
          <span>{item.priority}</span>
          <span className="tag">{item.status}</span>
          <button className="filterButton">Review</button>
        </div>
      ))}
    </div>
  );
}

function ActiveMembersDetail({ setScreen }: { setScreen: (screen: Screen) => void }) {
  return (
    <div className="compactPanel">
      <div className="metricsGrid">
        <Metric label="Active members" value="64" />
        <Metric label="Board / PST" value="8" />
        <Metric label="Members with donor consent" value="42" />
        <Metric label="Profile updates pending" value="3" />
      </div>
      <button onClick={() => setScreen("members")}>Open member list</button>
    </div>
  );
}

function MemberFellowshipDetail({ member, setScreen }: { member: Member; setScreen: (screen: Screen) => void }) {
  return (
    <div className="compactPanel">
      <div className="fieldGrid">
        <Field label="Member" value={member.dashboardName} />
        <Field label="Current month" value="June 2026" />
        <Field label="Queue position" value="4" />
        <Field label="Sponsor status" value="Not selected this month" />
        <Field label="Last sponsored" value="Apr 2026" />
        <Field label="Visibility" value="Own fellowship status only" />
      </div>
      <button onClick={() => setScreen("fellowship")}>Open My Fellowship</button>
    </div>
  );
}

function MembersList({ setScreen, role }: { setScreen: (screen: Screen) => void; role: Role }) {
  return (
    <section className="stack">
      <Toolbar title="Member Directory" actionLabel={role === "member" ? "Consent-approved fields only" : "Role-based management"} />
      <div className="tableLike">
        {members.map((member) => (
          <button key={member.id} className="rowButton" onClick={() => setScreen(role === "member" ? "member-profile" : "member-profile")}>
            <Avatar name={member.name} />
            <span>
              <strong>{member.name}</strong>
              <small>{member.id} - {member.clubRole}</small>
            </span>
            <span>{member.bloodGroup}</span>
            <span>{member.consentContact || role !== "member" ? member.phone : "Hidden"}</span>
            <span className="tag">{member.status}</span>
          </button>
        ))}
      </div>
    </section>
  );
}

function Committees() {
  return (
    <section className="stack">
      <Toolbar title="Board & Committees" actionLabel="Year-wise structure" />
      <div className="actionGrid">
        <Action title="Board 2026" body="President, Secretary, Treasurer and board members." onClick={() => undefined} />
        <Action title="Education Committee" body="Lead: Lion Secretary User. 4 members." onClick={() => undefined} />
        <Action title="Health Committee" body="Lead: Lion Treasurer User. 5 members." onClick={() => undefined} />
        <Action title="Service Committee" body="Lead: Lion Bijesh. 6 members." onClick={() => undefined} />
      </div>
    </section>
  );
}

function MemberProfile({ member, role }: { member: Member; role: Role }) {
  return (
    <section className="detailGrid">
      <div className="profilePanel">
        <Avatar name={member.name} large />
        <h2>{member.dashboardName}</h2>
        <p>{member.id} - {member.clubRole}</p>
        <span className="tag">{member.status}</span>
      </div>
      <div className="fieldsPanel">
        <Field label="Phone" value={member.consentContact || role !== "member" ? member.phone : "Hidden by privacy"} />
        <Field label="Blood group" value={member.bloodGroup} />
        <Field label="Contact consent" value={member.consentContact ? "Enabled" : "Disabled"} />
        <Field label="Donor consent" value={member.donorConsent ? "Enabled" : "Disabled"} />
        <Field label="Financial visibility" value={role === "member" ? "Own dues only" : "Role-based club view"} />
      </div>
    </section>
  );
}

function DigitalCard({ member }: { member: Member }) {
  return (
    <section className="cardPreviewWrap">
      <div className="digitalCard">
        <div className="cardTop">
          <div>
            <p>Lions Club Beenachi</p>
            <h2>Digital Membership Card</h2>
          </div>
          <div className="miniMark">LC</div>
        </div>
        <div className="cardBody">
          <Avatar name={member.name} large />
          <div>
            <h3>{member.dashboardName}</h3>
            <p>{member.id}</p>
            <p>{member.clubRole}</p>
            <p>Valid: 2026</p>
          </div>
        </div>
        <div className="cardFooter">Basic in-app card only. QR public profile and NFC are deferred.</div>
      </div>
    </section>
  );
}

function FellowshipDashboard({
  role,
  member,
  setScreen,
  tab,
  setTab,
  selectedSponsors
}: {
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  tab: "Overview" | "Queue" | "Selected Sponsors" | "Payments" | "History";
  setTab: (tab: "Overview" | "Queue" | "Selected Sponsors" | "Payments" | "History") => void;
  selectedSponsors: string[];
}) {
  if (role === "member") {
    return <MemberFellowship member={member} selectedSponsors={selectedSponsors} />;
  }

  const tabs: typeof tab[] = ["Overview", "Queue", "Selected Sponsors", "Payments", "History"];
  const progressByTab: Record<typeof tab, string> = {
    Overview: "Step 4 — Confirm group",
    Queue: "Step 2 — See suggested queue members",
    "Selected Sponsors": "Step 3 — Select sponsors",
    Payments: "Step 7 — Payments tracked",
    History: "Month history review"
  };
  const progress = [
    ["Month selected", true],
    ["Queue ready", true],
    ["Sponsors selected", true],
    ["Group confirmed", false],
    ["Ledger impact previewed", false],
    ["Payments tracked", false],
    ["Month closed", false]
  ] as const;

  return (
    <section className="stack">
      <div className="compactPanel">
        <div className="toolbar">
          <div>
            <p className="eyebrow">Fellowship Sponsorship - June 2026</p>
            <h2>Guided sponsor workflow</h2>
          </div>
          <span className="statusPill">Confirmation pending</span>
        </div>
        <div className="metricsGrid">
          <Metric label="Month" value="June 2026" />
          <Metric label="Status" value="Confirmation pending" />
          <Metric label="Progress" value={progressByTab[tab]} />
          <Metric label="Current section" value={tab} />
        </div>
        <label className="moduleSelect">
          Fellowship section
          <select value={tab} onChange={(event) => setTab(event.target.value as typeof tab)}>
            {tabs.map((item) => <option key={item}>{item}</option>)}
          </select>
        </label>
      </div>

      {tab === "Overview" && (
        <>
          <div className="guidedFlow">
            {[
              "Select month",
              "See suggested queue members",
              "Select sponsors",
              "Confirm group",
              "Sponsor rows generated",
              "Ledger impact previewed",
              "Payments tracked",
              "Close month"
            ].map((item, index) => (
              <div className="guidedStep" key={item}>
                <span>{index + 1}</span>
                <strong>{item}</strong>
              </div>
            ))}
          </div>
          <div className="metricsGrid">
            <Metric label="Month" value="June 2026" />
            <Metric label="Required sponsors" value="3" />
            <Metric label="System suggested" value="3 members" />
            <Metric label="Payment status" value="2 pending" />
          </div>
          <div className="actionGrid">
            <Action title="Copy previous month setup" body="Use meeting date and amount defaults." onClick={() => undefined} />
            <Action title="Suggest next eligible sponsors" body="Queue-based recommendation ready." onClick={() => setTab("Queue")} />
            <Action title="Prepare sponsor reminder draft" body="Prepare sponsor reminder message." onClick={() => undefined} />
            <Action title="Open Sponsor Payments" body="Track sponsor contribution status." onClick={() => setTab("Payments")} />
          </div>
        </>
      )}

      {tab === "Queue" && (
        <div className="compactPanel">
          <Toolbar title="Eligible Queue" actionLabel="System suggests next members" />
          <div className="queueGrid">
            {fellowshipQueue.slice(0, 4).map((item) => (
              <div className={item.status === "Eligible" ? "queueItem selected" : "queueItem"} key={item.member}>
                <strong>{item.order}. {item.member}</strong>
                <span>{item.status} - Last: {item.last}</span>
              </div>
            ))}
          </div>
          <button onClick={() => setScreen("fellowship-queue")}>Adjust Queue Selection</button>
        </div>
      )}

      {tab === "Selected Sponsors" && (
        <div className="compactPanel">
          <Toolbar title="Selected Sponsors" actionLabel={`${selectedSponsors.length} selected`} />
          <div className="selectedSponsorGrid">
            {selectedSponsors.map((sponsor) => (
              <article className="selectedSponsorCard" key={sponsor}>
                <div>
                  <strong>{sponsor}</strong>
                  <span>Amount: {money(2500)}</span>
                  <span>Status: Auto-created sponsor row</span>
                </div>
                <button className="filterButton">Review</button>
              </article>
            ))}
          </div>
          <button onClick={() => setScreen("confirm-sponsors")}>Confirm Group + Auto-Generate Rows</button>
        </div>
      )}

      {tab === "Payments" && (
        <div className="compactPanel">
          <Toolbar title="Payment Status" actionLabel="Sponsor dues preview ready" />
          <div className="selectedSponsorGrid">
            {selectedSponsors.map((sponsor, index) => (
              <article className="selectedSponsorCard" key={sponsor}>
                <div>
                  <strong>{sponsor}</strong>
                  <span>Amount: {money(2500)}</span>
                  <span>Payment status: {index === 0 ? "Paid" : "Pending"}</span>
                  <span>Receipt/reminder status: {index === 0 ? "Receipt ready" : "Reminder prepared"}</span>
                </div>
                <button className="filterButton">Review</button>
              </article>
            ))}
          </div>
        </div>
      )}

      {tab === "History" && (
        <div className="compactPanel">
          <Toolbar title="History / Close Month" actionLabel="Close after payments" />
          <div className="fieldGrid">
            <Field label="May 2026" value="Completed" />
            <Field label="April 2026" value="Completed" />
            <Field label="June 2026" value="Pending confirmation" />
            <Field label="Queue update" value="Runs after close month" />
          </div>
          <button className="secondaryButton">Close Month + Update Queue</button>
        </div>
      )}

      {tab !== "Queue" && (
        <div className="buttonRow">
          <button onClick={() => setScreen("fellowship-queue")}>Open Queue</button>
          <button className="secondaryButton" onClick={() => setScreen("confirm-sponsors")}>Review Sponsor Group</button>
        </div>
      )}
    </section>
  );
}

function MemberFellowship({ member, selectedSponsors }: { member: Member; selectedSponsors: string[] }) {
  const isSponsor = selectedSponsors.includes(member.name) || selectedSponsors.includes(member.dashboardName);
  const coSponsors = selectedSponsors.filter((sponsor) => sponsor !== member.name && sponsor !== member.dashboardName);
  const memberPaid = false;

  return (
    <section className="stack">
      <section className="memberPanel">
        <Toolbar title="My Fellowship" actionLabel="Member view" />
        <div className="memberFellowshipHero">
          <p className="eyebrow">June 2026</p>
          <h2>{isSponsor ? "You are assigned as a fellowship sponsor this month." : "You are not assigned as a fellowship sponsor this month."}</h2>
          <p>{isSponsor ? "Please check your own contribution and action." : "No action required."}</p>
        </div>
      </section>

      {isSponsor ? (
        <>
          <section className="memberPanel">
            <Toolbar title="Your Sponsorship" actionLabel={memberPaid ? "Paid" : "Action needed"} />
            <div className="memberDuesSummary">
              <Metric label="Your Contribution" value={money(500)} />
              <Metric label="Your Payment Status" value={memberPaid ? "Paid" : "Pending"} />
              <Metric label="Meeting Date" value="15 Jun 2026" />
            </div>
            {memberPaid ? (
              <div className="notice memberHelperText">No payment pending.</div>
            ) : (
              <div className="buttonRow">
                <button>Contact Treasurer</button>
                <button className="secondaryButton">View Fellowship Details</button>
              </div>
            )}
          </section>
          <section className="memberPanel">
            <Toolbar title="Co-sponsors" actionLabel="Names only" />
            <div className="memberLedgerList">
              {coSponsors.map((sponsor) => (
                <div className="memberLedgerCard" key={sponsor}>
                  <div className="memberLedgerDate">LC</div>
                  <div>
                    <strong>{sponsor}</strong>
                    <span>Co-sponsor for June Fellowship</span>
                  </div>
                </div>
              ))}
            </div>
            <div className="notice memberHelperText">Payment status of other sponsors is visible only to authorized club roles.</div>
          </section>
        </>
      ) : (
        <section className="memberPanel">
          <Toolbar title="Next Action" actionLabel="Clear" />
          <div className="notice memberHelperText">Your future sponsorship turn will be informed by the club.</div>
        </section>
      )}
    </section>
  );
}

function FellowshipQueue({ selectedSponsors, setSelectedSponsors, setScreen }: { selectedSponsors: string[]; setSelectedSponsors: (sponsors: string[]) => void; setScreen: (screen: Screen) => void }) {
  function toggleSponsor(name: string) {
    if (selectedSponsors.includes(name)) {
      setSelectedSponsors(selectedSponsors.filter((sponsor) => sponsor !== name));
      return;
    }
    if (selectedSponsors.length < 5) setSelectedSponsors([...selectedSponsors, name]);
  }
  return (
    <section className="stack">
      <Toolbar title="June Fellowship Queue" actionLabel={`${selectedSponsors.length} selected`} />
      <div className="queueGrid">
        {fellowshipQueue.map((item) => (
          <button key={item.member} className={selectedSponsors.includes(item.member) ? "queueItem selected" : "queueItem"} onClick={() => item.status === "Eligible" && toggleSponsor(item.member)} disabled={item.status !== "Eligible"}>
            <strong>{item.order}. {item.member}</strong>
            <span>{item.status} - Last: {item.last}</span>
          </button>
        ))}
      </div>
      <div className="buttonRow">
        <button onClick={() => setScreen("confirm-sponsors")}>Confirm Selected Group</button>
        <button className="secondaryButton">Skip / hold requires reason</button>
      </div>
    </section>
  );
}

function ConfirmSponsors({ selectedSponsors, setScreen }: { selectedSponsors: string[]; setScreen: (screen: Screen) => void }) {
  return (
    <section className="stack">
      <div className="notice">This confirmation creates sponsor child rows and ledger entries. No confusing AppSheet-style plus-button workflow.</div>
      <div className="tableLike">
        {selectedSponsors.map((sponsor, index) => (
          <div key={sponsor} className="staticRow">
            <span>{index + 1}</span>
            <strong>{sponsor}</strong>
            <span>{money(2500)}</span>
            <span className="tag">Pending payment</span>
          </div>
        ))}
      </div>
      <div className="buttonRow">
        <button onClick={() => setScreen("ledger")}>Generate Sponsor Rows + Ledger Entries</button>
        <button className="secondaryButton" onClick={() => setScreen("fellowship-queue")}>Back to Queue</button>
      </div>
    </section>
  );
}

function ledgerEntryType(row: (typeof ledgerRows)[number]) {
  if (row.type === "Fellowship sponsor ledger entry") return "Fellowship sponsor contribution";
  return row.type;
}

function ledgerBalanceImpact(row: (typeof ledgerRows)[number]) {
  if (row.credit) return `${money(row.credit)} reduced from dues`;
  return `+${money(row.debit)} due`;
}

function ledgerExtraFields(row: (typeof ledgerRows)[number]) {
  if (row.credit) {
    return ["Payment Mode: UPI", "Receipt No: RCP-001"];
  }
  if (row.type === "Fellowship sponsor ledger entry") {
    return ["Linked Module: Fellowship Sponsorship - June 2026"];
  }
  return [];
}

function Ledger({ role, member, setScreen }: { role: Role; member: Member; setScreen: (screen: Screen) => void }) {
  const visibleRows = role === "member" ? ledgerRows.filter((row) => row.member === member.name) : ledgerRows;
  const memberPendingDues = outstandingDues.filter((due) => due.member === member.name && due.status === "Pending");
  const pendingTotal = memberPendingDues.reduce((sum, due) => sum + due.amount, 0);
  const memberDebits = visibleRows.reduce((sum, row) => sum + row.debit, 0);
  const memberCredits = visibleRows.reduce((sum, row) => sum + row.credit, 0);
  const netBalance = memberCredits - memberDebits;
  const currentDues = pendingTotal;
  const advanceBalance = currentDues === 0 && netBalance > 0 ? netBalance : 0;
  const lastPayment = [...visibleRows].reverse().find((row) => row.credit > 0);

  if (role === "member") {
    return (
      <section className="stack">
        <Toolbar title="My Dues" actionLabel="Own records only" />
        <MemberFinanceSummaryPanel showPendingItems />
        <section className="memberPanel">
          <Toolbar title="Current Status" actionLabel="Private view" />
          <div className="memberDuesSummary">
            <button className="memberSummaryCard" onClick={() => setScreen("member-dues-detail")}>
              <span>Current Dues</span>
              <strong>{money(currentDues)}</strong>
              <small>Tap to view details</small>
            </button>
            <Metric label="Last Payment Made" value={lastPayment ? `${money(lastPayment.credit)} on ${lastPayment.date}` : "No payment yet"} />
            {advanceBalance > 0 && <Metric label="Advance" value={money(advanceBalance)} />}
          </div>
          {currentDues === 0 && <div className="notice memberHelperText">No payment pending at the moment.</div>}
          {advanceBalance > 0 && <div className="notice memberHelperText">Your advance balance will be adjusted against future dues.</div>}
        </section>
        <section className="memberPanel">
          <Toolbar title="Pending Items" actionLabel={memberPendingDues.length ? "Payment needed" : "Clear"} />
          {memberPendingDues.length ? (
            <div className="memberLedgerList">
              {memberPendingDues.map((due) => (
                <div className="memberLedgerCard" key={due.id}>
                  <div className="memberLedgerDate">Due</div>
                  <div>
                    <strong>{due.label === "Monthly dues - June" ? "Monthly Dues" : due.label}</strong>
                    <span>{money(due.amount)}</span>
                    {"purpose" in due && due.purpose && <small>Purpose: {due.purpose}</small>}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <div className="notice memberHelperText">No pending dues found.</div>
          )}
        </section>
        <section className="memberPanel">
          <Toolbar title="Dues & Payment History" actionLabel="Member-friendly view" />
          <div className="memberLedgerList">
            {[...visibleRows].reverse().map((row) => (
              <div key={row.id} className="memberLedgerCard">
                <div className="memberLedgerDate">{row.date}</div>
                <div>
                  <strong>{row.debit ? "Monthly Dues Added" : "You Paid"}</strong>
                  <span>{row.debit ? `Dues Added: ${money(row.debit)}` : `Amount: ${money(row.credit)}`}</span>
                  <small>{row.debit ? "Status: Pending" : `Payment recorded on ${row.date}`}</small>
                </div>
              </div>
            ))}
          </div>
        </section>
      </section>
    );
  }

  return (
    <section className="stack">
      <Toolbar title="Member Ledger" actionLabel={role === "secretary" ? "Finance summary view" : "Finance workflow"} />
      <div className="financeCardList">
        {visibleRows.map((row) => (
          <article key={row.id} className="financeStackCard">
            <h3>{ledgerEntryType(row)}</h3>
            <span>Date: {row.date} 2026</span>
            <span>Member: {row.member}</span>
            <span>Entry Type: {ledgerEntryType(row)}</span>
            <span>Debit: {row.debit ? money(row.debit) : "-"}</span>
            <span>Credit: {row.credit ? money(row.credit) : "-"}</span>
            <span>Balance Impact: {ledgerBalanceImpact(row)}</span>
            <span>Status: {row.credit ? "Payment recorded" : "Pending"}</span>
            {ledgerExtraFields(row).map((line) => <span key={line}>{line}</span>)}
          </article>
        ))}
      </div>
      {(role === "president" || role === "treasurer") && (
        <div className="buttonRow">
          <button onClick={() => setScreen("record-member-payment")}>Open Payments / Receipts</button>
          <button className="secondaryButton" onClick={() => setScreen("notifications-reminders")}>Send Due Reminder</button>
        </div>
      )}
    </section>
  );
}

function MemberDuesDetail({ member, setScreen }: { member: Member; setScreen: (screen: Screen) => void }) {
  const rows = ledgerRows.filter((row) => row.member === member.name);
  const memberPendingDues = outstandingDues.filter((due) => due.member === member.name && due.status === "Pending");
  const pendingTotal = memberPendingDues.reduce((sum, due) => sum + due.amount, 0);
  const totalAdded = rows.reduce((sum, row) => sum + row.debit, 0);
  const totalPaid = rows.reduce((sum, row) => sum + row.credit, 0);
  const netBalance = totalPaid - totalAdded;
  const currentDues = pendingTotal;
  const advanceBalance = currentDues === 0 && netBalance > 0 ? netBalance : 0;
  const recentPayments = [...rows].reverse().filter((row) => row.credit > 0);

  return (
    <section className="stack">
      <div className="buttonRow">
        <button className="secondaryButton" onClick={() => setScreen("ledger")}>Back to My Dues</button>
        <button className="secondaryButton" onClick={() => setScreen("dashboard")}>Back to Dashboard</button>
      </div>
      <MemberFinanceSummaryPanel showPendingItems />
      <section className="memberPanel">
        <Toolbar title="Current Dues Details" actionLabel="Member view" />
        <div className="memberDuesSummary">
          <Metric label="Current Dues" value={money(currentDues)} />
          {advanceBalance > 0 && <Metric label="Advance" value={money(advanceBalance)} />}
        </div>
        {currentDues === 0 && <div className="notice memberHelperText">No payment pending at the moment.</div>}
        {advanceBalance > 0 && <div className="notice memberHelperText">Your advance balance will be adjusted against future dues.</div>}
      </section>
      <section className="memberPanel">
        <Toolbar title="Pending Items" actionLabel={memberPendingDues.length ? "Payment needed" : "Clear"} />
        {memberPendingDues.length ? (
          <div className="memberLedgerList">
            {memberPendingDues.map((due) => (
              <div className="memberLedgerCard" key={due.id}>
                <div className="memberLedgerDate">Due</div>
                <div>
                  <strong>{due.label === "Monthly dues - June" ? "Monthly Dues" : due.label}</strong>
                  <span>{money(due.amount)}</span>
                  {"purpose" in due && due.purpose && <small>Purpose: {due.purpose}</small>}
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="notice memberHelperText">No pending dues found.</div>
        )}
      </section>
      <section className="memberPanel">
        <Toolbar title="Recent Payments" actionLabel="Your payments" />
        <div className="memberLedgerList">
          {recentPayments.map((row) => (
            <div className="memberLedgerCard" key={`${row.date}-${row.credit}`}>
              <div className="memberLedgerDate">{row.date}</div>
              <div>
                <strong>You Paid</strong>
                <span>{money(row.credit)}</span>
                <small>Payment recorded on {row.date}</small>
              </div>
            </div>
          ))}
        </div>
      </section>
    </section>
  );
}

function SpecialCollection({ setScreen }: { setScreen: (screen: Screen) => void }) {
  const expectedTotal = specialCollection.amountPerMember * specialCollection.applicableCount;
  const memberRows = [
    ["Lion Ajith Kumar - Member", "Pending"],
    ["Lion Secretary User", "Pending"],
    ["Lion Treasurer User", "Pending"],
    ["Lion Bijesh", "Pending"]
  ];

  return (
    <section className="stack">
      <Toolbar title="Special Collection" actionLabel="Posts dues, not income" />
      <p className="breadcrumbText">Finance → Member Ledger / Dues → Special Collection</p>

      <section className="compactPanel">
        <Toolbar title="Special Collection Summary" actionLabel={specialCollection.status} />
        <div className="summaryList">
          <Field label="Purpose" value={specialCollection.purpose} />
          <Field label="Amount per member" value={money(specialCollection.amountPerMember)} />
          <Field label="Applicable group" value={specialCollection.applicableMembers} />
          <Field label="Fund section" value={specialCollection.fundSection} />
          <Field label="Due date" value={specialCollection.dueDate} />
          <Field label="Status" value={specialCollection.status} />
        </div>
      </section>

      <section className="compactPanel">
        <Toolbar title="Generated Dues" actionLabel="Dummy posting result" />
        <div className="metricsGrid">
          <Metric label="Total applicable members" value={String(specialCollection.applicableCount)} />
          <Metric label="Amount per member" value={money(specialCollection.amountPerMember)} />
          <Metric label="Total expected collection" value={money(expectedTotal)} />
          <Metric label="Posted to member dues" value="Yes" />
        </div>
      </section>

      <div className="notice">
        Creating a Special Collection adds pending dues to member accounts. It becomes club income only when payments are recorded.
      </div>

      <details className="compactPanel disclosurePanel">
        <summary>
          <span>Member-wise Payment Status</span>
          <small>Compact Treasurer/Admin view · first 4 dummy rows</small>
        </summary>
        <div className="tableLike compactStatusTable">
          {memberRows.map(([member, status]) => (
            <div className="staticRow specialStatusRow" key={member}>
              <strong>{member}</strong>
              <span>Special Collection</span>
              <span>Due: {specialCollection.dueDate}</span>
              <span>{money(specialCollection.amountPerMember)}</span>
              <span className="tag">{status}</span>
              <button className="filterButton" onClick={() => setScreen("record-member-payment")}>Record Payment</button>
            </div>
          ))}
        </div>
        <div className="notice">
          View all members will open the full 40-member payment list in the final app.
        </div>
      </details>
    </section>
  );
}

function PaymentsReceiptsHub({ setScreen }: { setScreen: (screen: Screen) => void }) {
  return (
    <section className="stack">
      <Toolbar title="Payments / Receipts" actionLabel="Choose finance workflow" />
      <div className="notice">Record Member Payment uses the backend payment API. Receipt List reads already issued receipts. Payment History now reads the existing backend payment history APIs only.</div>
      <div className="actionGrid">
        <Action title="Record Member Payment" body="Record a member payment through the existing backend API without generating a receipt." onClick={() => setScreen("record-member-payment")} />
        <Action title="Record Club Income / Donation" body="Record collection box, donation, or other club income without changing member dues." onClick={() => setScreen("collection-box")} />
        <Action title="Receipt List" body="View an already issued member payment receipt and download its PDF using existing backend APIs." onClick={() => setScreen("receipt-list")} />
        <Action title="Payment History" body="Read payment history through the existing own-history or selected-member API without changing records." onClick={() => setScreen("payment-history")} />
      </div>
    </section>
  );
}

function RecordMemberPayment({ role }: { role: Role }) {
  const canRecordPayment = role === "president" || role === "secretary" || role === "treasurer";
  const [paymentStatus, setPaymentStatus] = useState<PaymentRecordingStatus>(canRecordPayment ? "initializing" : "ready");
  const [apiMessage, setApiMessage] = useState(
    canRecordPayment
      ? "Loading authorized payment context..."
      : "Payment recording is restricted to authorized PST finance roles."
  );
  const [paymentToken, setPaymentToken] = useState<string | null>(null);
  const [paymentClubId, setPaymentClubId] = useState<number | null>(null);
  const [backendMembers, setBackendMembers] = useState<BackendMemberRecord[]>([]);
  const [selectedMemberId, setSelectedMemberId] = useState<number | null>(null);
  const [summaryStatus, setSummaryStatus] = useState<FinanceSummaryStatus>("loading");
  const [selectedSummary, setSelectedSummary] = useState<MemberFinanceSummary | null>(null);
  const [recordedPayment, setRecordedPayment] = useState<PaymentRecordingApiPayment | null>(null);
  const [paymentForm, setPaymentForm] = useState<PaymentRecordingFormState>({
    amount: "1000",
    payment_date: new Date().toISOString().slice(0, 10),
    payment_mode: "upi",
    reference_number: "",
    notes: "Payment recorded from LC Beenachi Connect frontend."
  });

  const selectedMember = backendMembers.find((member) => member.id === selectedMemberId) ?? null;
  const isBusy = paymentStatus === "initializing" || paymentStatus === "submitting";

  function apiErrorMessage(payload: ApiErrorPayload | null, fallback: string) {
    if (!payload) return fallback;

    if (payload.message) {
      return payload.message;
    }

    if (payload.errors) {
      const messages = Object.values(payload.errors).flat();
      if (messages.length) {
        return messages.join(" ");
      }
    }

    return fallback;
  }

  async function fetchSelectedMemberFinanceSummary(currentToken: string, currentClubId: number, memberId: number) {
    const response = await fetch(
      API_BASE_URL + "/clubs/" + currentClubId + "/members/" + memberId + "/finance-summary?include_pending_items=true&include_last_payment=true&include_receipt_status=true",
      {
        headers: {
          Accept: "application/json",
          Authorization: "Bearer " + currentToken
        }
      }
    );

    if (!response.ok) {
      throw new Error("Selected member finance summary unavailable");
    }

    const payload = await response.json();
    const financeSummary = payload.finance_summary as MemberFinanceSummary | undefined;

    if (!financeSummary) {
      throw new Error("Selected member finance summary missing");
    }

    return financeSummary;
  }

  async function loadSelectedMemberSummary(memberId: number) {
    if (!paymentToken || !paymentClubId) {
      return;
    }

    setSummaryStatus("loading");

    try {
      const financeSummary = await fetchSelectedMemberFinanceSummary(paymentToken, paymentClubId, memberId);
      setSelectedSummary(financeSummary);
      setSummaryStatus("ready");
    } catch {
      setSelectedSummary(null);
      setSummaryStatus("error");
    }
  }

  useEffect(() => {
    let cancelled = false;

    async function loadPaymentContext() {
      if (!canRecordPayment) {
        setPaymentStatus("ready");
        setApiMessage("Payment recording is restricted to authorized PST finance roles.");
        return;
      }

      setPaymentStatus("initializing");
      setApiMessage("Loading authorized payment context...");
      setRecordedPayment(null);

      try {
        const login = LOCAL_PAYMENT_RECORDING_LOGINS[role as Exclude<Role, "member">];

        const loginResponse = await fetch(API_BASE_URL + "/auth/login", {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json"
          },
          body: JSON.stringify(login)
        });

        if (!loginResponse.ok) {
          throw new Error("Payment role login failed");
        }

        const loginPayload = await loginResponse.json();
        const token = loginPayload.token as string | undefined;

        if (!token) {
          throw new Error("Payment token missing");
        }

        const meResponse = await fetch(API_BASE_URL + "/auth/me", {
          headers: {
            Accept: "application/json",
            Authorization: "Bearer " + token
          }
        });

        if (!meResponse.ok) {
          throw new Error("Payment club lookup failed");
        }

        const mePayload = await meResponse.json();
        const memberships = mePayload.club_memberships as BackendClubMembership[] | undefined;
        const loadedClubId = memberships?.[0]?.club?.id;

        if (typeof loadedClubId !== "number") {
          throw new Error("Payment club missing");
        }

        const membersResponse = await fetch(API_BASE_URL + "/clubs/" + loadedClubId + "/members", {
          headers: {
            Accept: "application/json",
            Authorization: "Bearer " + token
          }
        });

        if (!membersResponse.ok) {
          throw new Error("Member list unavailable");
        }

        const membersPayload = await membersResponse.json();
        const activeMembers = ((membersPayload.members as BackendMemberRecord[] | undefined) ?? [])
          .filter((member) => member.status === "active");

        if (!activeMembers.length) {
          throw new Error("No active members available for payment recording");
        }

        const defaultMemberId = activeMembers[0].id;
        const financeSummary = await fetchSelectedMemberFinanceSummary(token, loadedClubId, defaultMemberId);

        if (!cancelled) {
          setPaymentToken(token);
          setPaymentClubId(loadedClubId);
          setBackendMembers(activeMembers);
          setSelectedMemberId(defaultMemberId);
          setSelectedSummary(financeSummary);
          setSummaryStatus("ready");
          setPaymentStatus("ready");
          setApiMessage("Ready to record payment through existing backend payment API.");
        }
      } catch (error) {
        if (!cancelled) {
          setPaymentToken(null);
          setPaymentClubId(null);
          setBackendMembers([]);
          setSelectedMemberId(null);
          setSelectedSummary(null);
          setSummaryStatus("error");
          setPaymentStatus("error");
          setApiMessage(error instanceof Error ? error.message : "Payment recording context unavailable.");
        }
      }
    }

    loadPaymentContext();

    return () => {
      cancelled = true;
    };
  }, [role, canRecordPayment]);

  async function submitPayment(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!canRecordPayment || !paymentToken || !paymentClubId || !selectedMemberId) {
      setPaymentStatus("error");
      setApiMessage("Payment cannot be submitted without an authorized role, club, and selected member.");
      return;
    }

    setPaymentStatus("submitting");
    setApiMessage("Submitting payment to backend...");
    setRecordedPayment(null);

    try {
      const response = await fetch(API_BASE_URL + "/clubs/" + paymentClubId + "/members/" + selectedMemberId + "/payments", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          Authorization: "Bearer " + paymentToken
        },
        body: JSON.stringify({
          amount: paymentForm.amount,
          payment_date: paymentForm.payment_date,
          payment_mode: paymentForm.payment_mode,
          reference_number: paymentForm.reference_number,
          notes: paymentForm.notes
        })
      });

      const payload = await response.json().catch(() => null) as { payment?: PaymentRecordingApiPayment } & ApiErrorPayload | null;

      if (!response.ok) {
        throw new Error(apiErrorMessage(payload, "Payment recording failed."));
      }

      if (!payload?.payment) {
        throw new Error("Payment response missing.");
      }

      const financeSummary = await fetchSelectedMemberFinanceSummary(paymentToken, paymentClubId, selectedMemberId);

      setRecordedPayment(payload.payment);
      setSelectedSummary(financeSummary);
      setSummaryStatus("ready");
      setPaymentStatus("success");
      setApiMessage("Payment recorded. Member finance summary refreshed from backend.");
    } catch (error) {
      setPaymentStatus("error");
      setApiMessage(error instanceof Error ? error.message : "Payment recording failed.");
    }
  }

  function updateSelectedMember(memberId: number) {
    setSelectedMemberId(memberId);
    setRecordedPayment(null);
    setApiMessage("Selected member changed. Loading finance summary...");
    void loadSelectedMemberSummary(memberId);
  }

  if (!canRecordPayment) {
    return (
      <section className="stack">
        <Toolbar title="Record Member Payment" actionLabel="Restricted" />
        <div className="notice">Payment recording is available only to President, Secretary, and Treasurer roles with backend payment permission.</div>
        <div className="notice">Members can view their own dues, but cannot see or use payment recording controls.</div>
      </section>
    );
  }

  return (
    <section className="stack">
      <Toolbar title="Record Member Payment" actionLabel="Backend payment API" />
      <div className="notice">
        Milestone 3AR narrow implementation. This form records member payments through the existing backend payment API. Receipt generation and receipt download remain separate workflows.
      </div>
      <div className="notice">{apiMessage}</div>

      <form className="formPanel" onSubmit={submitPayment}>
        <div className="fieldGrid">
          <label>
            Member
            <select
              value={selectedMemberId ?? ""}
              disabled={isBusy || !backendMembers.length}
              onChange={(event) => updateSelectedMember(Number(event.target.value))}
            >
              {backendMembers.map((member) => (
                <option key={member.id} value={member.id}>
                  {member.full_name} ({member.member_code})
                </option>
              ))}
            </select>
          </label>
          <label>
            Amount
            <input
              type="number"
              min="0.01"
              step="0.01"
              value={paymentForm.amount}
              disabled={isBusy}
              onChange={(event) => setPaymentForm({ ...paymentForm, amount: event.target.value })}
              required
            />
          </label>
          <label>
            Payment date
            <input
              type="date"
              value={paymentForm.payment_date}
              disabled={isBusy}
              onChange={(event) => setPaymentForm({ ...paymentForm, payment_date: event.target.value })}
              required
            />
          </label>
          <label>
            Payment mode
            <select
              value={paymentForm.payment_mode}
              disabled={isBusy}
              onChange={(event) => setPaymentForm({ ...paymentForm, payment_mode: event.target.value as PaymentRecordingMode })}
            >
              {PAYMENT_RECORDING_MODES.map((mode) => (
                <option key={mode} value={mode}>{PAYMENT_RECORDING_MODE_LABELS[mode]}</option>
              ))}
            </select>
          </label>
          <label>
            Reference number
            <input
              value={paymentForm.reference_number}
              disabled={isBusy}
              maxLength={100}
              onChange={(event) => setPaymentForm({ ...paymentForm, reference_number: event.target.value })}
              placeholder="Optional bank / UPI / cheque reference"
            />
          </label>
          <label>
            Notes
            <input
              value={paymentForm.notes}
              disabled={isBusy}
              maxLength={1000}
              onChange={(event) => setPaymentForm({ ...paymentForm, notes: event.target.value })}
              placeholder="Optional note"
            />
          </label>
        </div>
        <div className="buttonRow">
          <button type="submit" disabled={isBusy || !selectedMemberId}>
            {paymentStatus === "submitting" ? "Recording payment..." : "Record Payment"}
          </button>
          <button
            type="button"
            className="secondaryButton"
            disabled={isBusy}
            onClick={() => {
              setRecordedPayment(null);
              setPaymentStatus("ready");
              setApiMessage("Payment form ready. No backend change made by clearing this status.");
            }}
          >
            Clear status
          </button>
        </div>
      </form>

      <div className="metricsGrid">
        <Metric label="Selected Member" value={selectedMember ? selectedMember.full_name : "Loading..."} />
        <Metric label="Current Dues" value={selectedSummary ? financeCurrentDuesText(selectedSummary) : summaryStatus === "loading" ? "Loading..." : "Unavailable"} />
        <Metric label="Payment Status" value={selectedSummary ? selectedSummary.payment_status : summaryStatus === "loading" ? "Loading..." : "Unavailable"} />
        <Metric label="Last Payment" value={selectedSummary ? financeLastPaymentText(selectedSummary) : summaryStatus === "loading" ? "Loading..." : "Unavailable"} />
      </div>

      {selectedSummary && (
        <div className="compactPanel">
          <Toolbar title="Pending Items" actionLabel={selectedSummary.pending_items.length ? "Backend summary" : "Clear"} />
          {selectedSummary.pending_items.length ? (
            <div className="financeCardList">
              {selectedSummary.pending_items.map((item, index) => (
                <article className="financeStackCard" key={item.source_type + "-" + (item.due_date ?? "no-date") + "-" + index}>
                  <h3>{item.source_label}</h3>
                  <span>Amount: {financeApiAmountText(item.amount) ?? item.amount}</span>
                  <span>Due Date: {item.due_date ?? "Not set"}</span>
                  <span>Status: {item.status}</span>
                </article>
              ))}
            </div>
          ) : (
            <div className="notice">No current dues for selected member.</div>
          )}
        </div>
      )}

      {recordedPayment && (
        <div className="compactPanel">
          <Toolbar title="Payment Accepted" actionLabel="Backend confirmed" />
          <div className="fieldGrid">
            <Field label="Payment ID" value={String(recordedPayment.id)} />
            <Field label="Amount" value={financeApiAmountText(recordedPayment.amount) ?? recordedPayment.amount} />
            <Field label="Payment Date" value={recordedPayment.payment_date ?? "Not set"} />
            <Field label="Payment Mode" value={recordedPayment.payment_mode} />
            <Field label="Status" value={recordedPayment.status} />
            <Field label="Ledger Entry" value={recordedPayment.ledger_entry_id ? String(recordedPayment.ledger_entry_id) : "Linked by backend"} />
          </div>
          <div className="notice">Ledger credit is posted by the backend payment service. Receipt generation is not part of Milestone 3AR.</div>
        </div>
      )}
    </section>
  );
}

function CollectionBoxReceipt({ role }: { role: Role }) {
  const canEdit = role === "president" || role === "treasurer";

  return (
    <section className="stack">
      <Toolbar title="Record Club Income / Donation" actionLabel="Club income receipt" />
      <div className="notice">Record Member Payment uses the backend payment API. Receipt list, club income receipt, and transaction history remain prototype-only in this milestone.</div>
      <div className="notice">
        Collection box money is recorded only after the cash is opened, counted, and verified. It does not create member dues, receivables, or individual member account entries.
      </div>
      <div className="formPanel">
        <div className="fieldGrid">
          <label>Receipt Source<input value="Collection Box" readOnly /></label>
          <label>Purpose<input value={voluntaryContribution.purpose} readOnly /></label>
          <label>Amount Counted<input value={money(voluntaryContribution.amount)} readOnly /></label>
          <label>Fund Section<input value={voluntaryContribution.fundSection} readOnly /></label>
          <label>Mode<input value={voluntaryContribution.mode} readOnly /></label>
          <label>Counted By<input value={voluntaryContribution.countedBy} readOnly /></label>
          <label>Verified By<input value={voluntaryContribution.verifiedBy} readOnly /></label>
          <label>Deposit Status<input value={voluntaryContribution.depositStatus} readOnly /></label>
          <label>Voucher No<input value={voluntaryContribution.voucher} readOnly /></label>
          <label>Ledger Impact<input value="Club income only; no member dues impact" readOnly /></label>
        </div>
      </div>
      <ReceiptPreview
        title="Dummy Receipt Preview"
        rows={[
          ["Receipt No", voluntaryContribution.voucher],
          ["Receipt Source", "Collection Box"],
          ["Purpose", voluntaryContribution.purpose],
          ["Amount", money(voluntaryContribution.amount)],
          ["Mode", voluntaryContribution.mode],
          ["Fund Section", voluntaryContribution.fundSection],
          ["Ledger Impact", "Club income only; no member dues impact"]
        ]}
      />
      <button disabled={!canEdit}>Record Club Income Receipt</button>
    </section>
  );
}

function ReceiptList({ role }: { role: Role }) {
  const canUsePstReceiptAccess = role === "president" || role === "secretary" || role === "treasurer";
  const [status, setStatus] = useState<ReceiptLookupStatus>("initializing");
  const [message, setMessage] = useState("Loading receipt access context...");
  const [receiptToken, setReceiptToken] = useState<string | null>(null);
  const [receiptClubId, setReceiptClubId] = useState<number | null>(null);
  const [receiptMembers, setReceiptMembers] = useState<BackendMemberRecord[]>([]);
  const [selectedReceiptMemberId, setSelectedReceiptMemberId] = useState<number | null>(null);
  const [receiptPaymentId, setReceiptPaymentId] = useState("");
  const [receipt, setReceipt] = useState<ReceiptApiReceipt | null>(null);

  const selectedReceiptMember = receiptMembers.find((member) => member.id === selectedReceiptMemberId) ?? null;
  const canLookupReceipt = Boolean(receiptToken && receiptClubId && selectedReceiptMemberId && receiptPaymentId.trim()) && status !== "initializing" && status !== "loading";

  async function receiptResponseMessage(response: Response, fallback: string) {
    try {
      const payload = await response.json() as ApiErrorPayload;
      return payload.message ?? fallback;
    } catch {
      return fallback;
    }
  }

  useEffect(() => {
    let cancelled = false;

    async function loadReceiptContext() {
      setStatus("initializing");
      setMessage("Loading receipt access context...");
      setReceipt(null);

      try {
        const login = canUsePstReceiptAccess
          ? LOCAL_PAYMENT_RECORDING_LOGINS[role as Exclude<Role, "member">]
          : LOCAL_MEMBER_FINANCE_LOGIN;

        const loginResponse = await fetch(API_BASE_URL + "/auth/login", {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json"
          },
          body: JSON.stringify(login)
        });

        if (!loginResponse.ok) {
          throw new Error("Receipt role login failed");
        }

        const loginPayload = await loginResponse.json();
        const token = loginPayload.token as string | undefined;

        if (!token) {
          throw new Error("Receipt token missing");
        }

        const meResponse = await fetch(API_BASE_URL + "/auth/me", {
          headers: {
            Accept: "application/json",
            Authorization: "Bearer " + token
          }
        });

        if (!meResponse.ok) {
          throw new Error("Receipt club lookup failed");
        }

        const mePayload = await meResponse.json();
        const memberships = mePayload.club_memberships as BackendClubMembership[] | undefined;
        const loadedClubId = memberships?.[0]?.club?.id;

        if (typeof loadedClubId !== "number") {
          throw new Error("Receipt club missing");
        }

        if (canUsePstReceiptAccess) {
          const membersResponse = await fetch(API_BASE_URL + "/clubs/" + loadedClubId + "/members", {
            headers: {
              Accept: "application/json",
              Authorization: "Bearer " + token
            }
          });

          if (!membersResponse.ok) {
            throw new Error("Member list unavailable for receipt lookup");
          }

          const membersPayload = await membersResponse.json();
          const activeMembers = ((membersPayload.members as BackendMemberRecord[] | undefined) ?? [])
            .filter((member) => member.status === "active");

          if (!activeMembers.length) {
            throw new Error("No active members available for receipt lookup");
          }

          if (!cancelled) {
            setReceiptToken(token);
            setReceiptClubId(loadedClubId);
            setReceiptMembers(activeMembers);
            setSelectedReceiptMemberId(activeMembers[0].id);
            setStatus("ready");
            setMessage("Select a member and enter an issued payment ID to view receipt details.");
          }

          return;
        }

        const summaryResponse = await fetch(
          API_BASE_URL + "/clubs/" + loadedClubId + "/finance/member-summary?include_pending_items=false&include_last_payment=true&include_receipt_status=true",
          {
            headers: {
              Accept: "application/json",
              Authorization: "Bearer " + token
            }
          }
        );

        if (!summaryResponse.ok) {
          throw new Error("Own finance summary unavailable for receipt lookup");
        }

        const summaryPayload = await summaryResponse.json();
        const financeSummary = summaryPayload.finance_summary as MemberFinanceSummary | undefined;

        if (!financeSummary) {
          throw new Error("Own finance summary missing for receipt lookup");
        }

        if (!cancelled) {
          setReceiptToken(token);
          setReceiptClubId(loadedClubId);
          setReceiptMembers([{
            id: financeSummary.member_id,
            club_id: loadedClubId,
            member_code: financeSummary.member_code,
            full_name: financeSummary.member_name,
            status: "active"
          }]);
          setSelectedReceiptMemberId(financeSummary.member_id);
          setStatus("ready");
          setMessage("Enter your issued payment ID to view your receipt. Other members' receipts are not shown.");
        }
      } catch (error) {
        if (!cancelled) {
          setReceiptToken(null);
          setReceiptClubId(null);
          setReceiptMembers([]);
          setSelectedReceiptMemberId(null);
          setReceipt(null);
          setStatus("error");
          setMessage(error instanceof Error ? error.message : "Receipt access context unavailable.");
        }
      }
    }

    loadReceiptContext();

    return () => {
      cancelled = true;
    };
  }, [canUsePstReceiptAccess, role]);

  async function loadIssuedReceipt() {
    if (!receiptToken || !receiptClubId || !selectedReceiptMemberId || !receiptPaymentId.trim()) {
      return;
    }

    setStatus("loading");
    setReceipt(null);
    setMessage("Loading issued receipt...");

    const response = await fetch(
      API_BASE_URL + "/clubs/" + receiptClubId + "/members/" + selectedReceiptMemberId + "/payments/" + receiptPaymentId.trim() + "/receipt",
      {
        headers: {
          Accept: "application/json",
          Authorization: "Bearer " + receiptToken
        }
      }
    );

    if (!response.ok) {
      const failureMessage = await receiptResponseMessage(response, "Issued receipt unavailable.");
      setStatus("error");
      setMessage(failureMessage);
      return;
    }

    const payload = await response.json();
    const loadedReceipt = payload.receipt as ReceiptApiReceipt | undefined;

    if (!loadedReceipt) {
      setStatus("error");
      setMessage("Receipt response missing.");
      return;
    }

    setReceipt(loadedReceipt);
    setStatus("success");
    setMessage("Issued receipt loaded from existing backend receipt API.");
  }

  async function downloadIssuedReceiptPdf() {
    if (!receiptToken || !receiptClubId || !selectedReceiptMemberId || !receipt) {
      return;
    }

    setStatus("loading");
    setMessage("Preparing receipt PDF download...");

    const response = await fetch(
      API_BASE_URL + "/clubs/" + receiptClubId + "/members/" + selectedReceiptMemberId + "/payments/" + receipt.payment_id + "/receipt/pdf",
      {
        headers: {
          Accept: "application/pdf",
          Authorization: "Bearer " + receiptToken
        }
      }
    );

    if (!response.ok) {
      const failureMessage = await receiptResponseMessage(response, "Receipt PDF unavailable.");
      setStatus("error");
      setMessage(failureMessage);
      return;
    }

    const blob = await response.blob();
    const downloadUrl = URL.createObjectURL(blob);
    const anchor = document.createElement("a");

    anchor.href = downloadUrl;
    anchor.download = receipt.receipt_number + ".pdf";
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(downloadUrl);

    setStatus("success");
    setMessage("Receipt PDF download started.");
  }

  return (
    <section className="stack">
      <Toolbar title="Receipt List" actionLabel="Existing issued receipts" />
      <div className="notice">This screen uses only existing receipt view and PDF download APIs. It does not generate receipts, change payments, or show payment history.</div>

      {status === "initializing" && (
        <div className="notice memberHelperText">Loading receipt access context...</div>
      )}

      {status !== "initializing" && (
        <div className="formPanel">
          <div className="fieldGrid">
            {canUsePstReceiptAccess ? (
              <label>
                Member
                <select
                  value={selectedReceiptMemberId ?? ""}
                  onChange={(event) => {
                    setSelectedReceiptMemberId(Number(event.target.value));
                    setReceipt(null);
                    setStatus("ready");
                    setMessage("Enter an issued payment ID to view receipt details.");
                  }}
                >
                  {receiptMembers.map((member) => (
                    <option key={member.id} value={member.id}>{member.full_name} ({member.member_code})</option>
                  ))}
                </select>
              </label>
            ) : (
              <label>
                Member
                <input value={selectedReceiptMember ? selectedReceiptMember.full_name : "Own member record"} readOnly />
              </label>
            )}

            <label>
              Issued Payment ID
              <input
                value={receiptPaymentId}
                onChange={(event) => {
                  setReceiptPaymentId(event.target.value);
                  setReceipt(null);
                  setStatus("ready");
                  setMessage(canUsePstReceiptAccess ? "Enter an issued payment ID to view receipt details." : "Enter your issued payment ID to view your receipt.");
                }}
                placeholder="Example: 1"
              />
            </label>
          </div>

          <div className="buttonRow">
            <button disabled={!canLookupReceipt} onClick={loadIssuedReceipt}>View Issued Receipt</button>
          </div>
        </div>
      )}

      {message && <div className="notice memberHelperText">{message}</div>}

      {receipt && (
        <article className="financeStackCard">
          <h3>Receipt No: {receipt.receipt_number}</h3>
          <span>Member: {receipt.member_name ?? selectedReceiptMember?.full_name ?? "Member"}</span>
          <span>Payment ID: {receipt.payment_id}</span>
          <span>Amount: {financeApiAmountText(receipt.amount) ?? "Amount unavailable"}</span>
          <span>Payment Date: {receipt.payment_date ?? "Date unavailable"}</span>
          <span>Payment Mode: {receipt.payment_method ?? "Mode unavailable"}</span>
          <span>Status: {receipt.receipt_status}</span>
          <button className="filterButton compactActionButton" onClick={downloadIssuedReceiptPdf} disabled={status === "loading"}>Download Receipt PDF</button>
        </article>
      )}
    </section>
  );
}

function PaymentHistory({ role }: { role: Role }) {
  const canUseSelectedMemberHistory = role === "president" || role === "secretary" || role === "treasurer";
  const [status, setStatus] = useState<PaymentHistoryStatus>("initializing");
  const [message, setMessage] = useState("Loading payment history access context...");
  const [historyToken, setHistoryToken] = useState<string | null>(null);
  const [historyClubId, setHistoryClubId] = useState<number | null>(null);
  const [historyMembers, setHistoryMembers] = useState<BackendMemberRecord[]>([]);
  const [selectedHistoryMemberId, setSelectedHistoryMemberId] = useState<number | null>(null);
  const [payments, setPayments] = useState<PaymentHistoryApiRecord[]>([]);
  const [meta, setMeta] = useState<PaymentHistoryMeta | null>(null);

  const selectedHistoryMember = historyMembers.find((member) => member.id === selectedHistoryMemberId) ?? null;
  const currentPage = meta?.current_page ?? 1;
  const lastPage = meta?.last_page ?? 1;

  async function paymentHistoryResponseMessage(response: Response, fallback: string) {
    try {
      const payload = await response.json() as ApiErrorPayload;
      return payload.message ?? fallback;
    } catch {
      return fallback;
    }
  }

  async function fetchPaymentHistory(
    currentToken: string,
    currentClubId: number,
    memberId: number | null,
    requestedPage = 1
  ) {
    const url = canUseSelectedMemberHistory && memberId
      ? API_BASE_URL + "/clubs/" + currentClubId + "/members/" + memberId + "/payments/history?per_page=10&page=" + requestedPage
      : API_BASE_URL + "/clubs/" + currentClubId + "/members/me/payments/history?per_page=10&page=" + requestedPage;

    const response = await fetch(url, {
      headers: {
        Accept: "application/json",
        Authorization: "Bearer " + currentToken
      }
    });

    if (!response.ok) {
      const failureMessage = await paymentHistoryResponseMessage(response, "Payment history is unavailable right now.");
      throw new Error(response.status === 401 ? "Please sign in again to view payment history." : response.status === 403 ? "This role is not authorized to view that payment history." : failureMessage);
    }

    const payload = await response.json() as PaymentHistoryApiResponse;
    return {
      rows: payload.payment_history ?? [],
      meta: payload.meta ?? null
    };
  }

  async function loadPaymentHistory(
    currentToken = historyToken,
    currentClubId = historyClubId,
    memberId = selectedHistoryMemberId,
    requestedPage = 1
  ) {
    if (!currentToken || !currentClubId) {
      setStatus("error");
      setMessage("Payment history cannot load without an active club context.");
      return;
    }

    if (canUseSelectedMemberHistory && !memberId) {
      setStatus("empty");
      setPayments([]);
      setMeta(null);
      setMessage("No selected member is available for payment history.");
      return;
    }

    setStatus("loading");
    setMessage("Loading payment history from backend...");

    try {
      const result = await fetchPaymentHistory(currentToken, currentClubId, memberId, requestedPage);
      setPayments(result.rows);
      setMeta(result.meta);
      setStatus(result.rows.length ? "success" : "empty");
      setMessage(result.rows.length ? "Payment history loaded from existing backend API." : "No payment history records are available yet.");
    } catch (error) {
      setPayments([]);
      setMeta(null);
      setStatus("error");
      setMessage(error instanceof Error ? error.message : "Payment history is unavailable right now.");
    }
  }

  useEffect(() => {
    let cancelled = false;

    async function loadPaymentHistoryContext() {
      setStatus("initializing");
      setMessage("Loading payment history access context...");
      setPayments([]);
      setMeta(null);

      try {
        const login = canUseSelectedMemberHistory
          ? LOCAL_PAYMENT_RECORDING_LOGINS[role as Exclude<Role, "member">]
          : LOCAL_MEMBER_FINANCE_LOGIN;

        const loginResponse = await fetch(API_BASE_URL + "/auth/login", {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json"
          },
          body: JSON.stringify(login)
        });

        if (!loginResponse.ok) {
          throw new Error("Payment history login failed.");
        }

        const loginPayload = await loginResponse.json();
        const token = loginPayload.token as string | undefined;

        if (!token) {
          throw new Error("Payment history token missing.");
        }

        const meResponse = await fetch(API_BASE_URL + "/auth/me", {
          headers: {
            Accept: "application/json",
            Authorization: "Bearer " + token
          }
        });

        if (!meResponse.ok) {
          throw new Error("Payment history club lookup failed.");
        }

        const mePayload = await meResponse.json();
        const memberships = mePayload.club_memberships as BackendClubMembership[] | undefined;
        const loadedClubId = memberships?.[0]?.club?.id;

        if (typeof loadedClubId !== "number") {
          throw new Error("No active club context is available for payment history.");
        }

        if (canUseSelectedMemberHistory) {
          const membersResponse = await fetch(API_BASE_URL + "/clubs/" + loadedClubId + "/members", {
            headers: {
              Accept: "application/json",
              Authorization: "Bearer " + token
            }
          });

          if (!membersResponse.ok) {
            throw new Error("Member list unavailable for payment history.");
          }

          const membersPayload = await membersResponse.json();
          const activeMembers = ((membersPayload.members as BackendMemberRecord[] | undefined) ?? [])
            .filter((member) => member.status === "active");
          const defaultMemberId = activeMembers[0]?.id ?? null;

          if (!cancelled) {
            setHistoryToken(token);
            setHistoryClubId(loadedClubId);
            setHistoryMembers(activeMembers);
            setSelectedHistoryMemberId(defaultMemberId);
          }

          if (!defaultMemberId) {
            if (!cancelled) {
              setStatus("empty");
              setMessage("No active members are available for selected-member payment history.");
            }
            return;
          }

          const result = await fetchPaymentHistory(token, loadedClubId, defaultMemberId);

          if (!cancelled) {
            setPayments(result.rows);
            setMeta(result.meta);
            setStatus(result.rows.length ? "success" : "empty");
            setMessage(result.rows.length ? "Selected-member payment history loaded from existing backend API." : "No payment history records are available for the selected member.");
          }

          return;
        }

        const result = await fetchPaymentHistory(token, loadedClubId, null);

        if (!cancelled) {
          setHistoryToken(token);
          setHistoryClubId(loadedClubId);
          setHistoryMembers([]);
          setSelectedHistoryMemberId(null);
          setPayments(result.rows);
          setMeta(result.meta);
          setStatus(result.rows.length ? "success" : "empty");
          setMessage(result.rows.length ? "Your payment history loaded from existing backend API." : "No payment history records are available yet.");
        }
      } catch (error) {
        if (!cancelled) {
          setHistoryToken(null);
          setHistoryClubId(null);
          setHistoryMembers([]);
          setSelectedHistoryMemberId(null);
          setPayments([]);
          setMeta(null);
          setStatus("error");
          setMessage(error instanceof Error ? error.message : "Payment history context unavailable.");
        }
      }
    }

    loadPaymentHistoryContext();

    return () => {
      cancelled = true;
    };
  }, [canUseSelectedMemberHistory, role]);

  function updateSelectedHistoryMember(memberId: number) {
    setSelectedHistoryMemberId(memberId);
    void loadPaymentHistory(historyToken, historyClubId, memberId, 1);
  }

  return (
    <section className="stack">
      <Toolbar title={canUseSelectedMemberHistory ? "Payment History" : "My Payment History"} actionLabel="Read-only backend history" />
      <div className="notice">
        This screen uses only the existing payment history GET APIs. It does not create, edit, cancel, reverse, allocate, export, or generate receipts.
      </div>

      {canUseSelectedMemberHistory ? (
        <div className="formPanel">
          <div className="fieldGrid">
            <label>
              Member
              <select
                value={selectedHistoryMemberId ?? ""}
                disabled={status === "initializing" || status === "loading" || !historyMembers.length}
                onChange={(event) => updateSelectedHistoryMember(Number(event.target.value))}
              >
                {historyMembers.map((member) => (
                  <option key={member.id} value={member.id}>{member.full_name} ({member.member_code})</option>
                ))}
              </select>
            </label>
            <label>
              Scope
              <input value={selectedHistoryMember ? "Selected member in active club" : "No member selected"} readOnly />
            </label>
          </div>
        </div>
      ) : (
        <div className="notice memberHelperText">Member view loads only your own payment history. Other members' payment history is not requested.</div>
      )}

      {message && <div className="notice memberHelperText">{message}</div>}
      {(status === "initializing" || status === "loading") && <div className="notice memberHelperText">Loading payment history...</div>}
      {status === "empty" && <div className="memberEmptyState">No payment history records are available yet.</div>}

      {payments.length > 0 && (
        <div className="financeCardList">
          {payments.map((payment, index) => (
            <article className="financeStackCard" key={`${payment.payment_id ?? "payment"}-${index}`}>
              <h3>Payment Date: {financeDisplayValue(payment.payment_date)}</h3>
              <span>Payment ID: {financeDisplayValue(payment.payment_id)}</span>
              <span>Member: {financeDisplayValue(payment.member?.name)}</span>
              <span>Member Code: {financeDisplayValue(payment.member?.member_code)}</span>
              <span>Amount: {financeDisplayAmount(payment.amount)}</span>
              <span>Payment Mode: {financeDisplayValue(payment.payment_mode)}</span>
              <span>Status: {financeDisplayValue(payment.status)}</span>
              <span>Receipt: {payment.receipt?.available ? financeDisplayValue(payment.receipt.receipt_number) : "Unavailable"}</span>
              <span>Receipt Status: {financeDisplayValue(payment.receipt?.status)}</span>
              <span>Recorded By: {financeDisplayValue(payment.recorded_by?.name)}</span>
              <span>Created At: {financeDisplayValue(payment.created_at)}</span>
            </article>
          ))}
        </div>
      )}

      {meta && (
        <section className="compactPanel">
          <Toolbar title="Payment History Pages" actionLabel={`Page ${currentPage} of ${lastPage}`} />
          <div className="metricsGrid">
            <Metric label="Rows on page" value={String(payments.length)} />
            <Metric label="Per Page" value={financeDisplayValue(meta.per_page)} />
            <Metric label="Total Records" value={financeDisplayValue(meta.total)} />
          </div>
          <div className="buttonRow">
            <button
              className="secondaryButton"
              disabled={status === "loading" || currentPage <= 1}
              onClick={() => void loadPaymentHistory(historyToken, historyClubId, selectedHistoryMemberId, currentPage - 1)}
            >
              Previous Page
            </button>
            <button
              className="secondaryButton"
              disabled={status === "loading" || currentPage >= lastPage}
              onClick={() => void loadPaymentHistory(historyToken, historyClubId, selectedHistoryMemberId, currentPage + 1)}
            >
              Next Page
            </button>
          </div>
        </section>
      )}
    </section>
  );
}

function parseMemberImportPreviewRows(input: string): Record<string, string>[] {
  const lines = input
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter(Boolean);

  if (lines.length < 2) {
    return [];
  }

  const delimiter = lines[0].includes("\t") ? "\t" : ",";
  const headers = lines[0].split(delimiter).map((header) => header.trim()).filter(Boolean);

  if (!headers.length) {
    return [];
  }

  return lines.slice(1).map((line) => {
    const cells = line.split(delimiter).map((cell) => cell.trim());
    return headers.reduce<Record<string, string>>((row, header, index) => {
      row[header] = cells[index] ?? "";
      return row;
    }, {});
  });
}

function memberImportPreviewText(value: unknown): string {
  if (value === null || value === undefined || value === "") {
    return "Unavailable";
  }

  return String(value);
}

function memberImportPreviewErrors(errors?: string[] | null): string {
  if (!errors?.length) {
    return "None";
  }

  return errors.join("; ");
}

function memberImportPreviewMatches(matches?: MemberImportPreviewDuplicateMatch[] | null): string {
  if (!matches?.length) {
    return "None";
  }

  return matches
    .map((match) => {
      const field = memberImportPreviewText(match.field);
      const value = memberImportPreviewText(match.value);
      const rows = match.matched_rows?.length ? "Rows " + match.matched_rows.join(", ") : null;
      const member = match.member_name ?? match.member_code ?? match.member_id ?? null;
      return [field, value, rows, member ? "Member " + member : null].filter(Boolean).join(" - ");
    })
    .join("; ");
}

function memberImportPreviewNormalizedFields(normalized?: Record<string, string | number | boolean | null> | null): [string, string][] {
  if (!normalized) {
    return [];
  }

  return Object.entries(normalized).map(([key, value]) => [key, memberImportPreviewText(value)]);
}

function memberImportCommitErrors(errors?: string[] | null): string {
  return memberImportPreviewErrors(errors);
}

function memberImportCommitWarnings(warnings?: string[] | null): string {
  if (!warnings?.length) {
    return "None";
  }

  return warnings.join("; ");
}

function memberImportCommitStatusLabel(status?: string | null): string {
  const normalized = (status ?? "").toLowerCase();

  if (normalized === "created") {
    return "Created";
  }

  if (normalized === "skipped") {
    return "Skipped";
  }

  if (normalized === "failed") {
    return "Failed";
  }

  return memberImportPreviewText(status);
}

function MemberImportPreview({ role }: { role: Role }) {
  const canUseMemberImportPreview = role === "president" || role === "secretary" || role === "treasurer";
  const [status, setStatus] = useState<MemberImportPreviewStatus>(canUseMemberImportPreview ? "initializing" : "ready");
  const [message, setMessage] = useState(
    canUseMemberImportPreview
      ? "Loading member import preview access context..."
      : "Member import preview is available only to authorized officers."
  );
  const [previewToken, setPreviewToken] = useState<string | null>(null);
  const [previewClubId, setPreviewClubId] = useState<number | null>(null);
  const [pasteRows, setPasteRows] = useState("Member Code\tFull Name\tPhone\tEmail\nM-001\tMember Name\t9999999999\tmember@example.test");
  const [preview, setPreview] = useState<MemberImportPreviewPayload | null>(null);
  const [previewedRows, setPreviewedRows] = useState<Record<string, string>[]>([]);
  const [commitStatus, setCommitStatus] = useState<MemberImportCommitStatus>("idle");
  const [commitMessage, setCommitMessage] = useState("Run preview first. Commit is offered only for eligible new members after backend preview.");
  const [commitConfirmed, setCommitConfirmed] = useState(false);
  const [commitResult, setCommitResult] = useState<MemberImportCommitPayload | null>(null);

  function resetMemberImportCommitState(messageText = "Run preview first. Commit is offered only for eligible new members after backend preview.") {
    setPreviewedRows([]);
    setCommitStatus("idle");
    setCommitMessage(messageText);
    setCommitConfirmed(false);
    setCommitResult(null);
  }

  useEffect(() => {
    let cancelled = false;

    async function loadMemberImportPreviewContext() {
      if (!canUseMemberImportPreview) {
        setPreviewToken(null);
        setPreviewClubId(null);
        setPreview(null);
        resetMemberImportCommitState("Commit behavior is hidden from Normal Member role and no commit API call is made.");
        setStatus("ready");
        setMessage("Member import preview is hidden from Normal Member role and no preview API call is made.");
        return;
      }

      setStatus("initializing");
      setMessage("Loading member import preview access context...");
      setPreview(null);
      resetMemberImportCommitState();

      try {
        const login = LOCAL_PAYMENT_RECORDING_LOGINS[role as Exclude<Role, "member">];

        const loginResponse = await fetch(API_BASE_URL + "/auth/login", {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json"
          },
          body: JSON.stringify(login)
        });

        if (!loginResponse.ok) {
          throw new Error(loginResponse.status === 401 ? "Please sign in again before previewing member rows." : "Member import preview login failed.");
        }

        const loginPayload = await loginResponse.json();
        const token = loginPayload.token as string | undefined;

        if (!token) {
          throw new Error("Member import preview token missing.");
        }

        const meResponse = await fetch(API_BASE_URL + "/auth/me", {
          headers: {
            Accept: "application/json",
            Authorization: "Bearer " + token
          }
        });

        if (!meResponse.ok) {
          throw new Error(meResponse.status === 403 ? "This role is not authorized for member import preview." : "No active club context is available for member import preview.");
        }

        const mePayload = await meResponse.json();
        const memberships = mePayload.club_memberships as BackendClubMembership[] | undefined;
        const loadedClubId = memberships?.[0]?.club?.id;

        if (typeof loadedClubId !== "number") {
          throw new Error("No active club context is available for member import preview.");
        }

        if (!cancelled) {
          setPreviewToken(token);
          setPreviewClubId(loadedClubId);
          setStatus("ready");
          setMessage("Ready to run a preview through the existing backend dry-run API.");
        }
      } catch (error) {
        if (!cancelled) {
          setPreviewToken(null);
          setPreviewClubId(null);
          setPreview(null);
          resetMemberImportCommitState("Commit is unavailable until officer access and active club context are ready.");
          setStatus("error");
          setMessage(error instanceof Error ? error.message : "Member import preview is unavailable right now.");
        }
      }
    }

    loadMemberImportPreviewContext();

    return () => {
      cancelled = true;
    };
  }, [canUseMemberImportPreview, role]);

  async function runMemberImportPreview(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    resetMemberImportCommitState();

    if (!canUseMemberImportPreview) {
      setStatus("error");
      setMessage("Normal Member role cannot use member import preview.");
      return;
    }

    if (!previewToken || !previewClubId) {
      setStatus("error");
      setMessage("Member import preview cannot run without an active club context.");
      return;
    }

    const rows = parseMemberImportPreviewRows(pasteRows);

    if (!rows.length) {
      setPreview(null);
      setStatus("empty");
      setMessage("Enter a header row and at least one member row before running preview.");
      return;
    }

    setStatus("loading");
    setMessage("Running dry-run preview through the existing backend API...");

    try {
      const response = await fetch(API_BASE_URL + "/clubs/" + previewClubId + "/members/import-preview", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          Authorization: "Bearer " + previewToken
        },
        body: JSON.stringify({ rows })
      });

      if (!response.ok) {
        let failureMessage = "Member import preview is unavailable right now.";

        try {
          const payload = await response.json() as ApiErrorPayload;
          failureMessage = payload.message ?? failureMessage;
        } catch {
          failureMessage = "Member import preview is unavailable right now.";
        }

        throw new Error(response.status === 401 ? "Please sign in again before previewing member rows." : response.status === 403 ? "This role is not authorized for member import preview." : failureMessage);
      }

      const payload = await response.json() as MemberImportPreviewApiResponse;
      const importPreview = payload.import_preview ?? null;
      const previewRows = importPreview?.rows ?? [];

      setPreview(importPreview);
      setPreviewedRows(rows);
      setStatus(previewRows.length ? "success" : "empty");
      setMessage(previewRows.length ? "Preview completed. No member records were saved." : "Preview returned no rows.");
      setCommitStatus(previewRows.length ? "confirming" : "idle");
      setCommitMessage(previewRows.length ? "Review row eligibility, validation errors, duplicate warnings, and existing-member match warnings before commit." : "Run preview first. Commit is offered only for eligible new members after backend preview.");
    } catch (error) {
      setPreview(null);
      resetMemberImportCommitState("Commit is unavailable because preview did not complete successfully.");
      setStatus("error");
      setMessage(error instanceof Error ? error.message : "Member import preview is unavailable right now.");
    }
  }

  async function runMemberImportCommit() {
    if (!canUseMemberImportPreview) {
      setCommitStatus("error");
      setCommitMessage("Normal Member role cannot use member import commit.");
      return;
    }

    if (!previewToken || !previewClubId) {
      setCommitStatus("error");
      setCommitMessage("Commit cannot run without an active club context.");
      return;
    }

    if (status !== "success" || !preview || !previewedRows.length) {
      setCommitStatus("error");
      setCommitMessage("Run preview successfully before commit is offered.");
      return;
    }

    if (!commitConfirmed) {
      setCommitStatus("error");
      setCommitMessage("Confirm commit before calling the backend commit endpoint.");
      return;
    }

    setCommitStatus("loading");
    setCommitMessage("Submitting current preview rows to the backend commit endpoint...");
    setCommitResult(null);

    try {
      const response = await fetch(API_BASE_URL + "/clubs/" + previewClubId + "/members/" + "import-" + "commit", {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          Authorization: "Bearer " + previewToken
        },
        body: JSON.stringify({ rows: previewedRows })
      });

      if (!response.ok) {
        let failureMessage = "Member import commit is unavailable right now.";

        try {
          const payload = await response.json() as ApiErrorPayload;
          failureMessage = payload.message ?? failureMessage;
        } catch {
          failureMessage = "Member import commit is unavailable right now.";
        }

        throw new Error(response.status === 401 ? "Please sign in again before committing eligible new members." : response.status === 403 ? "This role is not authorized for member import commit." : failureMessage);
      }

      const payload = await response.json() as MemberImportCommitApiResponse;
      const importCommit = payload.import_commit ?? null;

      setCommitResult(importCommit);
      setCommitStatus("success");
      setCommitMessage("Commit completed. Review Created, Skipped, and Failed rows below.");
      setCommitConfirmed(false);
    } catch (error) {
      setCommitResult(null);
      setCommitStatus("error");
      setCommitMessage(error instanceof Error ? error.message : "Member import commit is unavailable right now.");
    }
  }

  if (!canUseMemberImportPreview) {
    return (
      <section className="stack">
        <Toolbar title="Member Import Preview" actionLabel="Officer only" />
        <div className="notice">
          Member import preview is hidden from Normal Member role. No preview API call is made and no import preview data is loaded.
        </div>
      </section>
    );
  }

  const summary = preview?.summary ?? null;
  const previewRows = preview?.rows ?? [];
  const commitSummary = commitResult?.summary ?? null;
  const commitRows = commitResult?.rows ?? [];
  const canOfferCommit = status === "success" && previewRows.length > 0 && previewedRows.length > 0 && commitStatus !== "loading";

  return (
    <section className="stack">
      <Toolbar title="Member Import Preview" actionLabel="Preview first" />
      <div className="notice">
        Preview only. This screen sends manual rows to the existing backend dry-run endpoint, does not save changes, and does not silently overwrite existing member data.
      </div>
      <div className="notice memberHelperText">
        No Excel upload, parser package, file storage, create/update/delete action, finance import, ledger change, payment change, receipt change, export, or reminder workflow is available here.
      </div>
      <div className="notice memberHelperText">
        Commit is separate from Preview. It creates eligible new members only, does not overwrite existing members, does not update existing members, and does not delete members.
      </div>

      <form className="formPanel" onSubmit={runMemberImportPreview}>
        <label>
          Paste spreadsheet-like rows
          <textarea
            value={pasteRows}
            onChange={(event) => {
              setPasteRows(event.target.value);
              setPreview(null);
              setStatus("ready");
              setMessage("Rows changed. Run preview again before commit is offered.");
              resetMemberImportCommitState("Rows changed. Run preview again before commit is offered.");
            }}
            rows={7}
            placeholder="Member Code\tFull Name\tPhone\tEmail"
          />
        </label>
        <div className="buttonRow">
          <button disabled={status === "initializing" || status === "loading"}>Run preview</button>
        </div>
      </form>

      {message && <div className="notice memberHelperText">{message}</div>}
      {(status === "initializing" || status === "loading") && <div className="notice memberHelperText">Loading member import preview...</div>}
      {status === "empty" && <div className="memberEmptyState">No preview rows are available yet.</div>}

      {previewRows.length > 0 && (
        <section className="compactPanel">
          <Toolbar title="Commit Review" actionLabel="Preview required" />
          <div className="notice memberHelperText">
            Review row-level eligibility, validation errors, duplicate warnings, and existing-member match warnings before using Confirm commit. The backend re-checks every row at commit time.
          </div>
          <label className="toggleLine">
            <input
              type="checkbox"
              checked={commitConfirmed}
              onChange={(event) => {
                setCommitConfirmed(event.target.checked);
                if (event.target.checked) {
                  setCommitStatus("confirming");
                  setCommitMessage("Confirm commit selected. Use the commit button only for eligible new members.");
                }
              }}
            />
            I understand this creates eligible new members only and will not overwrite, update, or delete existing members.
          </label>
          <div className="buttonRow">
            <button
              type="button"
              disabled={!canOfferCommit || !commitConfirmed}
              onClick={runMemberImportCommit}
            >
              {"Commit eligible new " + "members"}
            </button>
          </div>
          {commitMessage && <div className="notice memberHelperText">{commitMessage}</div>}
          {commitStatus === "loading" && <div className="notice memberHelperText">Loading member import commit...</div>}
        </section>
      )}

      {summary && (
        <section className="compactPanel">
          <Toolbar title="Preview Summary" actionLabel={preview?.dry_run ? "Dry run" : "Preview"} />
          <div className="metricsGrid">
            <Metric label="Total Rows" value={memberImportPreviewText(summary.total_rows)} />
            <Metric label="Accepted Rows" value={memberImportPreviewText(summary.accepted_rows)} />
            <Metric label="Rejected Rows" value={memberImportPreviewText(summary.rejected_rows)} />
            <Metric label="Duplicate Rows" value={memberImportPreviewText(summary.duplicate_rows)} />
            <Metric label="Possible Matches" value={memberImportPreviewText(summary.possible_existing_match_rows)} />
          </div>
          <div className="notice memberHelperText">
            Committed: {preview?.committed ? "Yes" : "No"}. Existing-member matches are preview information only.
          </div>
        </section>
      )}

      {previewRows.length > 0 && (
        <div className="financeCardList">
          {previewRows.map((row, index) => (
            <article className="financeStackCard" key={`${row.row_number ?? "row"}-${index}`}>
              <h3>Row {memberImportPreviewText(row.row_number)}</h3>
              <span>Validity: {row.valid ? "Accepted" : "Needs review"}</span>
              <span>Intent: {memberImportPreviewText(row.intent)}</span>
              <span>Validation Errors: {memberImportPreviewErrors(row.errors)}</span>
              <span>Duplicate Warnings: {memberImportPreviewMatches(row.duplicate_matches?.batch)}</span>
              <span>Existing-Member Match Warnings: {memberImportPreviewMatches(row.duplicate_matches?.existing)}</span>
              {memberImportPreviewNormalizedFields(row.normalized).length > 0 ? (
                <div className="tableLike">
                  {memberImportPreviewNormalizedFields(row.normalized).map(([field, value]) => (
                    <div className="staticRow" key={`${row.row_number}-${field}`}>
                      <strong>{field}</strong>
                      <span>{value}</span>
                    </div>
                  ))}
                </div>
              ) : (
                <span>Normalized Fields: Unavailable</span>
              )}
            </article>
          ))}
        </div>
      )}

      {commitSummary && (
        <section className="compactPanel">
          <Toolbar title="Commit Summary" actionLabel={commitResult?.committed ? "Committed" : "Unavailable"} />
          <div className="metricsGrid">
            <Metric label="Total Rows" value={memberImportPreviewText(commitSummary.total_rows)} />
            <Metric label="Created" value={memberImportPreviewText(commitSummary.created_rows)} />
            <Metric label="Skipped" value={memberImportPreviewText(commitSummary.skipped_rows)} />
            <Metric label="Failed" value={memberImportPreviewText(commitSummary.failed_rows)} />
          </div>
          <div className="notice memberHelperText">
            Created rows are new member records. Skipped and Failed rows require review and are not implied to be created.
          </div>
        </section>
      )}

      {commitRows.length > 0 && (
        <div className="financeCardList">
          {commitRows.map((row, index) => (
            <article className="financeStackCard" key={`commit-${row.row_number ?? "row"}-${index}`}>
              <h3>Commit Row {memberImportPreviewText(row.row_number)}</h3>
              <span>Status: {memberImportCommitStatusLabel(row.status)}</span>
              <span>Member ID: {memberImportPreviewText(row.member_id)}</span>
              <span>Errors: {memberImportCommitErrors(row.errors)}</span>
              <span>Warnings: {memberImportCommitWarnings(row.warnings)}</span>
              {memberImportPreviewNormalizedFields(row.normalized).length > 0 ? (
                <div className="tableLike">
                  {memberImportPreviewNormalizedFields(row.normalized).map(([field, value]) => (
                    <div className="staticRow" key={`commit-${row.row_number}-${field}`}>
                      <strong>{field}</strong>
                      <span>{value}</span>
                    </div>
                  ))}
                </div>
              ) : (
                <span>Normalized Fields: Unavailable</span>
              )}
            </article>
          ))}
        </div>
      )}
    </section>
  );
}

function ReceiptPreview({ title, rows }: { title: string; rows: [string, string][] }) {
  return (
    <section className="compactPanel receiptPreviewPanel">
      <Toolbar title={title} actionLabel="Prototype only" />
      <div className="receiptPreviewList">
        {rows.map(([label, value], index) => (
          <div className="receiptPreviewLine" key={`${label}-${index}`}>
            <span>{label}:</span>
            <strong>{value}</strong>
          </div>
        ))}
      </div>
    </section>
  );
}

function OutstandingDues({ setScreen }: { setScreen: (screen: Screen) => void }) {
  return (
    <section className="stack">
      <Toolbar title="Outstanding Dues" actionLabel="Treasurer working list" />
      <div className="financeCardList">
        {outstandingDues.map((due) => (
          <article className="financeStackCard" key={due.id}>
            <h3>Member: {due.member}</h3>
            <span>Due Type: {due.label}</span>
            <span>Due Date: {due.dueDate}</span>
            <span>Amount: {money(due.amount)}</span>
            <span>Status: {due.status}</span>
            <button className="filterButton compactActionButton" onClick={() => setScreen("record-member-payment")}>Record Payment</button>
          </article>
        ))}
      </div>
    </section>
  );
}

function CollectionSummary({ detail, setDetail }: { detail: CollectionDetail; setDetail: (detail: CollectionDetail) => void }) {
  const cards: { key: Exclude<CollectionDetail, null>; label: string; value: string }[] = [
    { key: "today", label: "Today’s Collection", value: money(8500) },
    { key: "month", label: "This Month’s Collection", value: money(42500) },
    { key: "pending", label: "Pending Dues", value: money(5750) },
    { key: "fellowship", label: "Fellowship Pending", value: money(5000) },
    { key: "split", label: "Cash / Bank / UPI Split", value: "3 modes" }
  ];

  return (
    <section className="stack">
      <Toolbar title="Collection Summary" actionLabel="Dummy finance snapshot" />
      <div className="reportGrid">
        {cards.map((card) => (
          <button
            className={detail === card.key ? "reportCard activeReportCard" : "reportCard"}
            key={card.key}
            onClick={() => setDetail(card.key)}
          >
            <span>{card.label}</span>
            <strong>{card.value}</strong>
          </button>
        ))}
      </div>
      {detail === null && <div className="notice">Tap a collection card to view dummy receipt, dues, fellowship, or mode-wise details.</div>}
      {detail === "today" && <CollectionDetailPanel title="Today’s Collection">{todayCollectionRows()}</CollectionDetailPanel>}
      {detail === "month" && <CollectionDetailPanel title="This Month’s Collection">{monthlyCollectionRows()}</CollectionDetailPanel>}
      {detail === "pending" && <CollectionDetailPanel title="Pending Dues">{pendingDuesRows()}</CollectionDetailPanel>}
      {detail === "fellowship" && <CollectionDetailPanel title="Fellowship Pending">{fellowshipPendingRows()}</CollectionDetailPanel>}
      {detail === "split" && <CollectionDetailPanel title="Cash / Bank / UPI Split">{modeSplitRows()}</CollectionDetailPanel>}
    </section>
  );
}

function CollectionDetailPanel({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="compactPanel">
      <Toolbar title={title} actionLabel="Dummy summary" />
      <div className="tableLike">{children}</div>
    </div>
  );
}

function todayCollectionRows() {
  const rows = [
    ["RCP-001", "Lion Ajith Kumar - Member", money(3000), "UPI", "03 Jun 2026 10:15", "Lion Treasurer User"],
    ["RCP-002", "Lion Bijesh", money(2500), "Cash", "03 Jun 2026 11:10", "Lion Treasurer User"],
    ["RCP-003", "Lion Secretary User", money(3000), "Bank", "03 Jun 2026 12:25", "Lion Treasurer User"]
  ];
  return rows.map((row) => (
    <div className="staticRow collectionDetailRow" key={row[0]}>
      {row.map((item, index) => <span key={`${row[0]}-${index}`}>{item}</span>)}
    </div>
  ));
}

function monthlyCollectionRows() {
  const rows = [
    ["01 Jun", money(9500), "8 members", "Cash 2 / Bank 3 / UPI 3"],
    ["02 Jun", money(14250), "11 members", "Cash 4 / Bank 2 / UPI 5"],
    ["03 Jun", money(8500), "3 members", "Cash 1 / Bank 1 / UPI 1"]
  ];
  return rows.map((row) => (
    <div className="staticRow collectionDetailRow" key={row[0]}>
      {row.map((item, index) => <span key={`${row[0]}-${index}`}>{item}</span>)}
    </div>
  ));
}

function pendingDuesRows() {
  return outstandingDues.map((due) => (
    <div className="staticRow financeDueRow" key={due.id}>
      <strong>{due.member}</strong>
      <span>{due.label}</span>
      <span>{due.dueDate}</span>
      <span>{money(due.amount)}</span>
      <span className="tag">{due.status}</span>
      <button className="filterButton">Record Payment</button>
    </div>
  ));
}

function fellowshipPendingRows() {
  const rows = [
    ["June 2026", "Lion Secretary User", money(2500), money(0), money(2500), "Pending"],
    ["June 2026", "Lion Treasurer User", money(2500), money(0), money(2500), "Pending"],
    ["June 2026", "Lion Bijesh", money(2500), money(2500), money(0), "Paid"]
  ];
  return rows.map((row) => (
    <div className="staticRow collectionDetailRow" key={`${row[0]}-${row[1]}`}>
      {row.map((item, index) => <span key={`${row[0]}-${row[1]}-${index}`}>{item}</span>)}
    </div>
  ));
}

function modeSplitRows() {
  const rows = [
    ["Cash total", money(6000), "RCP-002, RCP-006"],
    ["Bank total", money(18000), "RCP-003, RCP-007, RCP-009"],
    ["UPI total", money(18500), "RCP-001, RCP-004, RCP-005"]
  ];
  return rows.map((row) => (
    <div className="staticRow collectionDetailRow" key={row[0]}>
      {row.map((item, index) => <span key={`${row[0]}-${index}`}>{item}</span>)}
    </div>
  ));
}

function FellowshipPayments({ view = "Current Month", setScreen }: { view?: FellowshipPaymentView; setScreen: (screen: Screen) => void }) {
  const records = [
    { sponsor: "Lion Bijesh", month: "June 2026 Fellowship", amount: 2500, status: "Paid", receipt: "RCP-010" },
    { sponsor: "Lion Secretary User", month: "June 2026 Fellowship", amount: 2500, status: "Pending", receipt: "Not issued" },
    { sponsor: "Lion Treasurer User", month: "June 2026 Fellowship", amount: 2500, status: "Pending", receipt: "Not issued" }
  ];
  const visibleRecords = records.filter((record) => {
    if (view === "Paid Sponsors") return record.status === "Paid";
    if (view === "Pending Sponsors") return record.status === "Pending";
    return true;
  });
  const title = view === "Current Month" ? "Fellowship Payments" : view;

  return (
    <section className="stack">
      <Toolbar title={title} actionLabel="Sponsor payment records" />
      <div className="selectedSponsorGrid">
        {visibleRecords.map((record) => (
          <article className="selectedSponsorCard" key={`${record.month}-${record.sponsor}`}>
            <div>
              <strong>Sponsor: {record.sponsor}</strong>
              <span>Fellowship Month: {record.month}</span>
              <span>Amount: {money(record.amount)}</span>
              <span>Payment Status: {record.status}</span>
              <span>Receipt: {record.receipt}</span>
            </div>
            <button className="filterButton" onClick={() => record.status === "Paid" ? undefined : setScreen("record-member-payment")}>
              {record.status === "Paid" ? "View Receipt" : "Record Payment"}
            </button>
          </article>
        ))}
      </div>
    </section>
  );
}

function EventsMeetings({
  role,
  member,
  selectedSponsors,
  setScreen,
  selectedSection
}: {
  role: Role;
  member: Member;
  selectedSponsors: string[];
  setScreen: (screen: Screen) => void;
  selectedSection?: EventMeetingSection | null;
}) {
  const visible = role === "member" ? eventsMeetings.filter((item) => item.visibility === "All Members") : eventsMeetings;
  const memberIsFellowshipSponsor = selectedSponsors.includes(member.name) || selectedSponsors.includes(member.dashboardName);
  const sections: EventMeetingSection[] = selectedSection ? [selectedSection] : ["Upcoming Events", "Upcoming Meetings", "Past Events", "Meeting Notices"];
  const emptyMessages: Record<EventMeeting["section"], string> = {
    "Upcoming Events": "No events available at the moment.",
    "Upcoming Meetings": "No upcoming meetings announced.",
    "Past Events": "No past events available at the moment.",
    "Meeting Notices": "No meeting notices created yet. Meeting reminder sending will be available after a notice is created."
  };
  const memberEmptyMessages: Record<EventMeeting["section"], string> = {
    ...emptyMessages,
    "Meeting Notices": "No meeting notices available yet."
  };
  const displaySectionTitle = (section: EventMeetingSection) => {
    if (role === "member" && section === "Upcoming Events") return "Upcoming Club Events";
    return section;
  };
  const title = selectedSection ? displaySectionTitle(selectedSection) : "Events & Meetings";
  const selectedSectionItems = selectedSection ? visible.filter((item) => item.section === selectedSection) : [];
  const badge = role === "member"
    ? "Available to you"
    : selectedSection === "Meeting Notices"
      ? "Meeting notice management"
      : "Management view";
  return (
    <section className="stack">
      <Toolbar title={title} actionLabel={badge} />
      {selectedSection && <p className="breadcrumbText">Operations → Events & Meetings → {selectedSection}</p>}
      {role !== "member" && (
        <div className="buttonRow">
          {selectedSection === "Meeting Notices" && selectedSectionItems.length === 0 ? (
            <button className="secondaryButton">Create Meeting Notice</button>
          ) : (
            <button className="secondaryButton" onClick={() => setScreen("notifications-reminders")}>Send Meeting Reminder</button>
          )}
        </div>
      )}
      {sections.map((section) => {
        const sectionItems = visible.filter((item) => item.section === section);
        const sectionTitle = displaySectionTitle(section);
        const emptyMessage = role === "member" ? memberEmptyMessages[section] : emptyMessages[section];
        return (
          <div className="compactPanel" key={section}>
            <h2>{sectionTitle}</h2>
            <div className={role === "member" ? "memberEventList" : "tableLike"}>
              {sectionItems.map((item) => role === "member" ? (
                <article className="memberEventCard" key={item.title}>
                  <div>
                    <h3>{item.title}</h3>
                    <p>{item.date}{item.time ? ` · ${item.time}` : ""}</p>
                    <span>Venue: {item.venue}</span>
                    <span>Audience: {item.title === "Monthly Family Fellowship" ? "Members & Family" : "All Members"}</span>
                    {item.title === "Monthly Family Fellowship" && memberIsFellowshipSponsor && (
                      <span>You are a sponsor for this fellowship. Your contribution: {money(500)}. Payment status: Pending.</span>
                    )}
                  </div>
                  <span className="tag">{item.status === "Published" ? "Upcoming" : item.status}</span>
                </article>
              ) : (
                <article className="adminEventCard" key={item.title}>
                  <div>
                    <h3>{item.title}</h3>
                    <p>{item.date}{item.time ? ` · ${item.time}` : ""}</p>
                  </div>
                  <span>Venue: {item.venue}</span>
                  <span>Audience: {item.visibility}</span>
                  <span className="tag">{item.status}</span>
                  <button className="filterButton">View details</button>
                </article>
              ))}
              {sectionItems.length === 0 && <div className="memberEmptyState">{role === "member" || section === "Meeting Notices" ? emptyMessage : "No items in this section."}</div>}
            </div>
          </div>
        );
      })}
    </section>
  );
}

function Announcements({ role, member, selectedSponsors }: { role: Role; member: Member; selectedSponsors: string[] }) {
  const memberIsSponsor = selectedSponsors.includes(member.name) || selectedSponsors.includes(member.dashboardName);
  const visibleAnnouncements = role === "member"
    ? announcements.filter((item) => item.status === "Published" && (item.audience === "Members" || (item.audience === "Sponsors" && memberIsSponsor)))
    : announcements;

  if (role === "member") {
    return (
      <section className="stack">
        <Toolbar title="Announcements" actionLabel="Latest Announcements" />
        <div className="memberAnnouncementList">
          {visibleAnnouncements.map((item) => (
            <article className="memberAnnouncementCard" key={item.title}>
              <div>
                <h2>{item.title}</h2>
                <p>{item.date} · {item.priority === "High" ? "High Priority" : item.priority}</p>
                <span>{item.preview}</span>
              </div>
              <button className="filterButton">View Details</button>
            </article>
          ))}
          {visibleAnnouncements.length === 0 && <div className="memberEmptyState">No new announcements at the moment.</div>}
        </div>
      </section>
    );
  }

  return (
    <section className="stack">
      <Toolbar title="Announcements" actionLabel="Compact communication list" />
      <div className="announcementList">
        {announcements.map((item) => (
          <article className="announcementCard" key={item.title}>
            <div>
              <h2>{item.title}</h2>
              <span>Visible to: {item.audience}</span>
            </div>
            <span className="tag">{item.priority}</span>
            <span>{item.status}</span>
            <span>{item.date}</span>
            <div className="miniActions">
              <button className="filterButton">View</button>
              <button className="filterButton">Edit</button>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}

function Documents({ role }: { role: Role }) {
  if (role === "member") {
    const visibleDocuments = documents.filter((item) => item.visibility === "Members");
    return (
      <section className="stack">
        <Toolbar title="Documents" actionLabel="Documents available to you" />
        <div className="documentGrid">
          {visibleDocuments.map((item) => (
            <article className="documentCard" key={item.title}>
              <div>
                <h2>{item.title}</h2>
                <p>{item.category}</p>
                <span>{item.date}</span>
              </div>
              <button className="filterButton">Open Document</button>
            </article>
          ))}
        </div>
      </section>
    );
  }

  return (
    <section className="stack">
      <Toolbar title="Documents" actionLabel="Role-based edit" />
      <div className="tableLike">
        {documents.map((item) => (
          <div className="staticRow documentAdminRow" key={item.title}>
            <strong>{item.title}</strong>
            <span>{item.category}</span>
            <span>{item.date}</span>
            <span className="tag">{item.visibility}</span>
            <button className="filterButton">Edit</button>
          </div>
        ))}
      </div>
    </section>
  );
}

function ListScreen({ title, items, role }: { title: string; items: string[]; role: Role }) {
  return (
    <section className="stack">
      <Toolbar title={title} actionLabel={role === "member" ? "View permitted" : "Role-based edit"} />
      <div className="tableLike">{items.map((item) => <div className="staticRow" key={item}><strong>{item}</strong><span className="tag">{role === "member" ? "Visible" : "Editable"}</span></div>)}</div>
    </section>
  );
}

function ProjectsActivities({ setScreen }: { setScreen: (screen: Screen) => void }) {
  return (
    <section className="stack">
      <Toolbar title="Projects / Activities" actionLabel="V1 activity reporting" />
      <div className="filterBar">
        {["This month", "Pending report", "Completed", "By category"].map((filter) => <button className="filterButton" key={filter}>{filter}</button>)}
      </div>
      <div className="actionGrid">
        <Action title="Create from annual plan" body="Auto-fill month, category and lead committee." onClick={() => undefined} />
        <Action title="Use activity template" body="Service, health, education or environment template." onClick={() => undefined} />
        <Action title="Upload photos/report" body="Attach placeholders and mark report status." onClick={() => undefined} />
        <Action title="Batch update status" body="Mark planned, completed or cancelled." onClick={() => undefined} />
      </div>
      <div className="activityGrid">
        {activities.map((activity) => <ActivityCard key={activity.name} activity={activity} />)}
      </div>
      <button onClick={() => setScreen("reports-tools-module")}>Open Activity Reports</button>
    </section>
  );
}

function ActivityCard({ activity }: { activity: Activity }) {
  return (
    <article className="activityCard">
      <div className="toolbar">
        <h2>{activity.name}</h2>
        <span className="tag">{activity.status}</span>
      </div>
      <div className="fieldGrid">
        <Field label="Activity date" value={activity.date} />
        <Field label="Month" value={activity.month} />
        <Field label="Category" value={activity.category} />
        <Field label="Venue/location" value={activity.venue} />
        <Field label="Lead member / committee" value={activity.lead} />
        <Field label="People served" value={String(activity.peopleServed)} />
        <Field label="Estimated budget" value={money(activity.estimatedBudget)} />
        <Field label="Actual expense" value={money(activity.actualExpense)} />
        <Field label="Sponsors/contributors" value={activity.sponsors} />
        <Field label="Photos" value={activity.photos} />
        <Field label="Report document" value={activity.reportDocument} />
      </div>
      <p>{activity.description}</p>
    </article>
  );
}

function Celebrations({ role, setScreen }: { role: Role; setScreen: (screen: Screen) => void }) {
  const items = celebrationsForRole(role);
  const filters = ["Today", "This week", "This month", "Birthdays", "Anniversaries"];
  if (role === "member") return <MemberCelebrations />;

  return (
    <section className="stack">
      <Toolbar title="Celebrations" actionLabel="Official greeting tracking" />
      <div className="buttonRow">
        <button className="secondaryButton" onClick={() => setScreen("notifications-reminders")}>Send Greeting Reminder</button>
      </div>
      <div className="filterBar">{filters.map((filter) => <button className="filterButton" key={filter}>{filter}</button>)}</div>
      <div className="tableLike">
        {items.map((item) => (
          <div className="staticRow celebrationAdminRow" key={`${item.name}-${item.type}-${item.date}`}>
            <Avatar name={item.name} />
            <strong>{item.name}</strong>
            <span>{item.type}</span>
            <span>{item.date}</span>
            <span>{item.contact}</span>
            <span className="tag">{item.wishStatus}</span>
            <button className="filterButton">Mark as Sent</button>
          </div>
        ))}
      </div>
    </section>
  );
}

function MemberCelebrations() {
  const sections = memberCelebrationSections();
  return (
    <section className="stack">
      <Toolbar title="Celebrations" actionLabel="Member wishing" />
      <section className="compactPanel">
        <Toolbar title="Today’s Celebrations" actionLabel={`${sections.today.length} today`} />
        <MemberCelebrationList items={sections.today} emptyText="No celebrations today." />
      </section>
      <section className="compactPanel">
        <Toolbar title="Recently Completed Celebrations" actionLabel="Last 1–2 days" />
        <MemberCelebrationList items={sections.recent} emptyText="No recent celebrations." />
      </section>
    </section>
  );
}

function MemberCelebrationList({
  items,
  emptyText
}: {
  items: { name: string; type: string; status: string; phone?: string }[];
  emptyText: string;
}) {
  if (items.length === 0) return <div className="memberEmptyState">{emptyText}</div>;

  return (
    <div className="memberCelebrationList">
      {items.map((item) => (
        <article className="memberCelebrationCard" key={`${item.name}-${item.type}-${item.status}`}>
          <div>
            <strong>{item.name}</strong>
            <span>{item.type} · {item.status}</span>
          </div>
          <div className="miniActions">
            {item.phone && <button className="filterButton">Call</button>}
            {item.phone && <button className="filterButton">WhatsApp</button>}
            <button className="filterButton">Wish</button>
          </div>
        </article>
      ))}
    </div>
  );
}

function DonorConsent({ member }: { member: Member }) {
  return (
    <section className="formPanel">
      <label>Blood group<input value={member.bloodGroup} readOnly /></label>
      <label>Consent to donate<select defaultValue={member.donorConsent ? "yes" : "no"}><option value="yes">Yes</option><option value="no">No</option></select></label>
      <label>Show contact to authorized users<select defaultValue={member.consentContact ? "yes" : "no"}><option value="yes">Yes</option><option value="no">No</option></select></label>
      <label>Availability status<select defaultValue="available"><option value="available">Available if contacted</option><option value="not-now">Not available now</option></select></label>
      <button>Save Consent Settings</button>
    </section>
  );
}

function DonorSearch({ bloodGroup, setBloodGroup, allowedDonors }: { bloodGroup: string; setBloodGroup: (group: string) => void; allowedDonors: Member[] }) {
  return (
    <section className="stack">
      <div className="notice">Authorized donor search is for President and Secretary in V1. Results include consented donors only.</div>
      <label className="compactControl">Required blood group<select value={bloodGroup} onChange={(event) => setBloodGroup(event.target.value)}>{["O+", "A+", "B+", "AB+", "O-", "A-", "B-", "AB-"].map((group) => <option key={group}>{group}</option>)}</select></label>
      <div className="tableLike">
        {allowedDonors.length === 0 && <div className="staticRow">No consented donors for this group.</div>}
        {allowedDonors.map((donor) => <div key={donor.id} className="staticRow"><Avatar name={donor.name} /><strong>{donor.name}</strong><span>{donor.bloodGroup}</span><span>{donor.consentContact ? donor.phone : "Contact hidden"}</span></div>)}
      </div>
    </section>
  );
}

function Reports() {
  const reportCategories = [
    "Member Reports",
    "Finance / Ledger Reports",
    "Fellowship Reports",
    "Events & Meetings Reports",
    "Club Activity / Service Reports",
    "Blood Donor Consent Report",
    "Document Reports",
    "Celebration Reports",
    "Monthly Birthday Report",
    "Monthly Anniversary Report",
    "Yearly Birthday/Anniversary Calendar"
  ];
  return (
    <section className="stack">
      <div className="reportGrid">{reportCategories.map((name) => <button className="reportCard" key={name}><span>Report category</span><strong>{name}</strong></button>)}</div>
      <div className="compactPanel">
        <Toolbar title="Club Activity / Service Reports" actionLabel="V1 basic views" />
        <div className="actionGrid">
          <Action title="Monthly Activity Report" body="Month-wise activity list, people served and expenses." onClick={() => undefined} />
          <Action title="Yearly Consolidated Activity Report" body="Annual activity summary for club review." onClick={() => undefined} />
          <Action title="Category-wise Activity Report" body="Education, health, environment and service categories." onClick={() => undefined} />
        </div>
        <div className="activityGrid">{activities.map((activity) => <ActivityCard key={activity.name} activity={activity} />)}</div>
      </div>
    </section>
  );
}

function Settings({ setScreen }: { setScreen: (screen: Screen) => void }) {
  const roles = [
    ["President", "Full club-level visibility; user and role management; approval rights; fellowship and reports oversight; finance visibility."],
    ["Secretary", "Meetings/events, announcements, documents, projects/activities, reports view; no payment editing by default."],
    ["Treasurer", "Ledger, dues, payments, receipts, fellowship payment status, finance reports, member records visibility."],
    ["Board Member", "View assigned records and reports where allowed; no finance editing."],
    ["Committee Chair", "Manage assigned projects/activities and view assigned documents/events."],
    ["Member", "Own profile, own dues, my fellowship, events/announcements/documents, digital card, consent settings."],
    ["Auditor / Read-only", "Read-only reports and audit visibility where approved; no edit permissions."],
    ["Platform Super Admin - future SaaS internal only", "Future platform role for multi-tenant operations; not a normal LC Beenachi dashboard role."]
  ];
  return (
    <section className="stack">
      <Toolbar title="Settings / Roles" actionLabel="President" />
      <div className="buttonRow">
        <button onClick={() => setScreen("dashboard")}>Back to Dashboard</button>
        <button className="secondaryButton" onClick={() => setScreen("members")}>Back to Menu Area</button>
      </div>
      <div className="permissionGrid">
        {roles.map(([title, summary]) => (
          <div key={title} className="permissionItem">
            <strong>{title}</strong>
            <span>{summary}</span>
          </div>
        ))}
      </div>
      <div className="compactPanel">
        <Toolbar title="Role Assignment" actionLabel="Dummy linked member profiles" />
        <p className="prototypeNote">PST roles are attached to normal member records in this prototype. Real role assignment, audit history and profile editing will be implemented in the backend phase.</p>
        <div className="financeCardList">
          {pstProfileCards("PST Profiles").map((profile) => (
            <article className="financeStackCard" key={`settings-${profile.title}`}>
              <h3>{profile.title}</h3>
              {profile.meta.filter((line) => line.startsWith("Role:") || line.startsWith("Term") || line.startsWith("Designation") || line.startsWith("Document") || line.startsWith("Finance")).map((line) => <span key={line}>{line}</span>)}
              <button className="filterButton">View linked member profile</button>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

function ClickableMetric({ label, value, action, onClick }: { label: string; value: string; action: string; onClick: () => void }) {
  return (
    <button className="metric clickableMetric" onClick={onClick}>
      <span>{label}</span>
      <strong>{value}</strong>
      <small>{action}</small>
    </button>
  );
}

function Metric({ label, value }: { label: string; value: string }) {
  return <div className="metric"><span>{label}</span><strong>{value}</strong></div>;
}

function Action({ title, body, onClick }: { title: string; body: string; onClick: () => void }) {
  return <button className="actionItem" onClick={onClick}><strong>{title}</strong><span>{body}</span></button>;
}

function Toolbar({ title, actionLabel }: { title: string; actionLabel: string }) {
  return <div className="toolbar"><h2>{title}</h2><span className="statusPill">{actionLabel}</span></div>;
}

function Field({ label, value }: { label: string; value: string }) {
  return <div className="fieldRow"><span>{label}</span><strong>{value}</strong></div>;
}

function Avatar({ name, large = false }: { name: string; large?: boolean }) {
  const initials = name.split(" ").filter((part) => part !== "Lion" && part !== "-" && part !== "Member").slice(0, 2).map((part) => part[0]).join("");
  return <span className={large ? "avatar large" : "avatar"}>{initials}</span>;
}

