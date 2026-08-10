"use client";

import { createContext, type FormEvent, type ReactNode, useContext, useEffect, useMemo, useState, useRef } from "react";
import RemindersBackend from "./RemindersBackend";
import ReportsBackend from "./ReportsBackend";
import { eventMeetingSection } from "./eventMeetingPeriod";
import ProfileUpdatesBackend from "./ProfileUpdatesBackend";
import FinanceBackend from "./FinanceBackend";
import ClubAccountStatementBackend from "./ClubAccountStatementBackend";
import ClubExpenseBackend from "./ClubExpenseBackend";
import PersonalProfileDocuments from "./PersonalProfileDocuments";
import AnnouncementsBackend, { AnnouncementDashboardStatus } from "./AnnouncementsBackend";
import { DevAuthProvider, type DevIdentity, type DevRole, useDevAuth } from "./DevAuthSession";
import { getApiBaseUrl } from "./apiBaseUrl";
import LoginScreen from "./LoginScreen";

type Role = DevRole;
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

const API_BASE_URL = getApiBaseUrl();
type ClubProjectRecord = {
  id: number;
  club_id?: number;
  record_type: "project" | "activity";
  title: string;
  description?: string | null;
  objective?: string | null;
  service_category?: string | null;
  visibility?: "members" | "management";
  status: "draft" | "planned" | "active" | "completed" | "cancelled";
  planned_start_date?: string | null;
  planned_end_date?: string | null;
  actual_start_date?: string | null;
  actual_end_date?: string | null;
  location?: string | null;
  lead_member?: { id: number; member_code: string; full_name: string } | null;
  expected_beneficiary_count?: number | null;
  actual_beneficiary_count?: number | null;
  impact_summary?: string | null;
  cancellation_reason?: string | null;
  lock_version?: number;
};

type ClubProjectForm = {
  record_type: "project" | "activity";
  title: string;
  description: string;
  objective: string;
  service_category: string;
  visibility: "members" | "management";
  planned_start_date: string;
  planned_end_date: string;
  location: string;
  expected_beneficiary_count: string;
  actual_start_date: string;
  actual_end_date: string;
  actual_beneficiary_count: string;
  impact_summary: string;
};

type ClubProjectsMeta = { current_page: number; last_page: number; total: number };

type PaymentRecordingStatus = "initializing" | "ready" | "submitting" | "success" | "error";
type PaymentRecordingMode = "cash" | "bank_transfer" | "upi" | "cheque" | "card" | "adjustment";

type BackendClubMembership = {
  club_id?: number;
  club?: {
    id?: number;
  };
  member: {
    id: number;
    member_code: string;
    full_name: string;
  } | null;
};

type BackendAuthenticatedUser = {
  id?: number;
};

type BackendMemberRecord = {
  id: number;
  club_id: number;
  user_id?: number | null;
  member_code: string;
  full_name: string;
  status: string;
  salutation?: string | null;
  first_name?: string | null;
  middle_name?: string | null;
  last_name?: string | null;
  gender?: string | null;
  occupation?: string | null;
  membership_type?: string | null;
  membership_category?: string | null;
  membership_program?: string | null;
  joined_at?: string | null;
  birth_date?: string | null;
  spouse_name?: string | null;
  wedding_anniversary?: string | null;
  membership_sponsor_name?: string | null;
  phone?: string | null;
  home_phone?: string | null;
  alternate_phone?: string | null;
  email?: string | null;
  address_line_1?: string | null;
  address_line_2?: string | null;
  city_locality?: string | null;
  district?: string | null;
  state?: string | null;
  postal_code?: string | null;
  country?: string | null;
  blood_group?: string | null;
  blood_donor_consent?: boolean | null;
};

type MemberLoadStatus = "loading" | "ready" | "error";
const MEMBER_REQUEST_TIMEOUT_MS = 15_000;

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

type ProfileDocumentStatus = "initializing" | "ready" | "loading" | "saving" | "empty" | "error" | "unavailable" | "not_linked";

class ProfileDocumentRequestError extends Error {
  constructor(message: string, readonly status: number) {
    super(message);
    this.name = "ProfileDocumentRequestError";
  }
}

type ProfileDocumentMetadataRecord = {
  id: number;
  document_type?: string | null;
  status?: string | null;
  external_reference?: string | null;
  remarks?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

type ProfileDocumentFormState = {
  document_type: string;
  status: string;
  external_reference: string;
  remarks: string;
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
  | "my-fellowship"
  | "fellowship-administration"
  | "fellowship-queue"
  | "confirm-sponsors"
  | "fellowship-sponsor-detail"
  | "ledger"
  | "my-finance-summary"
  | "my-dues"
  | "profile-update-detail"
  | "committee-detail"
  | "event-meeting-detail"
  | "announcement-single-detail"
  | "club-document-detail"
  | "personal-document-detail"
  | "activity-detail"
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
  | "my-documents"
  | "documents-administration"
  | "document-administration-edit"
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
type FinanceSection = "Club Account Statement" | "Record Expense" | "Member Ledger / Dues" | "Payments / Receipts" | "Club Finance Reports" | "Balance Sheet Summary" | "Outstanding Dues" | "Collection Summary" | "Fellowship Payments";
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
  backendId?: number;
  userId?: number | null;
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
  joinedAt?: string | null;
  occupation?: string | null;
  membershipType?: string | null;
  membershipCategory?: string | null;
  membershipProgram?: string | null;
  birthDate?: string | null;
  spouseName?: string | null;
  weddingAnniversary?: string | null;
  gender?: string | null;
  email?: string | null;
  address?: string | null;
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
type BackendEventMeeting = {
  id: number;
  club_id: number;
  type: "event" | "meeting";
  title: string;
  description?: string | null;
  is_all_day: boolean;
  starts_at: string | null;
  ends_at: string | null;
  start_date?: string | null;
  end_date?: string | null;
  venue?: string | null;
  status: "draft" | "published" | "completed" | "cancelled";
  visibility?: "members" | "management";
  timezone?: string | null;
  published_at?: string | null;
  cancelled_at?: string | null;
  cancellation_reason?: string | null;
  completed_at?: string | null;
  created_by_user_id?: number | null;
  updated_by_user_id?: number | null;
  lock_version?: number;
};

type EventMeetingFormState = {
  id?: number;
  type: "event" | "meeting";
  title: string;
  description: string;
  venue: string;
  visibility: "members" | "management";
  is_all_day: boolean;
  start_at: string;
  end_at: string;
  start_date: string;
  end_date: string;
  lock_version?: number;
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

const roleTestMembers: Member[] = [
  {
    id: "GENERIC-PRESIDENT",
    name: "LC Beenachi President",
    dashboardName: "LC Beenachi President",
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
    id: "GENERIC-SECRETARY",
    name: "LC Beenachi Secretary",
    dashboardName: "LC Beenachi Secretary",
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
    id: "GENERIC-TREASURER",
    name: "LC Beenachi Treasurer",
    dashboardName: "LC Beenachi Treasurer",
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
    id: "GENERIC-MEMBER",
    name: "LC Beenachi Member",
    dashboardName: "LC Beenachi Member",
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

const members = roleTestMembers; // Unrelated legacy finance/report demo data only.

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

type ClubDocumentRecord = { id: string; title: string; category: string; visibility: "Members" | "Board" | "Treasurer" | "Committee"; date: string };
type DocumentEditDraft = { title: string; category: string; visibility: ClubDocumentRecord["visibility"]; date: string };

const documents: ClubDocumentRecord[] = [
  { id: "doc-general-body-minutes-2026-05", title: "General Body Minutes - May 2026", category: "Meeting minutes", visibility: "Members", date: "31 May 2026" },
  { id: "doc-annual-plan-2026", title: "Annual Plan 2026", category: "Planning document", visibility: "Members", date: "02 Jun 2026" },
  { id: "doc-member-guidelines", title: "Member Guidelines", category: "Club document", visibility: "Members", date: "01 Jun 2026" },
  { id: "doc-public-circular-2026-06", title: "Public Circular - June Meeting", category: "Member circular", visibility: "Members", date: "03 Jun 2026" },
  { id: "doc-board-minutes-2026-05", title: "Board Meeting Minutes - May 2026", category: "Board minutes", visibility: "Board", date: "30 May 2026" },
  { id: "doc-treasurer-note-2026-06", title: "Treasurer Internal Finance Note", category: "Internal finance", visibility: "Treasurer", date: "03 Jun 2026" },
  { id: "doc-project-report-template", title: "Project Report Template", category: "Committee template", visibility: "Committee", date: "28 May 2026" }
];

function documentsVisibleToRole(role: Role) {
  if (role === "president") return documents;
  if (role === "secretary") return documents.filter((item) => item.visibility !== "Treasurer");
  if (role === "treasurer") return documents.filter((item) => ["Members", "Board", "Treasurer"].includes(item.visibility));
  return documents.filter((item) => item.visibility === "Members");
}

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


const ledgerRows = [
  { id: "ledger-001", date: "01 Jun", member: "Lion Ajith Kumar - Member", type: "Monthly dues", debit: 500, credit: 0 },
  { id: "ledger-002", date: "05 Jun", member: "Lion Ajith Kumar - Member", type: "Payment received", debit: 0, credit: 1000 },
  { id: "ledger-003", date: "08 Jun", member: "Lion Treasurer User", type: "Monthly fellowship share", debit: 2500, credit: 0 },
  { id: "ledger-004", date: "12 Jun", member: "Lion Bijesh", type: "Project share", debit: 500, credit: 0 }
];

const outstandingDues = [
  { id: "DUE-001", member: "Lion Ajith Kumar - Member", label: "Monthly dues - June", dueDate: "10 Jun 2026", amount: 500, status: "Pending", selected: true },
  { id: "DUE-002", member: "Lion Ajith Kumar - Member", label: "Special Collection", purpose: "Club service project contribution", dueDate: "20 Jun 2026", amount: 500, status: "Pending", selected: true },
  { id: "DUE-003", member: "Lion Secretary User", label: "Special Collection", purpose: "Club service project contribution", dueDate: "20 Jun 2026", amount: 500, status: "Pending", selected: false },
  { id: "DUE-004", member: "Lion Treasurer User", label: "Monthly fellowship share", dueDate: "15 Jun 2026", amount: 2500, status: "Pending", selected: false },
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
    "Profile update approval workflow is not connected",
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

const pendingMemberUpdates: { member: string; change: string; submitted: string; status: string }[] = [];

const committeesList = [
  { id: "COM-001", name: "Committee List", lead: "Lion Secretary User", count: 4, purpose: "Oversee school kit distribution and educational service projects." },
  { id: "COM-002", name: "Committee List item 2", lead: "Lion Treasurer User", count: 5, purpose: "Coordinate general health screening camps and medical drives." },
  { id: "COM-003", name: "Committee List item 3", lead: "Lion Bijesh", count: 6, purpose: "Direct community service projects and school support activities." }
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
      { label: "Events & Meetings Register", screen: "reports-tools-module" }
    ]
  },
  {
    label: "Club Activity / Service Reports",
    children: [
      { label: "Projects & Activities Register", screen: "reports-tools-module" }
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

const treasurerReportMenuItems = reportMenuItems.map((item) => item.label === "Member Reports"
  ? {
      ...item,
      children: item.children?.filter((child) => child.reportDetail !== "Profile Update Pending Report")
    }
  : item
);

const roleUser: Record<Role, Member> = {
  president: roleTestMembers[0],
  secretary: roleTestMembers[1],
  treasurer: roleTestMembers[2],
  member: roleTestMembers[3]
};

function detailItem(parent: string, label: string): NavItem {
  return { label, screen: "nav-detail", detailParent: parent, detailTitle: label };
}

const myMemberAreaItems: NavItem[] = [
  { label: "My Profile", screen: "member-profile" },
  { label: "My Digital Card", screen: "digital-card" },
  { label: "My Dues", screen: "my-dues" },
  { label: "My Fellowship", screen: "my-fellowship" },
  { label: "My Profile Documents", screen: "my-documents" },
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

const treasurerPeopleMenuItems = peopleMenuItems.map((item) => item.label === "Members"
  ? {
      ...item,
      children: item.children?.filter((child) => child.detailTitle !== "Profile Update Pending")
    }
  : item
);

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
    screen: "announcements"
  },
  {
    label: "Documents",
    children: [
      { label: "Club Document Management", screen: "documents-administration" }
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
      { label: "Overview", screen: "fellowship-administration", fellowshipTab: "Overview" },
      { label: "Sponsor Rotation", screen: "fellowship-administration", fellowshipTab: "Queue" },
      { label: "Selected Sponsors", screen: "fellowship-administration", fellowshipTab: "Selected Sponsors" },
      { label: "Payments", screen: "fellowship-administration", fellowshipTab: "Payments" },
      { label: "Month Summary", screen: "fellowship-administration", fellowshipTab: "History" }
    ]
  }
];

const financeMenuItems: NavItem[] = [
  { label: "Record Expense", screen: "finance-module", financeSection: "Record Expense" },
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
      ...treasurerPeopleMenuItems,
      ...operationsMenuItems.filter((item) => item.label !== "Fellowship" && item.label !== "Documents")
    ] },
    { title: "Reports & Tools", items: [
      ...treasurerReportMenuItems,
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
      { label: "My Finance Summary", screen: "my-finance-summary" },
      { label: "My Dues", screen: "my-dues" },
      { label: "My Payment History", screen: "payment-history" },
      { label: "My Fellowship", screen: "my-fellowship" },
      { label: "Events & Meetings", screen: "events-meetings" },
      { label: "Projects / Activities", screen: "projects" },
      { label: "Reminders", screen: "notifications-reminders" },
      { label: "Announcements", screen: "announcements" },
      { label: "My Profile Documents", screen: "my-documents" },
      { label: "Celebrations", screen: "celebrations" },
      { label: "Blood Donor Consent", screen: "donor-consent" }
    ] }
  ]
};

function money(value: number) {
  return `₹${value.toLocaleString("en-IN")}`;
}

function verifiedOperationalSourceConnected(_source: "events" | "announcements" | "projects" | "reports" | "collections" | "reminders") {
  return false;
}

function roundFellowshipAmount(amount: number) {
  return Math.round((amount + Number.EPSILON) * 100) / 100;
}

function formatSponsorShare(amount: number) {
  const rounded = roundFellowshipAmount(Number.isFinite(amount) ? amount : 0);
  return `₹${rounded.toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function hasPositiveFellowshipBalance(amount: number) {
  return roundFellowshipAmount(amount) > 0;
}

const memberQuickAccess = [
  { label: "My Dues", short: "DU", screen: "my-dues" as Screen },
  { label: "Payments", short: "PH", screen: "payment-history" as Screen },
  { label: "My Fellowship", short: "FE", screen: "my-fellowship" as Screen },
  { label: "Digital Card", short: "ID", screen: "digital-card" as Screen },
  { label: "Announcements", short: "AN", screen: "announcements" as Screen },
  { label: "Events", short: "EV", screen: "events-meetings" as Screen },
  { label: "Documents", short: "DO", screen: "my-documents" as Screen },
  { label: "Blood Donor", short: "BD", screen: "donor-consent" as Screen },
  { label: "Directory", short: "DR", screen: "members" as Screen }
];

const pstMemberAreaCards = [
  { label: "My Profile", value: "Member record", action: "Open", screen: "member-profile" as Screen },
  { label: "My Digital Card", value: "In-app card", action: "View", screen: "digital-card" as Screen },
  { label: "My Dues", value: "Own account", action: "Review", screen: "my-dues" as Screen },
  { label: "My Fellowship", value: "Own status", action: "Open", screen: "my-fellowship" as Screen },
  { label: "My Profile Documents", value: "My metadata", action: "View", screen: "my-documents" as Screen },
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

type MemberDataContextValue = {
  members: Member[];
  status: MemberLoadStatus;
  activeMemberCount: number | null;
  activeMemberStatus: MemberLoadStatus;
  error: string | null;
  token: string | null;
  clubId: number | null;
  authenticatedUserId: number | null;
  retry: () => void;
};

const MemberDataContext = createContext<MemberDataContextValue>({
  members: [],
  status: "loading",
  activeMemberCount: null,
  activeMemberStatus: "loading",
  error: null,
  token: null,
  clubId: null,
  authenticatedUserId: null,
  retry: () => undefined
});

const PersonalProfileContext = createContext(false);

function useCurrentMemberData() {
  return useContext(MemberDataContext);
}
function memberFromBackend(record: BackendMemberRecord): Member {
  const displayName = record.full_name.trim();
  const address = [record.address_line_1, record.address_line_2, record.city_locality, record.district, record.state, record.postal_code, record.country]
    .filter((value): value is string => Boolean(value?.trim()))
    .join(", ");

  return {
    id: record.member_code,
    backendId: record.id,
    userId: record.user_id,
    name: displayName,
    dashboardName: displayName,
    clubRole: record.membership_category || record.membership_type || "Member",
    status: record.status.toLowerCase() === "active" ? "Active" : "Inactive",
    bloodGroup: record.blood_group || "Not provided",
    phone: record.phone || "",
    consentContact: false,
    donorConsent: Boolean(record.blood_donor_consent),
    dues: 0,
    birthday: record.birth_date || "",
    anniversary: record.joined_at || "",
    consentDob: false,
    consentAnniversary: false,
    wishStatus: "Pending",
    joinedAt: record.joined_at,
    occupation: record.occupation,
    membershipType: record.membership_type,
    membershipCategory: record.membership_category,
    membershipProgram: record.membership_program,
    birthDate: record.birth_date,
    spouseName: record.spouse_name,
    weddingAnniversary: record.wedding_anniversary,
    gender: record.gender,
    email: record.email,
    address: address || null
  };
}

function isImportedCurrentMember(record: BackendMemberRecord) {
  return /^\d+$/.test(record.member_code);
}

function isBackendMemberRecord(value: unknown): value is BackendMemberRecord {
  if (!value || typeof value !== "object") return false;
  const record = value as Partial<BackendMemberRecord>;
  return typeof record.id === "number"
    && typeof record.club_id === "number"
    && typeof record.member_code === "string"
    && typeof record.full_name === "string"
    && typeof record.status === "string";
}

const verifiedLeadershipAssignments = [
  { position: "President", memberCode: "4794370" },
  { position: "Secretary", memberCode: "26766796" },
  { position: "Treasurer", memberCode: "26766860" },
  { position: "Immediate Past President", memberCode: "4177371" },
  { position: "First Vice President", memberCode: "26766886" },
  { position: "Finance Committee Chair", memberCode: "4796991" },
  { position: "Service Committee Chair", memberCode: "5922386" },
  { position: "LCIF Coordinator", memberCode: "5148361" },
  { position: "Meeting Committee Chair", memberCode: "5148362" },
  { position: "Membership Committee Chair", memberCode: "5148360" },
  { position: "Marketing Committee Chair", memberCode: "26766807" },
  { position: "Leadership Training Chair", memberCode: "4798100" },
  { position: "Board Member", memberCode: "6131765" },
  { position: "Board Member", memberCode: "26766781" }
] as const;

const governanceDetailTitles = [
  "PST Profiles",
  "President Profile",
  "Secretary Profile",
  "Treasurer Profile",
  "Board Members",
  "Committee List",
  "Committee Members"
];

function verifiedLeadershipCards(currentMembers: Member[], title: string) {
  const positionForTitle = title === "President Profile"
    ? "President"
    : title === "Secretary Profile"
      ? "Secretary"
      : title === "Treasurer Profile"
        ? "Treasurer"
        : null;
  const configured = positionForTitle
    ? verifiedLeadershipAssignments.filter((assignment) => assignment.position === positionForTitle)
    : title === "PST Profiles"
      ? verifiedLeadershipAssignments.filter((assignment) => ["President", "Secretary", "Treasurer"].includes(assignment.position))
      : verifiedLeadershipAssignments;

  return configured.flatMap((assignment) => {
    const matches = currentMembers.filter((member) => member.id === assignment.memberCode && typeof member.backendId === "number");
    if (matches.length !== 1) return [];
    const member = matches[0];
    return [{
      title: member.name,
      meta: [`Position: ${assignment.position}`, `Member Code: ${member.id}`, "Term / Lionistic Year: 2026–27"],
      action: "View member profile",
      memberId: member.id,
      memberBackendId: member.backendId
    }];
  });
}

function normalizeFellowshipMemberName(name: string) {
  return name.trim().replace(/\s+/g, " ").toLocaleLowerCase("en-IN");
}

function canonicalFellowshipMembers(currentMembers: Member[]) {
  return currentMembers
    .filter((member) => member.status === "Active" && typeof member.backendId === "number" && /^\d+$/.test(member.id))
    .slice()
    .sort((left, right) => {
      const nameOrder = normalizeFellowshipMemberName(left.name).localeCompare(
        normalizeFellowshipMemberName(right.name),
        "en-IN",
        { sensitivity: "base", usage: "sort" }
      );
      if (nameOrder !== 0) return nameOrder;

      const memberCodeOrder = Number(left.id) - Number(right.id);
      if (memberCodeOrder !== 0) return memberCodeOrder;

      return (left.backendId as number) - (right.backendId as number);
    });
}

function fellowshipMemberKey(member: Member) {
  return typeof member.backendId === "number" ? String(member.backendId) : "";
}

function fellowshipMemberForKey(memberKey: string, currentMembers: Member[]) {
  const backendId = Number(memberKey);
  return currentMembers.find((member) => member.backendId === backendId) || null;
}

function sortFellowshipMemberKeys(memberKeys: string[], currentMembers: Member[]) {
  const order = new Map(canonicalFellowshipMembers(currentMembers).map((member, index) => [fellowshipMemberKey(member), index]));
  return [...new Set(memberKeys)].sort((left, right) => {
    const leftOrder = order.get(left);
    const rightOrder = order.get(right);
    if (leftOrder === undefined && rightOrder === undefined) return left.localeCompare(right, "en-IN", { numeric: true });
    if (leftOrder === undefined) return 1;
    if (rightOrder === undefined) return -1;
    return leftOrder - rightOrder;
  });
}

function displayMemberName(memberKey: string, currentMembers: Member[]) {
  return fellowshipMemberForKey(memberKey, currentMembers)?.name || "Legacy / unlinked member";
}

type FellowshipMonthLifecycle = "unprepared" | "draft" | "payment_tracking" | "finalised";
type FellowshipPaymentEntry = { id: string; date: string; amount: number };
type FellowshipMonthRecord = {
  key: string;
  lifecycle: FellowshipMonthLifecycle;
  totalExpenseInput: string;
  selectedSponsors: string[];
  queueStatuses: Record<string, string>;
  confirmedSponsors: string[];
  confirmedSharePerSponsor: number;
  payments: Record<string, number>;
  paymentHistories: Record<string, FellowshipPaymentEntry[]>;
  paymentInputs: Record<string, string>;
  expandedHistories: Record<string, boolean>;
  transferredOutstanding: number;
  notice: string | null;
};

function fellowshipMonthLabel(monthKey: string) {
  const [year, month] = monthKey.split("-").map(Number);
  return new Intl.DateTimeFormat("en-IN", { month: "long", year: "numeric", timeZone: "UTC" })
    .format(new Date(Date.UTC(year, month - 1, 1)));
}

function nextFellowshipMonthKey(monthKey: string) {
  const [year, month] = monthKey.split("-").map(Number);
  const next = new Date(Date.UTC(year, month, 1));
  return `${next.getUTCFullYear()}-${String(next.getUTCMonth() + 1).padStart(2, "0")}`;
}

function emptyFellowshipMonth(key: string, lifecycle: FellowshipMonthLifecycle = "unprepared"): FellowshipMonthRecord {
  return {
    key,
    lifecycle,
    totalExpenseInput: "",
    selectedSponsors: [],
    queueStatuses: {},
    confirmedSponsors: [],
    confirmedSharePerSponsor: 0,
    payments: {},
    paymentHistories: {},
    paymentInputs: {},
    expandedHistories: {},
    transferredOutstanding: 0,
    notice: null
  };
}
export default function Prototype() {
  return <DevAuthProvider><PrototypeGate /></DevAuthProvider>;
}

function PrototypeGate() {
  const auth = useDevAuth();
  if (!auth.session) {
    return <main className="loginPage" data-testid="unauthenticated-login-page"><LoginScreen login={auth.login} error={auth.error} submitting={auth.status === "authenticating"} /></main>;
  }
  return <PrototypeWorkspace key={auth.session.identity} identity={auth.session.identity} role={auth.session.role} initialScreen="dashboard" />;
}

function PrototypeWorkspace({ identity, role, initialScreen }: { identity: DevIdentity; role: Role; initialScreen: Screen }) {
  const { session, logout } = useDevAuth();
  const [screen, setScreen] = useState<Screen>(initialScreen);
  const [currentMembers, setCurrentMembers] = useState<Member[]>([]);
  const [memberStatus, setMemberStatus] = useState<MemberLoadStatus>("loading");
  const [activeMemberCount, setActiveMemberCount] = useState<number | null>(null);
  const [activeMemberStatus, setActiveMemberStatus] = useState<MemberLoadStatus>("loading");
  const [memberLoadError, setMemberLoadError] = useState<string | null>(null);
  const [memberToken, setMemberToken] = useState<string | null>(null);
  const [memberClubId, setMemberClubId] = useState<number | null>(null);
  const [authenticatedUserId, setAuthenticatedUserId] = useState<number | null>(null);
  const [memberRefreshKey, setMemberRefreshKey] = useState(0);

  const [selectedSponsors, setSelectedSponsors] = useState<string[]>([]);
  // 3FG compatibility initializer: June/July are normalized once into canonical month-key records.
  const [fellowshipMonths, setFellowshipMonths] = useState<Record<string, FellowshipMonthRecord>>(() => ({
    "2026-06": { ...emptyFellowshipMonth("2026-06", "draft"), totalExpenseInput: "10000" },
    "2026-07": emptyFellowshipMonth("2026-07")
  }));
  const [selectedFellowshipMonthKey, setSelectedFellowshipMonthKey] = useState("2026-06");

  const [bloodGroup, setBloodGroup] = useState("O+");
  const [fellowshipTab, setFellowshipTab] = useState<"Overview" | "Queue" | "Selected Sponsors" | "Payments" | "History">("Overview");
  const [expandedGroups, setExpandedGroups] = useState<string[]>([]);
  const [collectionDetail, setCollectionDetail] = useState<CollectionDetail>(null);
  const [dashboardDetail, setDashboardDetail] = useState<DashboardDetail | null>(null);
  const [peopleSection, setPeopleSection] = useState<PeopleSection>("Members");
  const [operationsSection, setOperationsSection] = useState<OperationsSection | null>(null);
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
  const [selectedMember, setSelectedMember] = useState<Member | null>(null);
  const [financePaymentContext, setFinancePaymentContext] = useState<{ memberId: number; obligationId: number } | null>(null);
  const [selectedUpdate, setSelectedUpdate] = useState<typeof pendingMemberUpdates[number] | null>(null);
  const [selectedCommittee, setSelectedCommittee] = useState<typeof committeesList[number] | null>(null);
  const [selectedEventMeeting, setSelectedEventMeeting] = useState<BackendEventMeeting | null>(null);
  const [selectedAnnouncement, setSelectedAnnouncement] = useState<{ title: string; meta: string[]; sourceSection: AnnouncementDetail } | null>(null);
  const [selectedDocument, setSelectedDocument] = useState<{ title: string; meta: string[]; sourceTitle: string } | null>(null);
  const [selectedPersonalDocumentId, setSelectedPersonalDocumentId] = useState<string | null>(null);
  const [selectedAdministrativeDocumentId, setSelectedAdministrativeDocumentId] = useState<string | null>(null);
  const [documentEditDraft, setDocumentEditDraft] = useState<DocumentEditDraft | null>(null);
  const [selectedActivity, setSelectedActivity] = useState<{
    name: string;
    date: string;
    categoryCommittee: string;
    peopleServed: string;
    reportStatus: string;
    sourceTitle: string;
  } | null>(null);
  const [profileBackContext, setProfileBackContext] = useState<{ screen: Screen; parent?: string; title?: string } | null>(null);
  const [selectedSponsor, setSelectedSponsor] = useState<{ memberId: string; name: string; amount: number; status: string; source: "Selected Sponsors" | "Payments" } | null>(null);
  const [selectedSponsorPayment, setSelectedSponsorPayment] = useState<{ memberId: string; name: string; amount: number; status: string; source: "Payments" } | null>(null);
  const [tempQueueStatuses, setTempQueueStatuses] = useState<Record<string, string>>({});
  const [fellowshipConfirmationMessage, setFellowshipConfirmationMessage] = useState<string | null>(null);
  const [totalFellowshipExpenseInput, setTotalFellowshipExpenseInput] = useState<string>("10000");
  const [sponsorPayments, setSponsorPayments] = useState<Record<string, number>>({});
  const [sponsorPaymentHistories, setSponsorPaymentHistories] = useState<Record<string, Array<{ id: string; date: string; amount: number }>>>({});
  const [sponsorPaymentInputs, setSponsorPaymentInputs] = useState<Record<string, string>>({});
  const [isFellowshipConfirmed, setIsFellowshipConfirmed] = useState<boolean>(false);
  const [confirmedSponsors, setConfirmedSponsors] = useState<string[]>([]);
  const [confirmedSharePerSponsor, setConfirmedSharePerSponsor] = useState<number>(0);
  const [isFellowshipMonthClosed, setIsFellowshipMonthClosed] = useState<boolean>(false);
  const [isJulyRotationActive, setIsJulyRotationActive] = useState<boolean>(false);
  const [julySelectedSponsors, setJulySelectedSponsors] = useState<string[]>([]);
  const [julyTempQueueStatuses, setJulyTempQueueStatuses] = useState<Record<string, string>>({});
  const [julyTotalFellowshipExpenseInput, setJulyTotalFellowshipExpenseInput] = useState<string>("");
  const [julySponsorPayments, setJulySponsorPayments] = useState<Record<string, number>>({});
  const [julySponsorPaymentHistories, setJulySponsorPaymentHistories] = useState<Record<string, Array<{ id: string; date: string; amount: number }>>>({});
  const [julySponsorPaymentInputs, setJulySponsorPaymentInputs] = useState<Record<string, string>>({});
  const [isJulyFellowshipConfirmed, setIsJulyFellowshipConfirmed] = useState<boolean>(false);
  const [julyConfirmedSponsors, setJulyConfirmedSponsors] = useState<string[]>([]);
  const [julyConfirmedSharePerSponsor, setJulyConfirmedSharePerSponsor] = useState<number>(0);
  const [julyExpandedHistories, setJulyExpandedHistories] = useState<Record<string, boolean>>({});
  const [julyLastRecordedPayment, setJulyLastRecordedPayment] = useState<{
    memberName: string;
    paymentAmount: number;
    cumulativePaid: number;
    remainingBalance: number;
    type: "partial" | "full";
  } | null>(null);
  const [julyHighlightedSponsor, setJulyHighlightedSponsor] = useState<string | null>(null);
  const [juneSnapshot, setJuneSnapshot] = useState<{
    totalExpense: number;
    confirmedSponsors: string[];
    sharePerSponsor: number;
    payments: Record<string, number>;
    paymentHistories: Record<string, Array<{ id: string; date: string; amount: number }>>;
    isClosed: boolean;
  } | null>(null);
  const [fellowshipOutstandingDues, setFellowshipOutstandingDues] = useState<Array<{
    id: string;
    heading: string;
    memberName: string;
    month: string;
    share: number;
    paid: number;
    balance: number;
    source: string;
    status: string;
    transferDate: string;
  }>>([]);

  const [expandedHistories, setExpandedHistories] = useState<Record<string, boolean>>({});
  const [lastRecordedPayment, setLastRecordedPayment] = useState<{
    memberName: string;
    paymentAmount: number;
    cumulativePaid: number;
    remainingBalance: number;
    type: "partial" | "full";
  } | null>(null);
  const [highlightedSponsor, setHighlightedSponsor] = useState<string | null>(null);

  useEffect(() => {
    const controller = new AbortController();
    let cancelled = false;
    let listReady = false;
    let timedOut = false;
    const timeoutId = window.setTimeout(() => {
      timedOut = true;
      controller.abort();
    }, MEMBER_REQUEST_TIMEOUT_MS);

    async function loadCurrentMembers() {
      setMemberStatus("loading");
      setActiveMemberStatus("loading");
      setActiveMemberCount(null);
      setMemberLoadError(null);
      setCurrentMembers([]);
      setMemberToken(null);
      setMemberClubId(null);
      setAuthenticatedUserId(null);

      try {
        if (!session) throw new Error("No verified session is available.");
        const token = session.token;
        const authHeaders = { Accept: "application/json", Authorization: "Bearer " + token };
        const mePayload = { user: session.user, club_memberships: [session.membership] };
        const authenticatedUser = mePayload.user as BackendAuthenticatedUser | undefined;
        const authenticatedId = authenticatedUser?.id;
        const memberships = mePayload.club_memberships as BackendClubMembership[] | undefined;
        const clubId = memberships?.[0]?.club?.id;
        if (typeof clubId !== "number") throw new Error("No active club context is available.");

        const activeMembership = (memberships || []).find(
          (membership) =>
            Number(membership.club_id ?? membership.club?.id) === Number(clubId)
        );

        const linkedMemberInfo = activeMembership?.member ?? null;

        const listResponse = await fetch(API_BASE_URL + "/clubs/" + clubId + "/members", { headers: authHeaders, signal: controller.signal });
        if (!listResponse.ok) throw new Error("Current member data could not be loaded.");
        const listPayload = await listResponse.json() as { members?: unknown };
        if (!Array.isArray(listPayload.members) || !listPayload.members.every(isBackendMemberRecord)) {
          throw new Error("Current member data could not be loaded.");
        }
        const importedRecords = listPayload.members.filter(isImportedCurrentMember);
        if (importedRecords.length !== 47) throw new Error("The expected 47 imported current members are not safely distinguishable.");
        listReady = true;
        if (!cancelled) {
          setActiveMemberCount(importedRecords.filter((record) => record.status.toLowerCase() === "active").length);
          setActiveMemberStatus("ready");
        }

        let records: BackendMemberRecord[];
        if (role === "member") {
          if (!activeMembership || !linkedMemberInfo || !linkedMemberInfo.id) {
            throw new Error("No genuine club member profile is linked.");
          }
          const profileResponse = await fetch(API_BASE_URL + "/clubs/" + clubId + "/members/me", { headers: authHeaders, signal: controller.signal });
          if (!profileResponse.ok) {
            throw new Error("No genuine club member profile is linked.");
          }
          const profilePayload = await profileResponse.json() as { member?: unknown };
          const selfRecord = profilePayload.member;
          if (!isBackendMemberRecord(selfRecord) || Number(selfRecord.id) !== Number(linkedMemberInfo.id)) {
            throw new Error("No genuine club member profile is linked.");
          }
          records = importedRecords.map((record) => Number(record.id) === Number(selfRecord.id) ? selfRecord : record);
        } else {
          records = await Promise.all(importedRecords.map(async (record) => {
            const profileResponse = await fetch(API_BASE_URL + "/clubs/" + clubId + "/members/" + record.id, { headers: authHeaders, signal: controller.signal });
            if (!profileResponse.ok) throw new Error("An imported member profile could not be loaded.");
            const profilePayload = await profileResponse.json() as { member?: unknown };
            if (!isBackendMemberRecord(profilePayload.member)) {
              throw new Error("An imported member profile could not be loaded.");
            }
            return profilePayload.member;
          }));
        }

        if (!cancelled) {
          const normalized = records.map(memberFromBackend).sort((a, b) => {
            const joinOrder = (a.joinedAt || "9999-12-31").localeCompare(b.joinedAt || "9999-12-31");
            return joinOrder || a.id.localeCompare(b.id, undefined, { numeric: true });
          });
          setCurrentMembers(normalized);
          setMemberToken(token);
          setMemberClubId(clubId);
          setAuthenticatedUserId(typeof authenticatedId === "number" ? authenticatedId : null);
          setMemberStatus("ready");
        }
      } catch (error) {
        const aborted = error instanceof DOMException && error.name === "AbortError";
        if (!cancelled && (!aborted || timedOut)) {
          setCurrentMembers([]);
          setMemberStatus("error");
          setMemberLoadError(timedOut ? "Current member data request timed out." : error instanceof Error ? error.message : "Current member data could not be loaded.");
          if (!listReady) {
            setActiveMemberCount(null);
            setActiveMemberStatus("error");
          }
        }
      } finally {
        window.clearTimeout(timeoutId);
      }
    }

    loadCurrentMembers();
    return () => {
      cancelled = true;
      window.clearTimeout(timeoutId);
      controller.abort();
    };
  }, [role, memberRefreshKey, session]);
  const expenseTrimmed = totalFellowshipExpenseInput.trim();
  const isValidExpenseFormat = /^\d+(\.\d*)?$/.test(expenseTrimmed) || expenseTrimmed === ".";
  const parsedExpense = parseFloat(expenseTrimmed);
  const isValidExpense = isValidExpenseFormat && !isNaN(parsedExpense) && parsedExpense > 0;
  const totalFellowshipExpense = isValidExpense ? parsedExpense : 0;

  function handleSelectSponsor(name: string) {
    if (activeSelectedSponsors.includes(name)) {
      activeSetSelectedSponsors(activeSelectedSponsors.filter((s) => s !== name));
      activeSetTempQueueStatuses(prev => {
        const next = { ...prev };
        delete next[name];
        return next;
      });
    } else {
      activeSetSelectedSponsors(sortFellowshipMemberKeys([...activeSelectedSponsors, name], currentMembers));
      activeSetTempQueueStatuses(prev => ({
        ...prev,
        [name]: "Selected"
      }));
    }
  }

  function handleSkipHoldSponsor(name: string) {
    activeSetSelectedSponsors(activeSelectedSponsors.filter((s) => s !== name));
    activeSetTempQueueStatuses(prev => ({
      ...prev,
      [name]: "Deferred for this month"
    }));
  }

  function handleResetSponsor(name: string) {
    activeSetSelectedSponsors(activeSelectedSponsors.filter((s) => s !== name));
    activeSetTempQueueStatuses(prev => {
      const next = { ...prev };
      delete next[name];
      return next;
    });
  }

  const selectedCount = selectedSponsors.length;
  const amountPerSponsor = selectedCount > 0 ? totalFellowshipExpense / selectedCount : 0;
  const julyExpenseTrimmed = julyTotalFellowshipExpenseInput.trim();
  const isValidJulyExpenseFormat = /^\d+(\.\d*)?$/.test(julyExpenseTrimmed) || julyExpenseTrimmed === ".";
  const parsedJulyExpense = parseFloat(julyExpenseTrimmed);
  const isValidJulyExpense = isValidJulyExpenseFormat && !isNaN(parsedJulyExpense) && parsedJulyExpense > 0;
  const julyTotalFellowshipExpense = isValidJulyExpense ? parsedJulyExpense : 0;
  const julySelectedCount = julySelectedSponsors.length;
  const julyAmountPerSponsor = julySelectedCount > 0 ? julyTotalFellowshipExpense / julySelectedCount : 0;
  const activeSelectedSponsors = isJulyRotationActive ? julySelectedSponsors : selectedSponsors;
  const activeSetSelectedSponsors = isJulyRotationActive ? setJulySelectedSponsors : setSelectedSponsors;
  const activeTempQueueStatuses = isJulyRotationActive ? julyTempQueueStatuses : tempQueueStatuses;
  const activeSetTempQueueStatuses = isJulyRotationActive ? setJulyTempQueueStatuses : setTempQueueStatuses;
  const activeTotalFellowshipExpense = isJulyRotationActive ? julyTotalFellowshipExpense : totalFellowshipExpense;
  const activeTotalFellowshipExpenseInput = isJulyRotationActive ? julyTotalFellowshipExpenseInput : totalFellowshipExpenseInput;
  const activeSetTotalFellowshipExpenseInput = isJulyRotationActive ? setJulyTotalFellowshipExpenseInput : setTotalFellowshipExpenseInput;
  const activeSponsorPayments = isJulyRotationActive ? julySponsorPayments : sponsorPayments;
  const activeSetSponsorPayments = isJulyRotationActive ? setJulySponsorPayments : setSponsorPayments;
  const activeSponsorPaymentHistories = isJulyRotationActive ? julySponsorPaymentHistories : sponsorPaymentHistories;
  const activeSetSponsorPaymentHistories = isJulyRotationActive ? setJulySponsorPaymentHistories : setSponsorPaymentHistories;
  const activeSponsorPaymentInputs = isJulyRotationActive ? julySponsorPaymentInputs : sponsorPaymentInputs;
  const activeSetSponsorPaymentInputs = isJulyRotationActive ? setJulySponsorPaymentInputs : setSponsorPaymentInputs;
  const activeIsFellowshipConfirmed = isJulyRotationActive ? isJulyFellowshipConfirmed : isFellowshipConfirmed;
  const activeSetIsFellowshipConfirmed = isJulyRotationActive ? setIsJulyFellowshipConfirmed : setIsFellowshipConfirmed;
  const activeConfirmedSponsors = isJulyRotationActive ? julyConfirmedSponsors : confirmedSponsors;
  const activeSetConfirmedSponsors = isJulyRotationActive ? setJulyConfirmedSponsors : setConfirmedSponsors;
  const activeConfirmedSharePerSponsor = isJulyRotationActive ? julyConfirmedSharePerSponsor : confirmedSharePerSponsor;
  const activeSetConfirmedSharePerSponsor = isJulyRotationActive ? setJulyConfirmedSharePerSponsor : setConfirmedSharePerSponsor;
  const activeAmountPerSponsor = isJulyRotationActive ? julyAmountPerSponsor : amountPerSponsor;
  const activeIsValidExpense = isJulyRotationActive ? isValidJulyExpense : isValidExpense;
  const activeExpandedHistories = isJulyRotationActive ? julyExpandedHistories : expandedHistories;
  const activeSetExpandedHistories = isJulyRotationActive ? setJulyExpandedHistories : setExpandedHistories;
  const activeLastRecordedPayment = isJulyRotationActive ? julyLastRecordedPayment : lastRecordedPayment;
  const activeSetLastRecordedPayment = isJulyRotationActive ? setJulyLastRecordedPayment : setLastRecordedPayment;
  const activeHighlightedSponsor = isJulyRotationActive ? julyHighlightedSponsor : highlightedSponsor;
  const activeSetHighlightedSponsor = isJulyRotationActive ? setJulyHighlightedSponsor : setHighlightedSponsor;
  const activeFellowshipMonthLabel = isJulyRotationActive ? "July 2026" : "June 2026";
  const activeConfirmationTitle = `Review & Confirm ${activeFellowshipMonthLabel} Sponsors`;

  const resolvedAuthenticatedMember = authenticatedUserId !== null
    ? currentMembers.find((m) => m.userId === authenticatedUserId)
    : null;
  const activeMember =
    resolvedAuthenticatedMember ??
    (role === "member" ? null : roleUser[role]);
  const canAccessFellowshipAdministration = role === "president" || role === "secretary";
  const canManageClubDocuments = role === "president" || role === "secretary";
  const isPersonalFinanceScreen =
    screen === "my-finance-summary" ||
    screen === "my-dues" ||
    screen === "payment-history";

  function handleSetScreen(nextScreen: Screen) {
    if (nextScreen === "fellowship-queue") {
      setFellowshipTab("Queue");
      nextScreen = "fellowship-administration";
    } else if (nextScreen === "confirm-sponsors") {
      setFellowshipTab("Selected Sponsors");
      nextScreen = "fellowship-administration";
    }
    if (!canAccessFellowshipAdministration && ["fellowship-administration", "fellowship-queue", "confirm-sponsors", "fellowship-sponsor-detail"].includes(nextScreen)) {
      nextScreen = "fellowship-administration";
    }
    if (!canManageClubDocuments && ["documents-administration", "document-administration-edit"].includes(nextScreen)) {
      nextScreen = "documents-administration";
    }
    setSelectedMember(null);
    setSelectedUpdate(null);
    setSelectedCommittee(null);
    setSelectedEventMeeting(null);
    setSelectedAnnouncement(null);
    setSelectedDocument(null);
    setSelectedPersonalDocumentId(null);
    setSelectedAdministrativeDocumentId(null);
    setDocumentEditDraft(null);
    setSelectedActivity(null);
    setProfileBackContext(null);
    if (nextScreen !== "record-member-payment") setFinancePaymentContext(null);
    if (
      nextScreen !== "fellowship-administration" &&
      nextScreen !== "my-fellowship" &&
      nextScreen !== "fellowship-sponsor-detail"
    ) {
      setSelectedSponsor(null);
      setSelectedSponsorPayment(null);
      setFellowshipConfirmationMessage(null);
      setTotalFellowshipExpenseInput("10000");
      setSponsorPayments({});
      setSponsorPaymentInputs({});
      setIsFellowshipConfirmed(false);
      setConfirmedSponsors([]);
      setConfirmedSharePerSponsor(0);
      setIsFellowshipMonthClosed(false);
      setJuneSnapshot(null);
      setFellowshipOutstandingDues([]);
      setLastRecordedPayment(null);
      setHighlightedSponsor(null);
      setExpandedHistories({});
      setJulySelectedSponsors([]);
      setJulyTempQueueStatuses({});
      setJulyTotalFellowshipExpenseInput("");
      setJulySponsorPayments({});
      setJulySponsorPaymentInputs({});
      setJulySponsorPaymentHistories({});
      setIsJulyFellowshipConfirmed(false);
      setJulyConfirmedSponsors([]);
      setJulyConfirmedSharePerSponsor(0);
      setJulyLastRecordedPayment(null);
      setJulyHighlightedSponsor(null);
      setJulyExpandedHistories({});
    }
    setScreen(nextScreen);
  }

  function openOutstandingPayment(memberId: number, obligationId: number) {
    setFinancePaymentContext({ memberId, obligationId });
    setScreen("record-member-payment");
  }

  function openActivityDetail(activity: {
    name: string;
    date: string;
    categoryCommittee: string;
    peopleServed: string;
    reportStatus: string;
    sourceTitle: string;
  }) {
    setSelectedMember(null);
    setSelectedUpdate(null);
    setSelectedCommittee(null);
    setSelectedEventMeeting(null);
    setSelectedAnnouncement(null);
    setSelectedDocument(null);
    setSelectedActivity(activity);
    setScreen("activity-detail");
  }

  function openDocumentDetail(doc: { title: string; meta: string[]; sourceTitle: string }) {
    setSelectedMember(null);
    setSelectedUpdate(null);
    setSelectedCommittee(null);
    setSelectedEventMeeting(null);
    setSelectedAnnouncement(null);
    setSelectedDocument(doc);
    setScreen("club-document-detail");
  }
  function openPersonalDocument(documentId: string) {
    const document = documentsVisibleToRole(role).find((item) => item.id === documentId);
    if (!document) {
      setSelectedPersonalDocumentId(null);
      setScreen("personal-document-detail");
      return;
    }
    setSelectedAdministrativeDocumentId(null);
    setDocumentEditDraft(null);
    setSelectedPersonalDocumentId(document.id);
    setScreen("personal-document-detail");
  }

  function startAdministrativeDocumentEdit(documentId: string) {
    const document = canManageClubDocuments ? documents.find((item) => item.id === documentId) : null;
    if (!document) {
      setSelectedAdministrativeDocumentId(null);
      setDocumentEditDraft(null);
      setScreen("documents-administration");
      return;
    }
    setSelectedPersonalDocumentId(null);
    setSelectedAdministrativeDocumentId(document.id);
    setDocumentEditDraft({ title: document.title, category: document.category, visibility: document.visibility, date: document.date });
    setScreen("document-administration-edit");
  }

  function cancelAdministrativeDocumentEdit() {
    setSelectedAdministrativeDocumentId(null);
    setDocumentEditDraft(null);
    setScreen("documents-administration");
  }

  function openAnnouncementDetail(announcement: { title: string; meta: string[]; sourceSection: AnnouncementDetail }) {
    setSelectedMember(null);
    setSelectedUpdate(null);
    setSelectedCommittee(null);
    setSelectedEventMeeting(null);
    setSelectedAnnouncement(announcement);
    setScreen("announcement-single-detail");
  }

  function openEventMeetingDetail(item: BackendEventMeeting) {
    setSelectedMember(null);
    setSelectedUpdate(null);
    setSelectedCommittee(null);
    setSelectedEventMeeting(item);
    setScreen("event-meeting-detail");
  }

  function openCommitteeDetail(committee: typeof committeesList[number]) {
    setSelectedMember(null);
    setSelectedUpdate(null);
    setSelectedCommittee(committee);
    setScreen("committee-detail");
  }

  function selectAndOpenMemberProfile(member: Member) {
    setProfileBackContext({
      screen: screen,
      parent: screen === "nav-detail" ? navDetail.parent : undefined,
      title: screen === "nav-detail" ? navDetail.title : undefined
    });
    setSelectedMember(member);
    setScreen("member-profile");
  }

  function openProfileUpdateDetail(update: typeof pendingMemberUpdates[number]) {
    setSelectedUpdate(update);
    setScreen("profile-update-detail");
  }
  const totalOutstanding = currentMembers.reduce((sum, member) => sum + member.dues, 0);
  const allowedDonors = useMemo(
    () => currentMembers.filter((member) => member.donorConsent && member.bloodGroup === bloodGroup),
    [bloodGroup, currentMembers]
  );

  function navItemMatches(item: NavItem) {
    if (!item.screen) return false;
    const isFellowshipMatch = item.screen === "fellowship-administration" && (screen === "fellowship-administration" || (screen === "operations-module" && operationsSection === "Fellowship"));
    if (item.screen !== screen && !isFellowshipMatch) return false;
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
    if (item.screen === "member-profile") {
      setSelectedMember(null);
    } else {
      setSelectedMember(null);
    }
    if (item.reportSection) setReportsSection(item.reportSection);
    if (item.reportDetail) setReportDetail(item.reportDetail);
    if (item.detailTitle) setNavDetail({ parent: item.detailParent || group.title, title: item.detailTitle });
    if (item.eventMeetingSection) setEventMeetingSection(item.eventMeetingSection);
    if (item.announcementDetail) setAnnouncementDetail(item.announcementDetail);
    if (item.fellowshipTab) {
      setFellowshipTab(item.fellowshipTab);
      setOperationsSection("Fellowship");
    }
    if (item.financeSection) setFinanceSection(item.financeSection);
    if (item.statementSection) setClubStatementSection(item.statementSection);
    if (item.collectionDetail) setCollectionDetail(item.collectionDetail);
    if (item.fellowshipPaymentView) setFellowshipPaymentView(item.fellowshipPaymentView);
    if (item.screen === "my-documents") { setSelectedAdministrativeDocumentId(null); setDocumentEditDraft(null); }
    if (item.screen === "documents-administration") { setSelectedPersonalDocumentId(null); setDocumentEditDraft(null); }
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
    if (item.screen === "member-profile") {
      setSelectedMember(null);
    } else {
      setSelectedMember(null);
    }
    if (item.screen === "my-documents") { setSelectedAdministrativeDocumentId(null); setDocumentEditDraft(null); }
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
  const backendMemberHeaderLabel =
    screen === "member-profile" && selectedMember?.backendId
      ? "Club member record"
      : screen === "members" ||
          (screen === "nav-detail" && navDetail.parent === "People" && navDetail.title.includes("Member") && navDetail.title !== "Committee Members") ||
          (screen === "people-module" && peopleSection === "Members")
        ? "Club member directory"
        : screen === "celebrations" ||
            (screen === "nav-detail" && navDetail.parent === "People" && ["Today", "This Week", "Birthdays", "Anniversaries"].includes(navDetail.title)) ||
            (screen === "people-module" && peopleSection === "Celebrations")
          ? "Club member celebrations"
          : screen === "committees" ||
              screen === "committee-detail" ||
              (screen === "people-module" && peopleSection === "Board & Committees")
            ? "Club member selector"
            : screen === "my-fellowship" ||
                screen === "fellowship-administration" ||
                screen === "fellowship-queue" ||
                screen === "confirm-sponsors" ||
                screen === "fellowship-sponsor-detail" ||
                (screen === "operations-module" && operationsSection === "Fellowship")
              ? "Fellowship member records"
              : ["ledger", "my-finance-summary", "my-dues", "member-dues-detail", "record-member-payment", "receipt-list", "payment-history"].includes(screen) ||
                  (screen === "finance-module" && ["Member Ledger / Dues", "Payments / Receipts"].includes(financeSection))
                ? "Member finance records"
                : screen === "donor-search"
                  ? "Authorized member search"
                  : screen === "member-import-preview"
                    ? "Member import records"
                    : screen === "club-visibility-module" && ["Members", "Celebrations"].includes(clubVisibilitySection)
                      ? "Club member records"
                      : null;

  return (
    <MemberDataContext.Provider value={{ members: currentMembers, status: memberStatus, activeMemberCount, activeMemberStatus, error: memberLoadError, token: memberToken, clubId: memberClubId, authenticatedUserId, retry: () => setMemberRefreshKey((key) => key + 1) }}>
    <main className="shell">
      <style>{`
        .sidebar {
          display: flex !important;
          flex-direction: column !important;
          height: 100vh !important;
          overflow: hidden !important;
          position: sticky !important;
          top: 0 !important;
          gap: 18px !important;
        }
        .sidebar nav {
          overflow-y: auto !important;
          flex: 1 !important;
          min-height: 0 !important;
          padding-right: 4px !important;
          scrollbar-width: thin;
        }
        .sidebar nav::-webkit-scrollbar {
          width: 6px;
        }
        .sidebar nav::-webkit-scrollbar-track {
          background: transparent;
        }
        .sidebar nav::-webkit-scrollbar-thumb {
          background: rgba(255, 255, 255, 0.2);
          border-radius: 3px;
        }
        .sidebar nav::-webkit-scrollbar-thumb:hover {
          background: rgba(255, 255, 255, 0.4);
        }
        .shell,
        .workspace,
        .workspace > * {
          min-width: 0;
        }
        .memberProfileScreen,
        .memberProfileScreen > *,
        .memberProfileScreen .detailGrid,
        .memberProfileScreen .profilePanel,
        .memberProfileScreen .fieldsPanel,
        .memberProfileScreen .fieldGrid,
        .memberProfileScreen .fieldRow,
        .memberProfileScreen .fieldRow > * {
          min-width: 0;
        }
        .memberProfileScreen .detailGrid {
          grid-template-columns: minmax(220px, 0.7fr) minmax(0, 1.3fr);
          align-items: start;
        }
        .memberProfileScreen .fieldRow strong {
          max-width: 65%;
          overflow-wrap: anywhere;
          word-break: break-word;
          text-align: right;
          white-space: normal;
        }
        @media (max-width: 1200px) {
          .memberProfileScreen .detailGrid {
            grid-template-columns: minmax(0, 1fr);
          }
        }
        @media (max-width: 980px) {
          .sidebar {
            position: static !important;
            height: auto !important;
          }
          .sidebar nav {
            max-height: 45vh;
          }
        }
        @media (max-width: 640px) {
          .dashboardStack,
          .dashboardIntro,
          .dashboardIntro > *,
          .dashboardStack .compactPanel,
          .dashboardStack .memberPanel {
            width: 100%;
            max-width: 100%;
            min-width: 0;
            box-sizing: border-box;
          }
          .dashboardIntro .statusPill,
          .topbar .statusPill {
            white-space: normal;
            overflow-wrap: anywhere;
          }
          .memberProfileScreen .fieldRow {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 4px;
          }
          .memberProfileScreen .fieldRow strong {
            max-width: none;
            text-align: left;
          }
        }
      `}</style>
      <aside className="sidebar" aria-label="Primary navigation">
        <div className="brand">
          <div className="brandMark">LC</div>
          <div>
            <strong>LC Beenachi Connect</strong>
            <span>Club Operations Platform</span>
          </div>
        </div>

        <div className="prototypeSwitcher">
          <span>Signed in</span>
          <small>{session?.user.email ?? identity}</small>
          <button type="button" onClick={() => void logout()}>Sign out</button>
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
        {role === "member" && activeMember === null ? (
          <>
            <Header
              role={roleLabels[role]}
              user={session?.user.name ?? identity}
              screen={screen}
              reportDetail={reportDetail}
              navTitle={navDetailTitle(navDetail.parent, navDetail.title)}
              selectedMember={selectedMember}
              fellowshipConfirmationTitle={activeConfirmationTitle}
              backendMemberLabel={backendMemberHeaderLabel}
            />
            <section className="compactPanel">
              <div className="notice">
                Personal member information is unavailable. No genuine club member profile is linked.
              </div>
            </section>
          </>
        ) : (
          <>
            <Header
              role={roleLabels[role]}
              user={session?.user.name ?? identity}
              screen={screen}
              reportDetail={reportDetail}
              navTitle={navDetailTitle(navDetail.parent, navDetail.title)}
              selectedMember={selectedMember}
              fellowshipConfirmationTitle={activeConfirmationTitle}
              backendMemberLabel={backendMemberHeaderLabel}
            />
            {screen === "my-fellowship" && (
              <ContextBack
                parent="My Member Area"
                onBack={() => {
                  setScreen("dashboard");
                  setExpandedGroups(role === "member" ? [] : ["My Member Area"]);
                }}
              />
            )}
            {screen === "my-documents" && (
              <ContextBack
                parent="My Member Area"
                onBack={() => {
                  setScreen("dashboard");
                  setExpandedGroups(role === "member" ? [] : ["My Member Area"]);
                }}
              />
            )}
            {screen === "documents-administration" && canManageClubDocuments && (
              <ContextBack
                parent="Operations"
                onBack={() => {
                  setScreen("operations-module");
                  setOperationsSection(null);
                  setExpandedGroups(["Operations"]);
                }}
              />
            )}
            {screen === "member-profile" && profileBackContext ? (
              <ContextBack
                parent={profileBackContext.screen === "nav-detail" ? (profileBackContext.title === "PST Profiles" ? "PST Profiles" : profileBackContext.title || "Back") : (profileBackContext.screen === "members" ? "Member Directory" : "Back")}
                onBack={() => {
                  if (profileBackContext.screen === "nav-detail") {
                    setNavDetail({
                      parent: profileBackContext.parent || "People",
                      title: profileBackContext.title || "Detail"
                    });
                    setExpandedGroups([profileBackContext.parent || "People"]);
                  }
                  setScreen(profileBackContext.screen);
                  setProfileBackContext(null);
                }}
              />
            ) : (
              activeParent && screen !== "finance-module" && screen !== "my-fellowship" && screen !== "my-documents" && screen !== "documents-administration" && screen !== "dashboard" && screen !== "login" && screen !== "nav-detail" && screen !== "report-detail" && screen !== "announcement-detail" && (
                <ContextBack
                  parent={screen === "fellowship-administration" ? "Operations" : activeParent}
                  onBack={() => {
                    if (screen === "fellowship-administration") {
                      setScreen("operations-module");
                      setOperationsSection(null);
                      setEventMeetingSection(null);
                      setExpandedGroups(["Operations"]);
                      setLastRecordedPayment(null);
                      setHighlightedSponsor(null);
                    } else {
                      setScreen(parentScreen(activeParent));
                      setCollectionDetail(null);
                      setEventMeetingSection(null);
                      setExpandedGroups([activeParent]);
                    }
                  }}
                />
              )
            )}
            {screen === "dashboard" && (
              <Dashboard
                role={role}
                member={activeMember!}
                setScreen={handleSetScreen}
                openDashboardDetail={openDashboardDetail}
                totalOutstanding={totalOutstanding}
                months={fellowshipMonths}
                selectedMonthKey={selectedFellowshipMonthKey}
                setSelectedMonthKey={setSelectedFellowshipMonthKey}
              />
            )}
            {screen === "dashboard-detail" && dashboardDetail && (
              <DashboardDetailScreen
                detail={dashboardDetail}
                role={role}
                member={activeMember!}
                setScreen={handleSetScreen}
                openDashboardDetail={openDashboardDetail}
                amountPerSponsor={amountPerSponsor}
              />
            )}
            {screen === "people-module" && (
              <PeopleModule
                section={peopleSection}
                setSection={setPeopleSection}
                role={role}
                setScreen={handleSetScreen}
                onSelectMember={selectAndOpenMemberProfile}
              />
            )}
            {screen === "operations-module" && (
              <OperationsModule
                section={operationsSection}
                setSection={setOperationsSection}
                role={role}
                member={activeMember!}
                setScreen={handleSetScreen}
                tab={fellowshipTab}
                setTab={setFellowshipTab}
                selectedSponsors={activeSelectedSponsors}
                setSelectedSponsors={activeSetSelectedSponsors}
                tempQueueStatuses={activeTempQueueStatuses}
                setTempQueueStatuses={activeSetTempQueueStatuses}
                handleSelectSponsor={handleSelectSponsor}
                handleSkipHoldSponsor={handleSkipHoldSponsor}
                handleResetSponsor={handleResetSponsor}
                setSelectedSponsor={setSelectedSponsor}
                setSelectedSponsorPayment={setSelectedSponsorPayment}
                fellowshipConfirmationMessage={fellowshipConfirmationMessage}
                setFellowshipConfirmationMessage={setFellowshipConfirmationMessage}
                totalFellowshipExpense={activeTotalFellowshipExpense}
                totalFellowshipExpenseInput={activeTotalFellowshipExpenseInput}
                setTotalFellowshipExpenseInput={activeSetTotalFellowshipExpenseInput}
                sponsorPayments={activeSponsorPayments}
                setSponsorPayments={activeSetSponsorPayments}
                sponsorPaymentHistories={activeSponsorPaymentHistories}
                setSponsorPaymentHistories={activeSetSponsorPaymentHistories}
                sponsorPaymentInputs={activeSponsorPaymentInputs}
                setSponsorPaymentInputs={activeSetSponsorPaymentInputs}
                isFellowshipConfirmed={activeIsFellowshipConfirmed}
                setIsFellowshipConfirmed={activeSetIsFellowshipConfirmed}
                confirmedSponsors={activeConfirmedSponsors}
                setConfirmedSponsors={activeSetConfirmedSponsors}
                confirmedSharePerSponsor={activeConfirmedSharePerSponsor}
                setConfirmedSharePerSponsor={activeSetConfirmedSharePerSponsor}
                isFellowshipMonthClosed={isFellowshipMonthClosed}
                setIsFellowshipMonthClosed={setIsFellowshipMonthClosed}
                isJulyRotationActive={isJulyRotationActive}
                setIsJulyRotationActive={setIsJulyRotationActive}
                juneSnapshot={juneSnapshot}
                setJuneSnapshot={setJuneSnapshot}
            fellowshipOutstandingDues={fellowshipOutstandingDues}
            setFellowshipOutstandingDues={setFellowshipOutstandingDues}
            amountPerSponsor={activeAmountPerSponsor}
            expandedHistories={activeExpandedHistories}
            setExpandedHistories={activeSetExpandedHistories}
            lastRecordedPayment={activeLastRecordedPayment}
            setLastRecordedPayment={activeSetLastRecordedPayment}
            highlightedSponsor={activeHighlightedSponsor}
            setHighlightedSponsor={activeSetHighlightedSponsor}
            months={fellowshipMonths}
            setMonths={setFellowshipMonths}
            selectedMonthKey={selectedFellowshipMonthKey}
            setSelectedMonthKey={setSelectedFellowshipMonthKey}
          />
        )}
                {screen === "finance-module" && (
                  financeSection === "Club Account Statement"
                    ? <ClubAccountStatementBackend role={role} />
                    : financeSection === "Record Expense"
                      ? <ClubExpenseBackend role={role} />
                    : <FinanceBackend role={role} view={financeSection === "Outstanding Dues" ? "outstanding" : financeSection === "Member Ledger / Dues" ? "summary" : "workspace"} onRecordPayment={openOutstandingPayment} />
                )}
        {screen === "reports-tools-module" && (
          <ReportsBackend role={role} onReminders={() => handleSetScreen("notifications-reminders")} />
        )}
        {screen === "notifications-reminders" && (
          <RemindersBackend role={role} onBack={() => handleSetScreen("reports-tools-module")} />
        )}
        {screen === "report-detail" && (
          reportDetail === "Profile Update Pending Report" && role !== "president" && role !== "secretary"
            ? <section className="compactPanel"><p className="eyebrow">PROFILE UPDATE PENDING</p><h2>Unauthorized</h2><div className="notice">This account role is not authorized to access club-wide profile update administration.</div></section>
            : reportDetail === "Profile Update Pending Report"
              ? <ProfileUpdatesBackend role={role} mode="review" />
              : <ReportDetailScreen section={reportsSection} detail={reportDetail} setScreen={handleSetScreen} onSelectMember={selectAndOpenMemberProfile} amountPerSponsor={activeAmountPerSponsor} selectedSponsors={activeConfirmedSponsors.length ? activeConfirmedSponsors : activeSelectedSponsors} sponsorPayments={activeSponsorPayments} />
        )}
        {screen === "nav-detail" && (
          <NavDetailScreen
            parent={navDetail.parent}
            title={navDetail.title}
            role={role}
            setScreen={handleSetScreen}
            onSelectMember={selectAndOpenMemberProfile}
            onSelectUpdate={openProfileUpdateDetail}
            onSelectCommittee={openCommitteeDetail}
            onBackToCelebrations={() => {
              setPeopleSection("Celebrations");
              handleSetScreen("people-module");
            }}
            onSelectDocument={openDocumentDetail}
            onSelectActivity={openActivityDetail}
            amountPerSponsor={amountPerSponsor}
          />
        )}
        {screen === "club-document-detail" && (
          <ClubDocumentDetail item={selectedDocument} setScreen={handleSetScreen} />
        )}
        {screen === "personal-document-detail" && (
          <PersonalDocumentDetail
            document={documentsVisibleToRole(role).find((item) => item.id === selectedPersonalDocumentId) || null}
            onBack={() => {
              setSelectedPersonalDocumentId(null);
              setScreen("my-documents");
              setExpandedGroups(role === "member" ? [] : ["My Member Area"]);
            }}
          />
        )}
        {screen === "document-administration-edit" && (canManageClubDocuments ? (
          <DocumentAdministrationEdit
            document={documents.find((item) => item.id === selectedAdministrativeDocumentId) || null}
            draft={documentEditDraft}
            setDraft={setDocumentEditDraft}
            onCancel={cancelAdministrativeDocumentEdit}
          />
        ) : (
          <section className="compactPanel"><p className="eyebrow">DOCUMENT ADMINISTRATION</p><h2>Unauthorized</h2><div className="notice">This account role is not authorized to manage club documents.</div></section>
        ))}
        {screen === "activity-detail" && (
          <ActivityDetail
            item={selectedActivity}
            onBack={(sourceTitle) => {
              setNavDetail({ parent: "Operations", title: sourceTitle });
              handleSetScreen("nav-detail");
            }}
          />
        )}
        {screen === "committee-detail" && (
          <CommitteeDetail committee={selectedCommittee} setScreen={handleSetScreen} />
        )}
        {screen === "profile-update-detail" && (
          role === "president" || role === "secretary"
            ? <ProfileUpdatesBackend role={role} mode="review" />
            : <section className="compactPanel"><p className="eyebrow">PROFILE UPDATE PENDING</p><h2>Unauthorized</h2><div className="notice">This account role is not authorized to access club-wide profile update administration.</div></section>
        )}
        {screen === "announcement-detail" && (
          <AnnouncementDetailScreen
            detail={announcementDetail}
            setScreen={handleSetScreen}
            onSelectAnnouncement={openAnnouncementDetail}
          />
        )}
        {screen === "announcement-single-detail" && (
          <AnnouncementSingleDetail item={selectedAnnouncement} setScreen={handleSetScreen} />
        )}
        {screen === "club-visibility-module" && (
          <ClubVisibilityModule
            section={clubVisibilitySection}
            setSection={setClubVisibilitySection}
            role={role}
            member={activeMember!}
            selectedSponsors={selectedSponsors}
            setScreen={handleSetScreen}
            onSelectMember={selectAndOpenMemberProfile}
          />
        )}
        {screen === "members" && <MembersList setScreen={handleSetScreen} role={role} onSelectMember={selectAndOpenMemberProfile} />}
        {screen === "committees" && <Committees />}
        {screen === "member-profile" && (selectedMember ? <MemberProfile key={selectedMember.backendId} member={selectedMember} role={role} /> : <PersonalMemberProfile role={role} />)}
        {screen === "digital-card" && <PersonalDigitalCard />}
        {screen === "my-fellowship" && (
          <MemberFellowship
            authenticatedUserId={authenticatedUserId}
            months={fellowshipMonths}
            selectedMonthKey={selectedFellowshipMonthKey}
          />
        )}
        {screen === "fellowship-administration" && (canAccessFellowshipAdministration ? (
          <FellowshipDashboard
            tab={fellowshipTab}
            setTab={setFellowshipTab}
            months={fellowshipMonths}
            setMonths={setFellowshipMonths}
            selectedMonthKey={selectedFellowshipMonthKey}
            setSelectedMonthKey={setSelectedFellowshipMonthKey}
          />
        ) : (
          <section className="compactPanel">
            <p className="eyebrow">FELLOWSHIP ADMINISTRATION</p>
            <h2>Unauthorized</h2>
            <div className="notice">This account role is not authorized to access club-wide Fellowship administration.</div>
          </section>
        ))}
        {screen === "fellowship-queue" && canAccessFellowshipAdministration && (
          <FellowshipQueue
            selectedSponsors={activeSelectedSponsors}
            confirmationTitle={activeConfirmationTitle}
            setScreen={handleSetScreen}
            setFellowshipTab={setFellowshipTab}
            setHighlightedSponsor={activeSetHighlightedSponsor}
            tempQueueStatuses={activeTempQueueStatuses}
            handleSelectSponsor={handleSelectSponsor}
            handleSkipHoldSponsor={handleSkipHoldSponsor}
            handleResetSponsor={handleResetSponsor}
            amountPerSponsor={activeAmountPerSponsor}
            totalFellowshipExpense={activeTotalFellowshipExpense}
            totalFellowshipExpenseInput={activeTotalFellowshipExpenseInput}
            isValidExpense={activeIsValidExpense}
            isFellowshipConfirmed={activeIsFellowshipConfirmed}
          />
        )}
        {screen === "confirm-sponsors" && canAccessFellowshipAdministration && (
          <ConfirmSponsors
            title={activeConfirmationTitle}
            monthLabel={activeFellowshipMonthLabel}
            selectedSponsors={activeSelectedSponsors}
            setScreen={handleSetScreen}
            setFellowshipTab={setFellowshipTab}
            setFellowshipConfirmationMessage={setFellowshipConfirmationMessage}
            amountPerSponsor={activeAmountPerSponsor}
            totalFellowshipExpense={activeTotalFellowshipExpense}
            setIsFellowshipConfirmed={activeSetIsFellowshipConfirmed}
            totalFellowshipExpenseInput={activeTotalFellowshipExpenseInput}
            setConfirmedSponsors={activeSetConfirmedSponsors}
            setConfirmedSharePerSponsor={activeSetConfirmedSharePerSponsor}
            setSponsorPayments={activeSetSponsorPayments}
            setSponsorPaymentInputs={activeSetSponsorPaymentInputs}
            setSponsorPaymentHistories={activeSetSponsorPaymentHistories}
            setExpandedHistories={activeSetExpandedHistories}
            setLastRecordedPayment={activeSetLastRecordedPayment}
            setHighlightedSponsor={activeSetHighlightedSponsor}

          />
        )}
        {screen === "fellowship-sponsor-detail" && canAccessFellowshipAdministration && (
          selectedSponsor ? (
            <FellowshipSponsorDetail
              sponsor={selectedSponsor}
              onBack={() => {
                setSelectedSponsor(null);
                setFellowshipTab("Selected Sponsors");
                setScreen("fellowship-administration");
              }}
            />
          ) : selectedSponsorPayment ? (
            <FellowshipSponsorDetail
              sponsor={selectedSponsorPayment}
              onBack={() => {
                setSelectedSponsorPayment(null);
                setFellowshipTab("Payments");
                setScreen("fellowship-administration");
              }}
            />
          ) : null
        )}
        {screen === "my-finance-summary" && <FinanceBackend role={role} view="summary" memberId={activeMember!.backendId} isPersonal={isPersonalFinanceScreen} />}
        {screen === "my-dues" && <FinanceBackend role={role} view="dues" memberId={activeMember!.backendId} isPersonal={isPersonalFinanceScreen} onReturnToSummary={() => handleSetScreen("my-finance-summary")} />}
        {screen === "ledger" && <FinanceBackend role={role} view="summary" />}
        {screen === "member-dues-detail" && <FinanceBackend role={role} memberId={activeMember!.backendId} isPersonal={isPersonalFinanceScreen} />}
        {screen === "special-collection" && <FinanceBackend role={role} memberId={activeMember!.backendId} isPersonal={isPersonalFinanceScreen} />}
        {/* 3AR validation baseline: {screen === "record-member-payment" && <FinanceBackend role={role} memberId={activeMember!.backendId} isPersonal={isPersonalFinanceScreen} />} */}
        {screen === "record-member-payment" && <FinanceBackend role={role} view="record-payment" memberId={financePaymentContext?.memberId} obligationId={financePaymentContext?.obligationId} onBack={() => handleSetScreen("outstanding-dues")} />}
        {screen === "collection-box" && <CollectionBoxReceipt role={role} />}
        {/* 3AX baseline: {screen === "receipt-list" && <FinanceBackend role={role} memberId={activeMember!.backendId} isPersonal={isPersonalFinanceScreen} />} */}
        {/* 3IF baseline: {screen === "payment-history" && <FinanceBackend role={role} memberId={activeMember!.backendId} isPersonal={isPersonalFinanceScreen} />} */}
        {screen === "receipt-list" && <FinanceBackend role={role} view="payment-history" isPersonal={role === "member"} />}
        {screen === "payment-history" && <FinanceBackend role={role} view="payment-history" memberId={role === "member" ? activeMember!.backendId : undefined} isPersonal={role === "member"} onReturnToSummary={role === "member" ? () => handleSetScreen("my-finance-summary") : undefined} />}
        {screen === "member-import-preview" && <MemberImportPreview role={role} />}
        {screen === "outstanding-dues" && <FinanceBackend role={role} view="outstanding" onRecordPayment={openOutstandingPayment} />}
        {screen === "collection-summary" && <FinanceBackend role={role} memberId={activeMember!.backendId} isPersonal={isPersonalFinanceScreen} />}
        {screen === "fellowship-payments" && <FellowshipPayments view={fellowshipPaymentView} setScreen={handleSetScreen} amountPerSponsor={amountPerSponsor} />}
        {screen === "events-meetings" && (
          <EventsMeetings
            role={role}
            member={activeMember!}
            selectedSponsors={selectedSponsors}
            setScreen={handleSetScreen}
            selectedSection={eventMeetingSection}
            onSelectEventMeeting={openEventMeetingDetail}
          />
        )}
        {screen === "event-meeting-detail" && (
          <EventMeetingDetail item={selectedEventMeeting} setScreen={handleSetScreen} />
        )}
        {screen === "announcements" && <Announcements role={role} member={activeMember!} selectedSponsors={selectedSponsors} />}
        {screen === "my-documents" && <PersonalProfileDocuments />}
        {screen === "documents-administration" && (canManageClubDocuments ? (
          <DocumentAdministration onEdit={startAdministrativeDocumentEdit} />
        ) : (
          <section className="compactPanel"><p className="eyebrow">DOCUMENT ADMINISTRATION</p><h2>Unauthorized</h2><div className="notice">This account role is not authorized to access club document management.</div></section>
        ))}
        {screen === "projects" && <ProjectsActivities role={role} setScreen={handleSetScreen} />}
        {screen === "celebrations" && <Celebrations role={role} setScreen={handleSetScreen} />}
        {screen === "donor-consent" && <DonorConsent onOpenRequests={() => handleSetScreen("member-profile")} />}
        {screen === "donor-search" && <DonorSearch bloodGroup={bloodGroup} setBloodGroup={setBloodGroup} allowedDonors={allowedDonors} />}
        {screen === "reports" && <Reports />}
        {screen === "settings" && <Settings setScreen={handleSetScreen} />}
          </>
        )}
      </section>
    </main>
    </MemberDataContext.Provider>
  );
}

function Header({
  role,
  user,
  screen,
  reportDetail,
  navTitle,
  selectedMember,
  fellowshipConfirmationTitle,
  backendMemberLabel
}: {
  role: string;
  user: string;
  screen: Screen;
  reportDetail: string;
  navTitle: string;
  selectedMember: Member | null;
  fellowshipConfirmationTitle: string;
  backendMemberLabel: string | null;
}) {
  const title = (screen === "my-dues" || (role === "Member" && screen === "ledger"))
    ? "My Dues"
    : screen === "member-profile" && selectedMember !== null
      ? "Member Profile"
      : screen === "report-detail"
        ? reportDetail
        : screen === "nav-detail"
          ? navTitle
          : screen === "confirm-sponsors"
            ? fellowshipConfirmationTitle
          : screenTitle(screen);
  const sourceLabel = screen === "login"
    ? "SECURE ACCOUNT ACCESS"
    : screen === "dashboard"
      ? "CLUB OPERATIONS DASHBOARD"
      : backendMemberLabel || "CLUB OPERATIONS WORKFLOW";
  return (
    <header className="topbar">
      <div>
        <p className="eyebrow">{sourceLabel}</p>
        <h1>{title}</h1>
      </div>
      <div className="statusPill">{screen === "login" ? `Account: ${user}` : `Signed in as ${user}`}</div>
    </header>
  );
}

function screenTitle(screen: Screen) {
  const names: Record<Screen, string> = {
    login: "Local Prototype Access",
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
    "my-fellowship": "My Fellowship",
    "fellowship-administration": "Fellowship Administration",
    "fellowship-queue": "Fellowship Queue",
    "confirm-sponsors": "Review & Confirm June 2026 Sponsors",
    "fellowship-sponsor-detail": "Fellowship Sponsor Details",
    ledger: "Member Ledger / Dues",
    "my-finance-summary": "My Finance Summary",
    "my-dues": "My Dues",
    "profile-update-detail": "Profile Update Request",
    "committee-detail": "Committee Details",
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
    "event-meeting-detail": "Event / Meeting Details",
    announcements: "Announcements",
    "announcement-detail": "Announcements",
    "announcement-single-detail": "Announcement Details",
    "club-document-detail": "Document Details",
    "activity-detail": "Activity Details",
    "my-documents": "My Profile Documents",
    "documents-administration": "Document Administration",
    "document-administration-edit": "Edit Club Document",
    "personal-document-detail": "Document Details",
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
          {value === "" && (
            <option value="" disabled hidden>
              {title === "Operations" ? "Select an operations section" : "Select a section"}
            </option>
          )}
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
  setScreen,
  onSelectMember
}: {
  section: PeopleSection;
  setSection: (section: PeopleSection) => void;
  role: Role;
  setScreen: (screen: Screen) => void;
  onSelectMember?: (member: Member) => void;
}) {
  const options: PeopleSection[] = ["Members", "Board & Committees", "Celebrations"];
  return (
    <section className="stack">
      <ModuleHeader title="People" label="Choose people section" value={section} options={options} onChange={setSection} setScreen={setScreen} />
      {section === "Members" && <MembersList setScreen={setScreen} role={role} onSelectMember={onSelectMember} />}
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
  selectedSponsors,
  setSelectedSponsors,
  tempQueueStatuses,
  setTempQueueStatuses,
  handleSelectSponsor,
  handleSkipHoldSponsor,
  handleResetSponsor,
  setSelectedSponsor,
  setSelectedSponsorPayment,
  fellowshipConfirmationMessage,
  setFellowshipConfirmationMessage,
  totalFellowshipExpense,
  totalFellowshipExpenseInput,
  setTotalFellowshipExpenseInput,
  sponsorPayments,
  setSponsorPayments,
  sponsorPaymentHistories,
  setSponsorPaymentHistories,
  sponsorPaymentInputs,
  setSponsorPaymentInputs,
  isFellowshipConfirmed,
  setIsFellowshipConfirmed,
  confirmedSponsors,
  setConfirmedSponsors,
  confirmedSharePerSponsor,
  setConfirmedSharePerSponsor,
  isFellowshipMonthClosed,
  setIsFellowshipMonthClosed,
  isJulyRotationActive,
  setIsJulyRotationActive,
  juneSnapshot,
  setJuneSnapshot,
  fellowshipOutstandingDues,
  setFellowshipOutstandingDues,
  amountPerSponsor,
  expandedHistories,
  setExpandedHistories,
  lastRecordedPayment,
  setLastRecordedPayment,
  highlightedSponsor,
  setHighlightedSponsor,
  months,
  setMonths,
  selectedMonthKey,
  setSelectedMonthKey
}: {
  section: OperationsSection | null;
  setSection: (section: OperationsSection | null) => void;
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  tab: FellowshipTab;
  setTab: (tab: FellowshipTab) => void;
  selectedSponsors: string[];
  setSelectedSponsors: React.Dispatch<React.SetStateAction<string[]>>;
  tempQueueStatuses: Record<string, string>;
  setTempQueueStatuses: React.Dispatch<React.SetStateAction<Record<string, string>>>;
  handleSelectSponsor: (name: string) => void;
  handleSkipHoldSponsor: (name: string) => void;
  handleResetSponsor: (name: string) => void;
  setSelectedSponsor: (sponsor: { memberId: string; name: string; amount: number; amountPaid: number; balanceRemaining: number; paymentStatus: string; status: string; source: "Selected Sponsors" | "Payments" } | null) => void;
  setSelectedSponsorPayment: (sponsor: { memberId: string; name: string; amount: number; amountPaid: number; balanceRemaining: number; paymentStatus: string; status: string; source: "Payments" } | null) => void;
  fellowshipConfirmationMessage: string | null;
  setFellowshipConfirmationMessage: (msg: string | null) => void;
  totalFellowshipExpense: number;
  totalFellowshipExpenseInput: string;
  setTotalFellowshipExpenseInput: (val: string) => void;
  sponsorPayments: Record<string, number>;
  setSponsorPayments: React.Dispatch<React.SetStateAction<Record<string, number>>>;
  sponsorPaymentHistories: Record<string, Array<{ id: string; date: string; amount: number }>>;
  setSponsorPaymentHistories: React.Dispatch<React.SetStateAction<Record<string, Array<{ id: string; date: string; amount: number }>>>>;
  sponsorPaymentInputs: Record<string, string>;
  setSponsorPaymentInputs: React.Dispatch<React.SetStateAction<Record<string, string>>>;
  isFellowshipConfirmed: boolean;
  setIsFellowshipConfirmed: (val: boolean) => void;
  confirmedSponsors: string[];
  setConfirmedSponsors: (val: string[]) => void;
  confirmedSharePerSponsor: number;
  setConfirmedSharePerSponsor: (val: number) => void;
  isFellowshipMonthClosed: boolean;
  setIsFellowshipMonthClosed: (val: boolean) => void;
  isJulyRotationActive: boolean;
  setIsJulyRotationActive: (val: boolean) => void;
  juneSnapshot: {
    totalExpense: number;
    confirmedSponsors: string[];
    sharePerSponsor: number;
    payments: Record<string, number>;
    paymentHistories: Record<string, Array<{ id: string; date: string; amount: number }>>;
    isClosed: boolean;
  } | null;
  setJuneSnapshot: React.Dispatch<React.SetStateAction<{
    totalExpense: number;
    confirmedSponsors: string[];
    sharePerSponsor: number;
    payments: Record<string, number>;
    paymentHistories: Record<string, Array<{ id: string; date: string; amount: number }>>;
    isClosed: boolean;
  } | null>>;
  fellowshipOutstandingDues: Array<{
    id: string;
    heading: string;
    memberName: string;
    month: string;
    share: number;
    paid: number;
    balance: number;
    source: string;
    status: string;
    transferDate: string;
  }>;
  setFellowshipOutstandingDues: React.Dispatch<React.SetStateAction<Array<{
    id: string;
    heading: string;
    memberName: string;
    month: string;
    share: number;
    paid: number;
    balance: number;
    source: string;
    status: string;
    transferDate: string;
  }>>>;
  amountPerSponsor: number;
  expandedHistories: Record<string, boolean>;
  setExpandedHistories: React.Dispatch<React.SetStateAction<Record<string, boolean>>>;
  lastRecordedPayment: {
    memberName: string;
    paymentAmount: number;
    cumulativePaid: number;
    remainingBalance: number;
    type: "partial" | "full";
  } | null;
  setLastRecordedPayment: (val: {
    memberName: string;
    paymentAmount: number;
    cumulativePaid: number;
    remainingBalance: number;
    type: "partial" | "full";
  } | null) => void;
  highlightedSponsor: string | null;
  setHighlightedSponsor: (val: string | null) => void;
  months: Record<string, FellowshipMonthRecord>;
  setMonths: React.Dispatch<React.SetStateAction<Record<string, FellowshipMonthRecord>>>;
  selectedMonthKey: string;
  setSelectedMonthKey: (key: string) => void;
}) {
  const options: OperationsSection[] = ["Events & Meetings", "Announcements", "Documents", "Projects / Activities", "Fellowship"];
  return (
    <section className="stack">
      <ModuleHeader<OperationsSection | "">
        title="Operations"
        label="Choose operations section"
        value={section ?? ""}
        options={options}
        onChange={(value) =>
          setSection(value ? (value as OperationsSection) : null)
        }
        setScreen={setScreen}
      />
      {section === "Events & Meetings" && <EventsMeetings role={role} member={member} selectedSponsors={selectedSponsors} setScreen={setScreen} />}
      {section === "Announcements" && <Announcements role={role} member={member} selectedSponsors={selectedSponsors} />}
      {section === "Documents" && <div className="compactPanel"><p>Club document management has a separate authorized workspace.</p><button onClick={() => setScreen("documents-administration")}>Open Document Administration</button></div>}
      {section === "Projects / Activities" && <ProjectsActivities role={role} setScreen={setScreen} />}
      {section === "Fellowship" && (
        <FellowshipDashboard
          tab={tab}
          setTab={setTab}
          months={months}
          setMonths={setMonths}
          selectedMonthKey={selectedMonthKey}
          setSelectedMonthKey={setSelectedMonthKey}
        />
      )}
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
  fellowshipPaymentView,
  amountPerSponsor
}: {
  section: FinanceSection;
  statementSection: ClubStatementSection;
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  collectionDetail: CollectionDetail;
  setCollectionDetail: (detail: CollectionDetail) => void;
  fellowshipPaymentView: FellowshipPaymentView;
  amountPerSponsor: number;
}) {
  return (
    <section className="stack">
      {section === "Club Account Statement" && <ClubAccountStatement section={statementSection} />}
      {section === "Member Ledger / Dues" && <Ledger role={role} member={member} setScreen={setScreen} amountPerSponsor={amountPerSponsor} />}
      {section === "Payments / Receipts" && <PaymentsReceiptsHub setScreen={setScreen} />}
      {section === "Club Finance Reports" && <ClubFinanceReportsPanel role={role} displayMode="reports" />}
      {section === "Balance Sheet Summary" && <ClubFinanceReportsPanel role={role} displayMode="balance-sheet" />}
      {section === "Outstanding Dues" && <OutstandingDues setScreen={setScreen} amountPerSponsor={amountPerSponsor} />}
      {section === "Collection Summary" && <CollectionSummary detail={collectionDetail} setDetail={setCollectionDetail} amountPerSponsor={amountPerSponsor} />}
      {section === "Fellowship Payments" && <FellowshipPayments view={fellowshipPaymentView} setScreen={setScreen} amountPerSponsor={amountPerSponsor} />}
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
  const { session } = useDevAuth();
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
        if (!session) throw new Error("unauthenticated");
        const token = session.token;
        const loadedClubId = session.membership.club.id;

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
  }, [canViewClubFinanceReports, role, session]);

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
              <Metric label="Kuri module status" value="Deferred" />
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
                    <span>Future kuri ledger head. Posting is not available.</span>
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

  if (!verifiedOperationalSourceConnected("reminders")) return (
    <section className="stack">
      <Toolbar title="Notifications & Reminders" actionLabel="Not connected" />
      <div className="notice">Reminder scheduling is not connected yet.</div>
      <div className="notice memberHelperText">Reminders can be created only after a verified persistence workflow is connected.</div>
      <button className="secondaryButton" onClick={() => setScreen("dashboard")}>Back to Dashboard</button>
    </section>
  );

  return (
    <section className="stack">
      <Toolbar title="Notifications & Reminders" actionLabel="Preview tools only" />
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
          <div className="notice">Members see only today&apos;s celebrations and recently completed celebrations. Secretary and President manage official greeting status here.</div>
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
          <div className="notice">Announcement reminder scheduling is not supported. Use the authoritative Announcements workspace to review current records.</div>
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
  setScreen,
  onSelectMember
}: {
  section: ClubVisibilitySection;
  setSection: (section: ClubVisibilitySection) => void;
  role: Role;
  member: Member;
  selectedSponsors: string[];
  setScreen: (screen: Screen) => void;
  onSelectMember?: (member: Member) => void;
}) {
  const options: ClubVisibilitySection[] = ["Members", "Events & Meetings", "Announcements", "Documents", "Projects / Activities", "Celebrations"];
  return (
    <section className="stack">
      <ModuleHeader title="Club Visibility" label="Choose view-only section" value={section} options={options} onChange={setSection} setScreen={setScreen} />
      <div className="notice">Treasurer view is mostly read-only outside Finance.</div>
      {section === "Members" && <MembersList setScreen={setScreen} role={role} onSelectMember={onSelectMember} />}
      {section === "Events & Meetings" && <EventsMeetings role={role} member={member} selectedSponsors={selectedSponsors} setScreen={setScreen} />}
      {section === "Announcements" && <Announcements role={role} member={member} selectedSponsors={selectedSponsors} />}
      {section === "Documents" && <div className="compactPanel"><p>Use My Documents for read-only role-authorized document access.</p><button onClick={() => setScreen("my-documents")}>Open My Documents</button></div>}
      {section === "Projects / Activities" && <ProjectsActivities role={role} setScreen={setScreen} />}
      {section === "Celebrations" && <Celebrations role={role} setScreen={setScreen} />}
    </section>
  );
}

function ReportCategory({ section }: { section: ReportsSection }) {
  const rows: Record<ReportsSection, string[]> = {
    "Member Reports": ["Active member list", "Profile update pending report", "Role-wise member report"],
    "Finance / Ledger Reports": ["Member-wise outstanding report", "Payment collection report", "Monthly finance summary"],
    "Fellowship Reports": ["Current month sponsorship report", "Sponsor payment report", "Queue history report"],
    "Events & Meetings Reports": ["Events & Meetings Register"],
    "Club Activity / Service Reports": ["Projects & Activities Register"],
    "Celebration Reports": ["Monthly birthday report", "Monthly anniversary report", "Celebration register"],
    "Blood Donor Consent Report": ["Consented donor list", "Blood group summary", "Contact visibility report"],
    "Document Reports": ["Published document report", "Board-only document report", "Pending upload report"]
  };
  return (
    <div className="reportGrid">
      {rows[section].map((report) => (
        <div className="reportCard" key={report}>
          <span>{section}</span>
          <strong>{report}</strong>
        </div>
      ))}
    </div>
  );
}

function ReportDetailScreen({
  section,
  detail,
  setScreen,
  onSelectMember,
  amountPerSponsor,
  selectedSponsors,
  sponsorPayments
}: {
  section: ReportsSection;
  detail: string;
  setScreen: (screen: Screen) => void;
  onSelectMember: (member: Member) => void;
  amountPerSponsor?: number;
  selectedSponsors: string[];
  sponsorPayments: Record<string, number>;
}) {
  const { members: currentMembers } = useCurrentMemberData();
  const [reportPeriod, setReportPeriod] = useState<{ month: number; year: number } | null>(null);
  const isMonthlyBirthdayReport = section === "Celebration Reports" && detail === "Monthly Birthday Report";
  const isMonthlyAnniversaryReport = section === "Celebration Reports" && detail === "Monthly Anniversary Report";
  const isMonthlyCelebrationReport = isMonthlyBirthdayReport || isMonthlyAnniversaryReport;
  useEffect(() => {
    if (!isMonthlyCelebrationReport) return;
    const currentDate = new Date();
    void Promise.resolve().then(() => setReportPeriod({ month: currentDate.getMonth() + 1, year: currentDate.getFullYear() }));
  }, [isMonthlyCelebrationReport]);
  if (section === "Finance / Ledger Reports") {
    return <OperationalReportsUnavailable setScreen={setScreen} />;
  }
  if (section === "Fellowship Reports" && detail === "Current Month Sponsorship") {
    return <CurrentMonthSponsorshipReport setScreen={setScreen} amountPerSponsor={amountPerSponsor} selectedSponsors={selectedSponsors} sponsorPayments={sponsorPayments} />;
  }
  if (section === "Fellowship Reports" && detail === "Sponsor Queue History") {
    return <SponsorQueueHistoryReport setScreen={setScreen} />;
  }
  const sourceBackedReport =
    (section === "Member Reports" && ["Active Member List", "Inactive Member List", "Birthday / Anniversary Report"].includes(detail)) ||
    section === "Celebration Reports";
  if (!sourceBackedReport) {
    return <OperationalReportsUnavailable setScreen={setScreen} />;
  }

  const cards = reportDetailCards(section, detail, amountPerSponsor, currentMembers, reportPeriod?.month);
  const memberStatusLabel = detail === "Active Member List" ? "active" : detail === "Inactive Member List" ? "inactive" : null;
  const memberReportCount = memberStatusLabel
    ? `${cards.length} verified ${memberStatusLabel} member${cards.length === 1 ? "" : "s"}`
    : null;
  const periodLabel = isMonthlyCelebrationReport
    ? reportPeriod ? `${celebrationMonths[reportPeriod.month - 1]} ${reportPeriod.year}` : "Loading current period"
    : section === "Celebration Reports" && detail === "Celebration Register" ? "All-time register"
    : section;
  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title={detail} actionLabel={periodLabel} />
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("reports-tools-module")}>← Reports & Tools</button>
        <div className="notice">This report is derived from the verified imported-member source. No export or report-generation mutation is connected.</div>
        {memberReportCount && <div className="notice memberHelperText">{memberReportCount}</div>}
      </div>
      <div className="financeCardList">
        {isMonthlyCelebrationReport && reportPeriod && cards.length === 0 && <div className="notice">{isMonthlyBirthdayReport ? "No birthdays recorded for this period." : "No anniversaries recorded for this period."}</div>}
        {memberStatusLabel && cards.length === 0 && <div className="notice">No verified {memberStatusLabel} members are recorded.</div>}
        {cards.map((card, index) => card.member ? (
          <button className="financeStackCard rowButton" aria-label={`View ${card.member.name} profile`} key={`${section}-${detail}-${card.title}-${index}`} onClick={() => onSelectMember(card.member!)}>
            <h3>{card.title}</h3>
            {card.meta.map((line) => <span key={line}>{line}</span>)}
          </button>
        ) : (
          <article className="financeStackCard" key={`${section}-${detail}-${card.title}-${index}`}>
            <h3>{card.title}</h3>
            {card.meta.map((line) => <span key={line}>{line}</span>)}
          </article>
        ))}
      </div>
    </section>
  );
}

function OperationalReportsUnavailable({ setScreen }: { setScreen: (screen: Screen) => void }) {
  return <section className="stack"><Toolbar title="Operational Reports" actionLabel="Not connected" /><button className="secondaryButton compactBackButton" onClick={() => setScreen("reports-tools-module")}>← Reports & Tools</button><div className="notice">Operational reports are not connected to a verified source yet.</div><div className="notice memberHelperText">Real reports and collection summaries will appear here only after a verified source is connected.</div></section>;
}

function SponsorQueueHistoryReport({ setScreen }: { setScreen: (screen: Screen) => void }) {
  return <section className="stack"><div className="compactPanel"><Toolbar title="Sponsor Queue History" actionLabel="No historical records" /><button className="secondaryButton" onClick={() => setScreen("reports-tools-module")}>← Reports & Tools</button><div className="notice">No historical sponsorship turns were imported. Queue history will appear only after confirmed turns exist.</div></div>
  </section>;
}

function CurrentMonthSponsorshipReport({ setScreen, amountPerSponsor = 0, selectedSponsors, sponsorPayments }: { setScreen: (screen: Screen) => void; amountPerSponsor?: number; selectedSponsors: string[]; sponsorPayments: Record<string, number> }) {
  const { members: currentMembers } = useCurrentMemberData();
  const sponsors = selectedSponsors.map((memberId) => ({ memberId, name: displayMemberName(memberId, currentMembers), paid: sponsorPayments[memberId] || 0 }));
  const collectedAmount = sponsors.reduce((sum, sponsor) => sum + sponsor.paid, 0);
  return <section className="stack"><div className="compactPanel"><Toolbar title="Current Month Sponsorship" actionLabel="Fellowship Reports" /><button className="secondaryButton" onClick={() => setScreen("reports-tools-module")}>← Reports & Tools</button></div>
    <div className="metricsGrid"><Metric label="Total Sponsors Selected" value={`${sponsors.length}`} /><Metric label="Total Sponsorship Amount" value={formatSponsorShare(sponsors.length * amountPerSponsor)} /><Metric label="Collected" value={formatSponsorShare(collectedAmount)} /><Metric label="Pending Collection" value={formatSponsorShare(Math.max(0, sponsors.length * amountPerSponsor - collectedAmount))} /></div>
    {sponsors.length ? <div className="financeCardList">{sponsors.map((sponsor) => <article className="financeStackCard" key={sponsor.memberId}><h3>Month: June 2026</h3><span>Sponsor: {sponsor.name}</span><span>Member ID: {sponsor.memberId}</span><span>Amount: {formatSponsorShare(amountPerSponsor)}</span><span>Payment Status: {sponsor.paid >= amountPerSponsor - 0.01 ? "Paid" : sponsor.paid > 0 ? "Partly paid" : "Pending"}</span></article>)}</div> : <div className="notice">No sponsors selected for this month.</div>}
  </section>;
}
function FinanceLedgerReportDetail({ detail, setScreen, amountPerSponsor, selectedSponsors, sponsorPayments }: { detail: string; setScreen: (screen: Screen) => void; amountPerSponsor?: number; selectedSponsors: string[]; sponsorPayments: Record<string, number> }) {
  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title={detail} actionLabel="Finance / Ledger Reports" />
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("reports-tools-module")}>← Reports & Tools</button>
        <div className="notice">Dummy data only. Real finance reports will be generated from backend ledger later.</div>
      </div>
      {detail === "Monthly Finance Summary" && <MonthlyFinanceSummaryReport />}
      {detail === "Payment Collection Report" && <PaymentCollectionReport />}
      {detail === "Member-wise Outstanding" && <MemberWiseOutstandingReport amountPerSponsor={amountPerSponsor} />}
      {detail === "Fellowship Payment Report" && <FellowshipPaymentReport amountPerSponsor={amountPerSponsor} selectedSponsors={selectedSponsors} sponsorPayments={sponsorPayments} />}
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

function MemberWiseOutstandingReport({ amountPerSponsor }: { amountPerSponsor?: number }) {
  const finalAmountPerSponsor = amountPerSponsor ?? 2500;
  const rows = [
    { member: "Lion Ajith Kumar - Member", outstanding: money(1000), items: ["Monthly dues - June: ₹500", "Special Collection: ₹500"], status: "Pending" },
    { member: "Lion Treasurer User", outstanding: formatSponsorShare(finalAmountPerSponsor), items: [`Fellowship contribution: ${formatSponsorShare(finalAmountPerSponsor)}`], status: "Pending" },
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

function FellowshipPaymentReport({ amountPerSponsor = 0, selectedSponsors, sponsorPayments }: { amountPerSponsor?: number; selectedSponsors: string[]; sponsorPayments: Record<string, number> }) {
  const { members: currentMembers } = useCurrentMemberData();
  if (!selectedSponsors.length) return <div className="notice">No confirmed fellowship sponsors are available.</div>;
  return <div className="financeCardList">{selectedSponsors.map((memberId) => {
    const paid = sponsorPayments[memberId] || 0;
    return <article className="financeStackCard" key={memberId}><h3>Sponsor: {displayMemberName(memberId, currentMembers)}</h3><span>Member ID: {memberId}</span><span>Fellowship Month: June 2026 Fellowship</span><span>Amount: {formatSponsorShare(amountPerSponsor)}</span><span>Payment Status: {paid >= amountPerSponsor - 0.01 ? "Paid" : paid > 0 ? "Partly paid" : "Pending"}</span></article>;
  })}</div>;
}
function NavDetailScreen({
  parent,
  title,
  role,
  setScreen,
  onSelectMember,
  onSelectUpdate,
  onSelectCommittee,
  onBackToCelebrations,
  onSelectDocument,
  onSelectActivity,
  amountPerSponsor
}: {
  parent: string;
  title: string;
  role: Role;
  setScreen: (screen: Screen) => void;
  onSelectMember?: (member: Member) => void;
  onSelectUpdate?: (update: typeof pendingMemberUpdates[number]) => void;
  onSelectCommittee?: (committee: typeof committeesList[number]) => void;
  onBackToCelebrations?: () => void;
  onSelectDocument?: (doc: { title: string; meta: string[]; sourceTitle: string }) => void;
  onSelectActivity?: (activity: {
    name: string;
    date: string;
    categoryCommittee: string;
    peopleServed: string;
    reportStatus: string;
    sourceTitle: string;
  }) => void;
  amountPerSponsor?: number;
}) {
  const { members: currentMembers, status: memberStatus, error: memberError } = useCurrentMemberData();
  const isUnconnectedProjectDetail = parent === "Operations" && ["This Month", "Pending Reports", "Completed Activities", "Category-wise Activities"].includes(title);
  if (isUnconnectedProjectDetail) {
    return <ProjectsActivities role={role} setScreen={setScreen} />;
  }
  const isProfileUpdatePending = parent === "People" && title === "Profile Update Pending";
  const isGovernanceDetail = parent === "People" && governanceDetailTitles.includes(title);
  const boardCommitteeUnavailableStates: Partial<Record<string, string>> = {
    "Board Members": "Board records are not connected to a verified backend source. No board records are shown.",
    "Committee List": "Committee records are not connected to a verified backend source. No committee records are shown.",
    "Committee Members": "Committee membership records are not connected to a verified backend source. Selectable committee membership is unavailable."
  };
  const boardCommitteeUnavailableState = parent === "People" ? boardCommitteeUnavailableStates[title] : undefined;
  if (isProfileUpdatePending && role !== "president" && role !== "secretary") {
    return <section className="compactPanel"><p className="eyebrow">PROFILE UPDATE PENDING</p><h2>Unauthorized</h2><div className="notice">This account role is not authorized to access club-wide profile update administration.</div></section>;
  }
  if (isProfileUpdatePending) {
    return <ProfileUpdatesBackend role={role} mode="review" />;
  }
  if (boardCommitteeUnavailableState) {
    return <section className="stack"><div className="compactPanel"><Toolbar title={title} actionLabel="Not connected" /><button className="secondaryButton compactBackButton" onClick={() => setScreen("members")}>← People</button><div className="notice">{boardCommitteeUnavailableState}</div><div className="notice memberHelperText">Verified board and committee records will appear here only after a backend source and matching permissions are connected.</div></div></section>;
  }
  if (isGovernanceDetail && memberStatus === "loading") {
    return <section className="compactPanel"><Toolbar title={title} actionLabel="Loading" /><div className="notice">Loading verified leadership assignments...</div></section>;
  }
  if (isGovernanceDetail && memberStatus === "error") {
    return <section className="compactPanel"><Toolbar title={title} actionLabel="Unavailable" /><div className="notice">Leadership assignments could not be loaded.{memberError && <span> {memberError}</span>}</div></section>;
  }
  if (parent === "Operations" && title === "Draft / Pending") {
    return <DraftPendingAnnouncements setScreen={setScreen} />;
  }


  const rows = navDetailCards(parent, title, role, amountPerSponsor, currentMembers);

  const handleRowClick = (row: { title: string; action: string; memberId?: string; memberBackendId?: number }) => {
    if (parent === "People" && typeof row.memberBackendId === "number" && onSelectMember) {
      const found = currentMembers.find((member) => member.backendId === row.memberBackendId);
      if (found) onSelectMember(found);
      return;
    }
    if (parent === "Operations" && ["This Month", "Pending Reports", "Completed Activities", "Category-wise Activities"].includes(title)) {
      if (onSelectActivity) {
        const matched = rows.find((r) => r.title === row.title);
        if (matched) {
          const getMetaValue = (prefix: string) => {
            const found = matched.meta.find(m => m.startsWith(prefix));
            return found ? found.replace(prefix, "").trim() : "N/A";
          };
          const date = getMetaValue("Date:");
          const categoryCommittee = getMetaValue("Category / Committee:");
          const peopleServed = getMetaValue("People Served:");
          const reportStatus = getMetaValue("Report Status:");

          onSelectActivity({
            name: matched.title.startsWith("Activity:") ? matched.title.substring(9).trim() : matched.title,
            date,
            categoryCommittee,
            peopleServed,
            reportStatus,
            sourceTitle: title
          });
        }
      }
      return;
    }

    if (parent === "Operations" && ["Club Documents", "Meeting Minutes", "Circulars"].includes(title)) {
      if (onSelectDocument) {
        const matched = rows.find((r) => r.title === row.title);
        if (matched) {
          onSelectDocument({ title: matched.title, meta: matched.meta, sourceTitle: title });
        }
      }
      return;
    }

    if (parent === "People" && title.includes("Member") && title !== "Committee Members") {
      if (!onSelectMember || typeof row.memberBackendId !== "number") return;
      const found = currentMembers.find((member) => member.backendId === row.memberBackendId);
      if (found) onSelectMember(found);
      return;
    }
    if (parent === "People" && title === "Committee List") {
      if (onSelectCommittee) {
        const foundCommittee = committeesList.find((c) => c.name === row.title);
        if (foundCommittee) {
          onSelectCommittee(foundCommittee);
        }
      }
      return;
    }

    if (parent === "People" && title === "Profile Update Pending" && onSelectUpdate) {
      const foundUpdate = pendingMemberUpdates.find((u) => u.member === row.title);
      if (foundUpdate) {
        onSelectUpdate(foundUpdate);
        return;
      }
    }

    if (onSelectMember && parent === "People" && row.memberId) {
      const found = currentMembers.find((member) => member.id === row.memberId);
      if (found) onSelectMember(found);
    }
  };

  const isCelebrationPage = parent === "People" && ["Today", "This Week", "Birthdays", "Anniversaries"].includes(title);

  const handleBackClick = () => {
    if (isCelebrationPage && onBackToCelebrations) {
      onBackToCelebrations();
    } else {
      setScreen(parentScreen(parent));
    }
  };

  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title={navDetailTitle(parent, title)} actionLabel={parent} />
        <button className="secondaryButton compactBackButton" onClick={handleBackClick}>
          ← {isCelebrationPage ? "Celebrations" : parent}
        </button>
        <div className="notice">{navDetailNote(parent, title)}</div>
      </div>
      {isGovernanceDetail && <div className="notice memberHelperText">Leadership assignments shown here are configured in this frontend prototype and are not yet stored in the backend.</div>}
      <div className="draftPendingGrid">
        {rows.length > 0 ? (
          rows.map((row, index) => (
            <article className="draftPendingCard" key={`${parent}-${title}-${row.title}-${index}`}>
              <div>
                <h3>{row.title}</h3>
                {row.meta.map((line) => <span key={line}>{line}</span>)}
              </div>
              <button className="filterButton" onClick={() => handleRowClick(row)}>{row.action}</button>
            </article>
          ))
        ) : (
          <div className="notice memberHelperText">
            {title === "Inactive Members" ? "No inactive members found." : "No records found."}
          </div>
        )}
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

function AnnouncementDetailScreen({
  detail,
  setScreen
}: {
  detail: AnnouncementDetail;
  setScreen: (screen: Screen) => void;
  onSelectAnnouncement?: (announcement: { title: string; meta: string[]; sourceSection: AnnouncementDetail }) => void;
}) {
  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title={`${detail} Announcements`} actionLabel="Not connected" />
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("events-meetings")}>← Operations</button>
        <div className="notice">Announcement records are not connected to a verified backend source. No announcement records are shown.</div>
        <div className="notice memberHelperText">Real announcement records and actions will appear here only after a verified backend workflow is connected.</div>
      </div>
    </section>
  );
}

function parentScreen(parent: string): Screen {
  if (parent === "People") return "members";
  if (parent === "Operations") return "events-meetings";
  if (parent === "Finance") return "finance-module";
  return "dashboard";
}

function navDetailNote(parent: string, title: string) {
  if (parent === "People" && title.includes("Member") && title !== "Committee Members") {
    return "Member information is loaded from the club member records.";
  }
  if (parent === "People" && ["Today", "This Week", "Birthdays", "Anniversaries"].includes(title)) {
    return "Celebration information is loaded from the club member records.";
  }
  if (parent === "Operations" && title.includes("Documents")) {
    return "Dummy data only. Real document upload, approval, and access control will be implemented in backend phase.";
  }
  return "Dummy data only. This screen validates layout and navigation before backend implementation.";
}

function navDetailCards(parent: string, title: string, role: Role = "president", amountPerSponsor?: number, currentMembers: Member[] = []): { title: string; meta: string[]; action: string; memberId?: string; memberBackendId?: number }[] {
  if (parent === "People" && governanceDetailTitles.includes(title)) {
    return verifiedLeadershipCards(currentMembers, title);
  }
  if (parent === "People" && title.includes("Member") && title !== "Committee Members") {
    const statusFilter = title === "Inactive Members" ? "Inactive" : "Active";
    return currentMembers.filter((member) => member.status === statusFilter).map((member) => ({
      title: member.name,
      meta: [`Member ID: ${member.id}`, `Membership: ${member.membershipCategory || member.membershipType || "Not provided"}`, `Status: ${member.status}`, `Occupation: ${member.occupation || "Not provided"}`],
      action: "View",
      memberBackendId: member.backendId
    }));
  }
  if (parent === "People" && title === "Committee List") {
    return committeesList.map((item) => ({
      title: item.name,
      meta: [
        `Lead: ${item.lead}`,
        `Members Count: ${item.count}`,
        `Purpose: ${item.purpose}`
      ],
      action: "View"
    }));
  }

  if (parent === "People" && title === "Profile Update Pending") {
    return pendingMemberUpdates.map((item) => ({
      title: item.member,
      meta: [
        `Requested Change: ${item.change}`,
        `Submitted Date: ${item.submitted}`,
        `Approval Status: ${item.status}`
      ],
      action: "View"
    }));
  }

  if (parent === "People" && ["Today", "This Week", "Birthdays", "Anniversaries"].includes(title)) {
    return peopleCelebrationCards(title, currentMembers);
  }
  if (parent === "People" && ["PST Profiles", "President Profile", "Secretary Profile", "Treasurer Profile"].includes(title)) {
    return pstProfileCards(title, role);
  }
  if (parent === "Operations" && ["This Month", "Pending Reports", "Completed Activities", "Category-wise Activities"].includes(title)) {
    return projectActivityCards(title);
  }
  if (parent === "Finance" && title === "Add Due") {
    const finalAmountPerSponsor = amountPerSponsor ?? 2500;
    const mappedOutstandingDues = outstandingDues.map((due) => {
      if (due.label === "Monthly fellowship share" || due.id === "DUE-004") {
        return { ...due, amount: finalAmountPerSponsor };
      }
      return due;
    });
    return mappedOutstandingDues.map((due) => ({
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
  const rows = navDetailRows(parent, title, currentMembers);
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

function peopleCelebrationCards(title: string, currentMembers: Member[]): { title: string; meta: string[]; action: string; memberId?: string; memberBackendId?: number }[] {
  const today = new Date();
  const month = today.getMonth() + 1;
  const day = today.getDate();
  const entries = currentMembers.flatMap((member) => {
    const rows: { title: string; meta: string[]; action: string; memberId: string; sortDate: string; type: string }[] = [];
    if (member.birthDate) rows.push({ title: `Member: ${member.name}`, meta: ["Type: Birthday", `Date: ${celebrationDayMonth(member.birthDate)}`, "Contact: Hidden", "Status: Pending"], action: "View", memberId: member.id, sortDate: member.birthDate, type: "Birthday" });
    if (member.joinedAt) rows.push({ title: `Member: ${member.name}`, meta: ["Type: Lions Membership Anniversary", `Date: ${celebrationDayMonth(member.joinedAt)}`, "Contact: Hidden", "Status: Pending"], action: "View", memberId: member.id, sortDate: member.joinedAt, type: "Lions Membership Anniversary" });
    if (member.weddingAnniversary) rows.push({ title: `Member: ${member.name}`, meta: ["Type: Wedding Anniversary", `Date: ${celebrationDayMonth(member.weddingAnniversary)}`, "Contact: Hidden", "Status: Pending"], action: "View", memberId: member.id, sortDate: member.weddingAnniversary, type: "Wedding Anniversary" });
    return rows;
  }).filter((item) => {
    const [, itemMonth, itemDay] = item.sortDate.split("-").map(Number);
    if (title === "Today") return itemMonth === month && itemDay === day;
    if (title === "This Week") return itemMonth === month && itemDay >= day && itemDay <= day + 7;
    if (title === "Birthdays") return item.type === "Birthday";
    if (title === "Anniversaries") return item.type !== "Birthday";
    return true;
  });
  return entries.length ? entries : [{ title: "No celebrations for this period", meta: [], action: "View" }];
}
function navDetailRows(parent: string, title: string, currentMembers: Member[]) {
  if (parent === "People" && title.includes("Member")) {
    const statusFilter = title === "Inactive Members" ? "Inactive" : "Active";
    return currentMembers
      .filter((member) => member.status === statusFilter)
      .map((member) => [member.name, member.clubRole, member.status, member.bloodGroup, member.consentContact ? member.phone : "Contact hidden"]);
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

function recurringDateMonth(value: string | null | undefined) {
  if (!value) return null;
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  if (!match) return null;
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const date = new Date(Date.UTC(year, month - 1, day));
  return date.getUTCFullYear() === year && date.getUTCMonth() + 1 === month && date.getUTCDate() === day ? month : null;
}

type ReportDetailCard = { title: string; meta: string[]; member?: Member };

function reportDetailCards(section: ReportsSection, detail: string, amountPerSponsor: number | undefined, currentMembers: Member[], reportMonth?: number): ReportDetailCard[] {
  if (section === "Member Reports") {
    const reportMembers = detail === "Active Member List"
      ? currentMembers.filter((member) => member.status === "Active")
      : detail === "Inactive Member List"
        ? currentMembers.filter((member) => member.status === "Inactive")
        : currentMembers;
    return reportMembers.map((member) => ({
      title: `Member: ${member.name}`,
      meta: [`Member ID: ${member.id}`, `Membership: ${member.membershipCategory || member.membershipType || "Not provided"}`, `Status: ${member.status}`, `Report: ${detail}`],
      member
    }));
  }
  if (section === "Celebration Reports") {
    return currentMembers.flatMap((member) => {
      const rows: { title: string; meta: string[] }[] = [];
      const birthdayMonth = recurringDateMonth(member.birthDate);
      const birthdayMatchesReport = detail !== "Monthly Birthday Report" || birthdayMonth === reportMonth;
      const joinedMonth = recurringDateMonth(member.joinedAt);
      const joinedMatchesReport = detail !== "Monthly Anniversary Report" || joinedMonth === reportMonth;
      const weddingMonth = recurringDateMonth(member.weddingAnniversary);
      const weddingMatchesReport = detail !== "Monthly Anniversary Report" || weddingMonth === reportMonth;
      if (member.birthDate && birthdayMatchesReport && detail !== "Monthly Anniversary Report") rows.push({ title: member.name, meta: ["Celebration Type: Birthday", `Date: ${celebrationDayMonth(member.birthDate)}`, `Member ID: ${member.id}`] });
      if (member.joinedAt && joinedMatchesReport && detail !== "Monthly Birthday Report") rows.push({ title: member.name, meta: ["Celebration Type: Lions Membership Anniversary", `Date: ${celebrationDayMonth(member.joinedAt)}`, `Member ID: ${member.id}`] });
      if (member.weddingAnniversary && weddingMatchesReport && detail !== "Monthly Birthday Report") rows.push({ title: member.name, meta: ["Celebration Type: Wedding Anniversary", `Date: ${celebrationDayMonth(member.weddingAnniversary)}`, `Member ID: ${member.id}`] });
      return rows;
    });
  }  if (section === "Blood Donor Consent Report") {
    return bloodDonorReportCards(detail, currentMembers);
  }
  if (section === "Document Reports") {
    return documentReportCards(detail);
  }
  const finalAmountPerSponsor = amountPerSponsor ?? 2500;
  return [
    { title: "Month: June 2026", meta: ["Sponsor: Lion Bijesh", `Amount: ${formatSponsorShare(finalAmountPerSponsor)}`, "Status: Paid", `Report: ${detail}`] },
    { title: "Month: June 2026", meta: ["Sponsor: Lion Secretary User", `Amount: ${formatSponsorShare(finalAmountPerSponsor)}`, "Status: Pending", `Report: ${detail}`] },
    { title: "Month: June 2026", meta: ["Sponsor: Lion Treasurer User", `Amount: ${formatSponsorShare(finalAmountPerSponsor)}`, "Status: Pending", `Report: ${detail}`] }
  ];
}

function isCurrentPrototypeMonth(date: string) {
  return date === "Today" || date.endsWith("Jun");
}

function bloodDonorReportCards(detail: string, currentMembers: Member[]): { title: string; meta: string[] }[] {
  if (detail === "Blood Group Summary") {
    const groups = [...new Set(currentMembers.map((member) => member.bloodGroup))];
    return groups.map((group) => {
      const groupMembers = currentMembers.filter((member) => member.bloodGroup === group);
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
    const consented = currentMembers.filter((member) => member.donorConsent);
    return [
      {
        title: "Contact Visibility Summary",
        meta: [
          `Contact Visible: ${consented.filter((member) => member.consentContact).length}`,
          `Contact Hidden: ${consented.filter((member) => !member.consentContact).length}`,
          `Consent Pending: ${currentMembers.filter((member) => !member.donorConsent).length}`,
          "Consent Denied: 0"
        ]
      }
    ];
  }

  return currentMembers.filter((member) => member.donorConsent).map((member) => ({
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
  role = "member",
  compact = false,
  showPendingItems = false
}: {
  role?: Role;
  compact?: boolean;
  showPendingItems?: boolean;
}) {
  const { session } = useDevAuth();
  const [status, setStatus] = useState<FinanceSummaryStatus>("loading");
  const [summary, setSummary] = useState<MemberFinanceSummary | null>(null);

  useEffect(() => {
    let cancelled = false;

    async function loadFinanceSummary() {
      setStatus("loading");

      try {
        if (!session) throw new Error("Member session missing");
        const token = session.token;
        const clubId = session.membership.club.id;

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
        <Metric label="Last Payment" value={financeLastPaymentText(summary)} />
        <Metric label="Receipt Status" value={financeReceiptText(summary)} />
      </div>
      {compact && <div className="notice memberHelperText">Current Dues, Last Payment, and Receipt Status come from the existing member finance summary API.</div>}

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
  months,
  selectedMonthKey,
  setSelectedMonthKey
}: {
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  openDashboardDetail: (detail: DashboardDetail) => void;
  totalOutstanding: number;
  months: Record<string, FellowshipMonthRecord>;
  selectedMonthKey: string;
  setSelectedMonthKey: (key: string) => void;
}) {
  const {
    members: currentMembers,
    status: currentMemberStatus,
    activeMemberCount,
    activeMemberStatus
  } = useCurrentMemberData();
  const [clientDate, setClientDate] = useState<Date | null>(null);
  useEffect(() => {
    void Promise.resolve().then(() => setClientDate(new Date()));
  }, []);

  const officerRole = role !== "member";
  const genuineMembers = officerRole ? canonicalFellowshipMembers(currentMembers) : [];
  const memberCountText = activeMemberStatus === "loading"
    ? "Loading member records…"
    : activeMemberStatus === "error"
      ? "Unable to load member records"
      : `${activeMemberCount ?? 0}`;
  const dateText = clientDate
    ? new Intl.DateTimeFormat("en-IN", { day: "numeric", month: "long", year: "numeric" }).format(clientDate)
    : "Loading current date…";
  const currentMonthKey = clientDate
    ? `${clientDate.getFullYear()}-${String(clientDate.getMonth() + 1).padStart(2, "0")}`
    : null;
  const currentMonth = currentMonthKey ? months[currentMonthKey] : null;
  const currentMonthLabel = currentMonthKey ? fellowshipMonthLabel(currentMonthKey) : "Current month";
  const lifecycleLabels: Record<FellowshipMonthLifecycle, string> = {
    unprepared: "Unprepared",
    draft: "Draft — sponsor selection",
    payment_tracking: "Payment tracking open",
    finalised: "Finalised"
  };
  const fellowshipSponsors = currentMonth
    ? (currentMonth.lifecycle === "draft" ? currentMonth.selectedSponsors : currentMonth.confirmedSponsors)
    : [];
  const fellowshipReceived = currentMonth
    ? currentMonth.confirmedSponsors.reduce((sum, memberId) => sum + (currentMonth.payments[memberId] || 0), 0)
    : 0;
  const fellowshipOutstanding = currentMonth
    ? Math.max(0, currentMonth.confirmedSharePerSponsor * currentMonth.confirmedSponsors.length - fellowshipReceived)
    : 0;
  const birthdaysToday = officerRole && clientDate && currentMemberStatus === "ready"
    ? genuineMembers.filter((currentMember) => {
        if (!currentMember.birthDate) return false;
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(currentMember.birthDate);
        return Boolean(match && Number(match[2]) === clientDate.getMonth() + 1 && Number(match[3]) === clientDate.getDate());
      })
    : [];
  const birthdayText = !officerRole
    ? "Available only in authorized officer views"
    : currentMemberStatus === "loading"
      ? "Loading birthday information…"
      : currentMemberStatus === "error"
        ? "Birthday information unavailable"
        : birthdaysToday.length
          ? `${birthdaysToday.length} member birthday${birthdaysToday.length === 1 ? "" : "s"} today`
          : "No member birthdays today";

  function openCurrentFellowship() {
    if (currentMonthKey && currentMonth) setSelectedMonthKey(currentMonthKey);
    setScreen(role === "president" || role === "secretary" ? "fellowship-administration" : "my-fellowship");
  }

  const fellowshipValue = !clientDate
    ? "Loading current month…"
    : currentMonth
      ? `${currentMonthLabel} — ${lifecycleLabels[currentMonth.lifecycle]}`
      : "Current month not prepared";
  const roleName = role === "member" ? "Normal Member" : roleLabels[role];

  return (
    <section className="dashboardStack" style={{ minWidth: 0 }}>
      <div className="dashboardIntro">
        <div>
          <p className="eyebrow">{roleName} Dashboard</p>
          <h2>{dateText}</h2>
          <p>Live club member and Fellowship information is shown where connected. Unconnected modules are marked as unavailable.</p>
        </div>
      </div>

      <section className="compactPanel dashboardPanel">
        <DashboardSectionHeader title="Connected club information" label="Verified sources only" />
        <div className="compactDashboardGrid">
          {officerRole ? (
            <CompactDashboardCard label="Active Members" value={memberCountText} action="Open member directory" onClick={() => setScreen("members")} />
          ) : (
            <TruthStatusCard label="Active Members" value="Club-wide member totals are not shown in the Normal Member dashboard." />
          )}
          <TruthStatusCard label="Birthdays Today" value={birthdayText} />
          <CompactDashboardCard label="Fellowship" value={fellowshipValue} action={currentMonth ? "Open current month" : "View existing months"} onClick={openCurrentFellowship} />
        </div>
        {currentMonth && (
          <div className="metricsGrid" style={{ marginTop: "1rem" }}>
            <Metric label="Fellowship month" value={currentMonthLabel} />
            <Metric label="Lifecycle" value={lifecycleLabels[currentMonth.lifecycle]} />
            <Metric label="Sponsor count" value={`${fellowshipSponsors.length}`} />
            <Metric label="Amount received" value={formatSponsorShare(fellowshipReceived)} />
            <Metric label="Outstanding" value={formatSponsorShare(fellowshipOutstanding)} />
            <Metric label="Selected month retained" value={fellowshipMonthLabel(selectedMonthKey)} />
          </div>
        )}
      </section>

      {role === "member" ? (
        <>
          <FinanceBackend role={role} view="summary" isPersonal />
          <section className="compactPanel dashboardPanel">
            <DashboardSectionHeader title="Member dashboard sources" label="Own authorized records only" />
            <div className="compactDashboardGrid">
              <TruthStatusCard label="Meetings" value="Available in Events & Meetings." />
              <TruthStatusCard label="Announcements" value={<AnnouncementDashboardStatus />} />
              <TruthStatusCard label="Documents" value="Document activity is not connected to the Dashboard yet." />
            </div>
          </section>
        </>
      ) : (
        <section className="compactPanel dashboardPanel">
          <DashboardSectionHeader title="Operational source status" label="Unknown is not reported as zero" />
          <div className="compactDashboardGrid">
            <TruthStatusCard label="Finance & Dues" value="Available in Finance." />
            <TruthStatusCard label="Announcements" value={<AnnouncementDashboardStatus />} />
            <TruthStatusCard label="Events" value="Available in Events & Meetings." />
            <TruthStatusCard label="Meetings" value="Available in Events & Meetings." />
            <TruthStatusCard label="Recent Payments" value="Recent payment activity is unavailable here." />
            <TruthStatusCard label="Documents" value="Document activity is not connected." />
            <TruthStatusCard label="Pending Reports" value="Pending report totals are unavailable." />
            <TruthStatusCard label="Approvals" value="Profile update review is available." />
          </div>
        </section>
      )}

      <section className="compactPanel dashboardPanel">
        <DashboardSectionHeader title="Available navigation" label="No dashboard mutations" />
        <div className="quickActionGrid">
          <button onClick={() => setScreen("members")}>{role === "member" ? "Member Directory" : "Open Members"}</button>
          <button onClick={openCurrentFellowship}>Open Fellowship</button>
          {role !== "member" && <button onClick={() => setScreen("reports-tools-module")}>Open Reports</button>}
          {role === "treasurer" && <button onClick={() => setScreen("record-member-payment")}>Record Member Payment</button>}
          {(role === "president" || role === "secretary") && <button onClick={() => setScreen("announcements")}>Open Announcements</button>}
          <button onClick={() => setScreen("celebrations")}>Open Celebrations</button>
        </div>
      </section>
    </section>
  );
}

function TruthStatusCard({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="compactDashboardCard" style={{ cursor: "default", overflowWrap: "anywhere" }}>
      <span>{label}</span>
      <strong>{value}</strong>
      <small>Source status</small>
    </div>
  );
}
function InternalMvpReadinessPanel({
  role,
  setScreen,
  totalOutstanding
}: {
  role: Role;
  setScreen: (screen: Screen) => void;
  totalOutstanding?: number;
}) {
  const officerRole = role === "president" || role === "secretary" || role === "treasurer";
  const profileDocumentAction = role === "member" ? "Own metadata" : role === "treasurer" ? "View metadata" : "Manage metadata";
  const financeValue = role === "member" ? "Own dues" : totalOutstanding !== undefined ? money(totalOutstanding) : "Existing APIs";

  return (
    <section className="compactPanel dashboardPanel" data-milestone="3DJ-internal-mvp-usability">
      <DashboardSectionHeader title="Internal MVP Quick Actions" label="Internal MVP" />
      <div className="notice memberHelperText">MVP Manual Check: Confirm member list, dues, payments, receipts, and profile document metadata before live use.</div>
      <div className="compactDashboardGrid">
        <CompactDashboardCard
          label="Members"
          value={role === "member" ? "Directory" : "Active Members"}
          action={officerRole ? "Select a member" : "View directory"}
          onClick={() => setScreen("members")}
        />
        <CompactDashboardCard
          label="Finance"
          value={financeValue}
          action={role === "member" ? "Current Dues" : role === "treasurer" ? "Record Payment" : "Review dues"}
          onClick={() => setScreen(role === "member" ? "member-dues-detail" : role === "treasurer" ? "record-member-payment" : "ledger")}
        />
        <CompactDashboardCard
          label="Receipts"
          value="Receipt PDF"
          action="Open receipts"
          onClick={() => setScreen("receipt-list")}
        />
        <CompactDashboardCard
          label="Profile Documents"
          value="Metadata only"
          action={profileDocumentAction}
          onClick={() => setScreen("member-profile")}
        />
      </div>
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
    ...(role === "president" || role === "secretary"
      ? [{ label: "Pending member updates", value: "Available in Profile Update Review", detail: "pending-member-updates" as DashboardDetail, action: "View status" }]
      : []),
    { label: "Upcoming meetings/events", value: "3", detail: "upcoming-events", action: "View details" },
    { label: "Fellowship month status", value: "Draft", detail: "fellowship-status", action: "Continue" },
    { label: role === "secretary" ? "Pending dues summary (view only)" : "Pending dues summary", value: money(totalOutstanding), detail: "pending-dues", action: "Review" },
    { label: "Pending activity/project reports", value: "2", detail: "activity-reports", action: "Update" },
    { label: "Recent documents", value: "Available in Documents", detail: "published-items", action: "Open" },
    { label: "View PST Profiles", value: "Verified assignments", detail: "pst-profiles", action: "Open" },
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
  openDashboardDetail,
  amountPerSponsor
}: {
  detail: DashboardDetail;
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  openDashboardDetail: (detail: DashboardDetail) => void;
  amountPerSponsor?: number;
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
      {detail === "pending-member-updates" && <ProfileUpdatesBackend role={role} mode="review" />}
      {detail === "upcoming-events" && <UpcomingEventsDetail role={role} />}
      {detail === "fellowship-status" && <FellowshipStatusDetail setScreen={setScreen} />}
      {detail === "pending-dues" && <PendingDuesDetail role={role} member={member} setScreen={setScreen} amountPerSponsor={amountPerSponsor} />}
      {detail === "activity-reports" && <ActivityReportsDetail role={role} />}
      {detail === "published-items" && <PublishedItemsDetail role={role} />}
      {detail === "celebration-reminders" && <CelebrationRemindersDetail role={role} />}
      {detail === "club-reminders" && <ClubRemindersDetail />}
      {detail === "active-members" && <ActiveMembersDetail setScreen={setScreen} />}
      {detail === "today-collection" && <CollectionDetailPanel title="Today’s Collection">{todayCollectionRows()}</CollectionDetailPanel>}
      {detail === "month-collection" && <CollectionDetailPanel title="This Month’s Collection">{monthlyCollectionRows()}</CollectionDetailPanel>}
      {detail === "fellowship-pending" && <CollectionDetailPanel title="Fellowship Pending">{fellowshipPendingRows(amountPerSponsor)}</CollectionDetailPanel>}
      {detail === "mode-split" && <CollectionDetailPanel title="Cash / Bank / UPI Split">{modeSplitRows()}</CollectionDetailPanel>}
      {detail === "pst-profiles" && <PstProfilesDetail role={role} />}
      {detail === "member-dues" && <PendingDuesDetail role={role} member={member} setScreen={setScreen} amountPerSponsor={amountPerSponsor} />}
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

function PstProfilesDetail({ role: _role }: { role: Role }) {
  const { members: currentMembers, status } = useCurrentMemberData();
  if (status === "loading") return <div className="notice">Loading verified leadership assignments...</div>;
  if (status === "error") return <div className="notice">Leadership assignments are unavailable.</div>;
  const profiles = verifiedLeadershipCards(currentMembers, "PST Profiles");
  return (
    <section className="stack"><div className="notice memberHelperText">Leadership assignments shown here are configured in this frontend prototype and are not yet stored in the backend.</div><div className="financeCardList">
      {profiles.map((profile) => (
        <article className="financeStackCard" key={profile.title}>
          <h3>{profile.title}</h3>
          {profile.meta.map((line) => <span key={line}>{line}</span>)}
        </article>
      ))}
    </div></section>
  );
}

function PendingMemberUpdatesDetail() {
  return (
    <section className="stack"><div className="notice">The profile update approval workflow is not connected yet.</div><div className="notice memberHelperText">Real pending requests will appear here only after a verified backend approval source is connected.</div></section>
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
    ["Sponsor payment tracking", "Payment records prepared"],
    ["Payment status", "2 pending / 1 paid"]
  ];
  return (
    <div className="compactPanel">
      <div className="fieldGrid">{rows.map(([label, value]) => <Field key={label} label={label} value={value} />)}</div>
      <button onClick={() => setScreen("fellowship-administration")}>Continue fellowship workflow</button>
    </div>
  );
}

function PendingDuesDetail({
  role,
  member,
  setScreen,
  amountPerSponsor
}: {
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  amountPerSponsor?: number;
}) {
  const finalAmountPerSponsor = amountPerSponsor ?? 2500;
  const mappedOutstandingDues = outstandingDues.map((due) => {
    if (due.label === "Monthly fellowship share" || due.id === "DUE-004") {
      return { ...due, amount: finalAmountPerSponsor };
    }
    return due;
  });
  const rows = role === "member" ? mappedOutstandingDues.filter((due) => due.member === member.name) : mappedOutstandingDues;
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
  const rows = documents
    .map((item) => ({ title: item.title, type: "Document", visibility: item.visibility, date: "03 Jun 2026", status: "Published" }))
    .filter((item) => role !== "member" || item.visibility.includes("Members"));
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
  const { members, status } = useCurrentMemberData();
  const value = status === "loading"
    ? "Loading member records…"
    : status === "error"
      ? "Unable to load member records"
      : `${canonicalFellowshipMembers(members).length}`;
  return (
    <div className="compactPanel">
      <div className="metricsGrid">
        <Metric label="Genuine active members" value={value} />
        <Metric label="Board / PST totals" value="Data unavailable" />
        <Metric label="Donor consent totals" value="Not connected here" />
        <Metric label="Profile update totals" value="Not connected here" />
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
        <Field label="Current month" value="Open Fellowship to view connected month state" />
        <Field label="Queue position" value="4" />
        <Field label="Sponsor status" value="Not selected this month" />
        <Field label="Last sponsored" value="Apr 2026" />
        <Field label="Visibility" value="Own fellowship status only" />
      </div>
      <button onClick={() => setScreen("my-fellowship")}>Open My Fellowship</button>
    </div>
  );
}

function MemberCollectionState({ children }: { children: (members: Member[]) => React.ReactNode }) {
  const { members, status, error, retry } = useCurrentMemberData();
  if (status === "loading") return <div className="notice">Loading current members...</div>;
  if (status === "error") return <div className="notice"><strong>Current member data could not be loaded.</strong>{error && <span> {error}</span>} <button className="filterButton" onClick={retry}>Retry</button></div>;
  if (members.length === 0) return <div className="notice">No current members are available.</div>;
  return <>{children(members)}</>;
}

function MembersList({
  setScreen,
  role,
  onSelectMember
}: {
  setScreen: (screen: Screen) => void;
  role: Role;
  onSelectMember?: (member: Member) => void;
}) {
  const { members: availableMembers, status: memberStatus } = useCurrentMemberData();
  const [query, setQuery] = useState("");
  const [statusFilter, setStatusFilter] = useState<"All" | "Active" | "Inactive">("Active");

  return (
    <section className="stack">
      <Toolbar title="Member Directory" actionLabel={role === "member" ? "Consent-approved fields only" : "Role-based management"} />
      <div className="notice memberHelperText">{memberStatus === "ready"
        ? role === "member"
          ? `${availableMembers.length} verified club members. Detailed member profiles are available only to authorized club officers.`
          : `${availableMembers.length} verified club members. Select a member to view the profile fields authorized for this role.`
        : "Verified club member count is loading."}</div>
      <div className="filterBar">
        <input aria-label="Search current members" placeholder="Search name, Member ID, occupation or membership" value={query} onChange={(event) => setQuery(event.target.value)} />
        {(["All", "Active", "Inactive"] as const).map((filter) => <button className="filterButton" key={filter} onClick={() => setStatusFilter(filter)}>{filter}</button>)}
      </div>
      <MemberCollectionState>{(currentMembers) => {
        const normalizedQuery = query.trim().toLowerCase();
        const filtered = currentMembers.filter((member) => {
          const statusMatches = statusFilter === "All" || member.status === statusFilter;
          const searchText = [member.name, member.id, member.occupation, member.membershipType, member.membershipCategory].filter(Boolean).join(" ").toLowerCase();
          return statusMatches && (!normalizedQuery || searchText.includes(normalizedQuery));
        });
        if (!filtered.length) return <div className="notice">No current members match this search.</div>;
        return <div className="tableLike">{filtered.map((member) => role === "member" ? (
          <div key={member.id} className="staticRow">
            <Avatar name={member.name} />
            <span><strong>{member.name}</strong><small>Member ID: {member.id}</small></span>
            <span>{member.occupation || "Not provided"}</span>
            <span>{member.membershipCategory || member.membershipType || "Not provided"}</span>
            <span className="tag">{member.status}</span>
          </div>
        ) : (
          <button key={member.id} className="rowButton" onClick={() => onSelectMember ? onSelectMember(member) : setScreen("member-profile")}>
            <Avatar name={member.name} />
            <span><strong>{member.name}</strong><small>Member ID: {member.id}</small></span>
            <span>{member.occupation || "Not provided"}</span>
            <span>{member.membershipCategory || member.membershipType || "Not provided"}</span>
            <span className="tag">{member.status}</span>
          </button>
        ))}</div>;
      }}</MemberCollectionState>
    </section>
  );
}
function Committees() {
  const [selectedMemberId, setSelectedMemberId] = useState("");
  return (
    <section className="stack">
      <Toolbar title="Board & Committees" actionLabel="Year-wise structure" />
      <div className="notice">Existing prototype office records remain legacy/unlinked. No imported member is assigned automatically.</div>
      <MemberCollectionState>{(currentMembers) => (
        <label className="moduleSelect">Member selector
          <select value={selectedMemberId} onChange={(event) => setSelectedMemberId(event.target.value)}>
            <option value="">Select a current member</option>
            {currentMembers.map((member) => <option key={member.id} value={member.id}>{member.name} — {member.id}</option>)}
          </select>
        </label>
      )}</MemberCollectionState>
      <div className="actionGrid">
        <Action title="Board 2026" body="Legacy office records preserved; member links unassigned." onClick={() => undefined} />
        <Action title="Education Committee" body="Legacy/unlinked lead. 4 roster positions." onClick={() => undefined} />
        <Action title="Health Committee" body="Legacy/unlinked lead. 5 roster positions." onClick={() => undefined} />
        <Action title="Service Committee" body="Legacy/unlinked lead. 6 roster positions." onClick={() => undefined} />
      </div>
    </section>
  );
}
function MemberProfile({ member, role }: { member: Member; role: Role }) {
  const isPersonal = useContext(PersonalProfileContext);
  const { token: sharedToken, clubId: sharedClubId } = useCurrentMemberData();
  const { session } = useDevAuth();
  const linkedMemberCode = session?.membership?.member?.member_code;
  const isOwnProfile = Boolean(
    linkedMemberCode &&
    member.id &&
    linkedMemberCode === member.id
  );
  const canManageProfileDocumentMetadata = role === "president" || role === "secretary";
  const [documentStatus, setDocumentStatus] = useState<ProfileDocumentStatus>("initializing");
  const [documentMessage, setDocumentMessage] = useState("Loading profile document metadata...");
  const [documentToken, setDocumentToken] = useState<string | null>(null);
  const [documentClubId, setDocumentClubId] = useState<number | null>(null);
  const [documentMemberId, setDocumentMemberId] = useState<number | null>(null);
  const [profileDocuments, setProfileDocuments] = useState<ProfileDocumentMetadataRecord[]>([]);
  const [editingDocumentId, setEditingDocumentId] = useState<number | null>(null);
  const [showDocumentForm, setShowDocumentForm] = useState(false);
  const [documentForm, setDocumentForm] = useState<ProfileDocumentFormState>({
    document_type: "",
    status: "active",
    external_reference: "",
    remarks: ""
  });
  const isDocumentBusy = documentStatus === "initializing" || documentStatus === "loading" || documentStatus === "saving";

  const canEditMember = role === "president" || role === "secretary";
  const [isEditingMember, setIsEditingMember] = useState(false);
  const [memberForm, setMemberForm] = useState({
    name: "",
    phone: "",
    email: "",
    bloodGroup: "",
    status: ""
  });
  const [backendMember, setBackendMember] = useState<BackendMemberRecord | null>(null);
  const [selectedProfileStatus, setSelectedProfileStatus] = useState<"loading" | "ready" | "error" | "not_linked">(member.backendId ? "loading" : "not_linked");
  const [editStatus, setEditStatus] = useState<"idle" | "saving" | "success" | "error">("idle");
  const [editMessage, setEditMessage] = useState("");

  function profileDocumentApiMessage(payload: ApiErrorPayload | null, fallback: string) {
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

  async function fetchProfileDocuments(currentToken: string, currentClubId: number, currentMemberId: number) {
    const response = await fetch(API_BASE_URL + "/clubs/" + currentClubId + "/members/" + currentMemberId + "/profile-documents", {
      headers: {
        Accept: "application/json",
        Authorization: "Bearer " + currentToken
      }
    });

    const payload = await response.json().catch(() => null) as { profile_documents?: ProfileDocumentMetadataRecord[] } & ApiErrorPayload | null;

    if (!response.ok) {
      throw new ProfileDocumentRequestError(
        profileDocumentApiMessage(payload, "Profile document metadata is unavailable."),
        response.status
      );
    }

    return payload?.profile_documents ?? [];
  }

  async function refreshProfileDocuments(currentToken = documentToken, currentClubId = documentClubId, currentMemberId = documentMemberId) {
    if (!currentToken || !currentClubId || !currentMemberId) {
      setDocumentStatus("error");
      setDocumentMessage("Profile document metadata context is unavailable.");
      return;
    }

    setDocumentStatus("loading");
    setDocumentMessage("Loading profile document metadata...");

    try {
      const documents = await fetchProfileDocuments(currentToken, currentClubId, currentMemberId);
      setProfileDocuments(documents);
      setDocumentStatus(documents.length ? "ready" : "empty");
      setDocumentMessage(documents.length ? "Profile document metadata loaded." : "No profile document records added yet.");
    } catch (error) {
      setProfileDocuments([]);
      setDocumentStatus("error");
      setDocumentMessage(error instanceof Error ? error.message : "Profile document metadata is unavailable.");
    }
  }

  useEffect(() => {
    let cancelled = false;

    async function loadSelectedProfile() {
      setDocumentStatus("initializing");
      setDocumentMessage("Loading profile document metadata...");
      setProfileDocuments([]);
      setEditingDocumentId(null);
      setShowDocumentForm(false);
      setEditStatus("idle");
      setEditMessage("");
      setIsEditingMember(false);
      setSelectedProfileStatus(member.backendId ? "loading" : "not_linked");

      if (!member.backendId || !sharedToken || !sharedClubId) {
        setBackendMember(null);
        setDocumentMemberId(null);
        setDocumentStatus("not_linked");
        setDocumentMessage("This role-test identity is not linked to a genuine member record.");
        return;
      }

      try {
        const headers = { Accept: "application/json", Authorization: "Bearer " + sharedToken };
        const profileUrl = isPersonal
          ? API_BASE_URL + "/clubs/" + sharedClubId + "/members/me"
          : API_BASE_URL + "/clubs/" + sharedClubId + "/members/" + member.backendId;
        const profileResponse = await fetch(profileUrl, { headers });
        if (!profileResponse.ok) throw new Error("The selected member profile is unavailable for this role.");
        const profilePayload = await profileResponse.json();
        const selectedBackendMember = profilePayload.member as BackendMemberRecord;
        if (selectedBackendMember.member_code !== member.id) throw new Error("The selected member identity did not match the requested Member ID.");
        const resolvedMemberId = isPersonal ? selectedBackendMember.id : member.backendId;

        if (!cancelled) {
          setDocumentToken(sharedToken);
          setDocumentClubId(sharedClubId);
          setDocumentMemberId(resolvedMemberId);
          setBackendMember(selectedBackendMember);
          setSelectedProfileStatus("ready");
        }

        try {
          const documents = await fetchProfileDocuments(sharedToken, sharedClubId, resolvedMemberId);
          if (!cancelled) {
            setProfileDocuments(documents);
            setDocumentStatus(documents.length ? "ready" : "empty");
            setDocumentMessage(documents.length ? "Profile document metadata loaded." : "No profile document records added yet.");
          }
        } catch (error) {
          if (!cancelled) {
            setProfileDocuments([]);
            if (error instanceof ProfileDocumentRequestError && error.status === 403) {
              setDocumentStatus("unavailable");
              setDocumentMessage("Profile document metadata is unavailable for this role.");
            } else {
              setDocumentStatus("error");
              setDocumentMessage(error instanceof Error ? error.message : "Profile document metadata is unavailable.");
            }
          }
        }
      } catch (error) {
        if (!cancelled) {
          setBackendMember(null);
          setSelectedProfileStatus("error");
          setProfileDocuments([]);
          setDocumentStatus("error");
          setDocumentMessage(error instanceof Error ? error.message : "The selected member profile is unavailable.");
        }
      }
    }

    loadSelectedProfile();
    return () => { cancelled = true; };
  }, [isPersonal, member.backendId, member.id, sharedClubId, sharedToken]);

  function startMemberEdit() {
    if (!backendMember) return;
    setMemberForm({
      name: backendMember.full_name || member.name || "",
      phone: backendMember.phone || member.phone || "",
      email: backendMember.email || "",
      bloodGroup: backendMember.blood_group || member.bloodGroup || "",
      status: backendMember.status || member.status || ""
    });
    setIsEditingMember(true);
    setEditStatus("idle");
    setEditMessage("");
  }

  async function submitMemberUpdate(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!canEditMember || !documentToken || !documentClubId || !documentMemberId) {
      setEditStatus("error");
      setEditMessage("Backend authorization is required before member updates can be saved.");
      return;
    }

    setEditStatus("saving");
    setEditMessage("Saving member updates...");

    try {
      const memberUpdateUrl = `${API_BASE_URL}/clubs/${documentClubId}/members/${documentMemberId}`;
      const response = await fetch(
        memberUpdateUrl,
        {
          method: "PATCH",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            Authorization: "Bearer " + documentToken
          },
          body: JSON.stringify({
            full_name: memberForm.name,
            phone: memberForm.phone,
            email: memberForm.email,
            blood_group: memberForm.bloodGroup,
            status: memberForm.status
          })
        }
      );

      const payload = await response.json().catch(() => null);

      if (!response.ok) {
        throw new Error(profileDocumentApiMessage(payload, "Member updates could not be saved."));
      }

      const updatedBackendMember = payload.member;
      setBackendMember(updatedBackendMember);


      setIsEditingMember(false);
      setEditStatus("success");
      setEditMessage("Member updates saved successfully.");
    } catch (error) {
      setEditStatus("error");
      setEditMessage(error instanceof Error ? error.message : "Member updates could not be saved.");
    }
  }

  function resetProfileDocumentForm() {
    setEditingDocumentId(null);
    setShowDocumentForm(false);
    setDocumentForm({
      document_type: "",
      status: "active",
      external_reference: "",
      remarks: ""
    });
  }

  function startProfileDocumentCreate() {
    setEditingDocumentId(null);
    setDocumentForm({
      document_type: "",
      status: "active",
      external_reference: "",
      remarks: ""
    });
    setShowDocumentForm(true);
    setDocumentMessage("Add document metadata for this member.");
  }

  function startProfileDocumentEdit(document: ProfileDocumentMetadataRecord) {
    setEditingDocumentId(document.id);
    setDocumentForm({
      document_type: document.document_type ?? "",
      status: document.status ?? "",
      external_reference: document.external_reference ?? "",
      remarks: document.remarks ?? ""
    });
    setShowDocumentForm(true);
    setDocumentMessage("Edit document metadata for this member.");
  }

  async function submitProfileDocumentMetadata(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!canManageProfileDocumentMetadata || !documentToken || !documentClubId || !documentMemberId) {
      setDocumentStatus("error");
      setDocumentMessage("Backend authorization is required before profile document metadata can be saved.");
      return;
    }

    setDocumentStatus("saving");
    setDocumentMessage("Saving profile document metadata...");

    try {
      const response = await fetch(
        API_BASE_URL + "/clubs/" + documentClubId + "/members/" + documentMemberId + "/profile-documents" + (editingDocumentId ? "/" + editingDocumentId : ""),
        {
          method: editingDocumentId ? "PATCH" : "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            Authorization: "Bearer " + documentToken
          },
          body: JSON.stringify({
            document_type: documentForm.document_type,
            status: documentForm.status,
            external_reference: documentForm.external_reference,
            remarks: documentForm.remarks
          })
        }
      );

      const payload = await response.json().catch(() => null) as ApiErrorPayload | null;

      if (!response.ok) {
        throw new Error(profileDocumentApiMessage(payload, "Profile document metadata could not be saved."));
      }

      resetProfileDocumentForm();
      await refreshProfileDocuments(documentToken, documentClubId, documentMemberId);
      setDocumentMessage("Profile document metadata saved and refreshed.");
    } catch (error) {
      setDocumentStatus("error");
      setDocumentMessage(error instanceof Error ? error.message : "Profile document metadata could not be saved.");
    }
  }

  function renderProfileDocumentsSection() {
    return (
      <section className="compactPanel profileDocumentsSection" data-milestone="3DF-profile-documents-frontend-narrow-implementation">
        <Toolbar title="Profile Documents" actionLabel={canManageProfileDocumentMetadata ? "Metadata controls" : "Metadata view"} />
        <div className="notice memberHelperText">Document metadata only. File upload and document storage are not enabled in this version.</div>
        {documentStatus !== "empty" && <div className="notice memberHelperText">{documentMessage}</div>}

        {canManageProfileDocumentMetadata && documentMemberId && (
          <div className="buttonRow">
            <button type="button" className="secondaryButton" disabled={isDocumentBusy} onClick={startProfileDocumentCreate}>Add Document Metadata</button>
            <button type="button" className="secondaryButton" disabled={isDocumentBusy} onClick={() => void refreshProfileDocuments()}>Refresh Metadata</button>
          </div>
        )}

        {showDocumentForm && canManageProfileDocumentMetadata && (
          <form className="formPanel" onSubmit={submitProfileDocumentMetadata}>
            <div className="fieldGrid">
              <label>
                Document type
                <input
                  value={documentForm.document_type}
                  disabled={isDocumentBusy}
                  maxLength={255}
                  onChange={(event) => setDocumentForm({ ...documentForm, document_type: event.target.value })}
                  required
                />
              </label>
              <label>
                Status
                <input
                  value={documentForm.status}
                  disabled={isDocumentBusy}
                  maxLength={255}
                  onChange={(event) => setDocumentForm({ ...documentForm, status: event.target.value })}
                  required
                />
              </label>
              <label>
                External reference
                <input
                  value={documentForm.external_reference}
                  disabled={isDocumentBusy}
                  maxLength={255}
                  onChange={(event) => setDocumentForm({ ...documentForm, external_reference: event.target.value })}
                  placeholder="Optional reference"
                />
              </label>
              <label>
                Remarks
                <textarea
                  value={documentForm.remarks}
                  disabled={isDocumentBusy}
                  maxLength={5000}
                  onChange={(event) => setDocumentForm({ ...documentForm, remarks: event.target.value })}
                  placeholder="Optional remarks"
                />
              </label>
            </div>
            <div className="buttonRow">
              <button type="submit" disabled={isDocumentBusy}>{documentStatus === "saving" ? "Saving..." : "Save Metadata"}</button>
              <button type="button" className="secondaryButton" disabled={isDocumentBusy} onClick={resetProfileDocumentForm}>Cancel</button>
            </div>
          </form>
        )}

        {(documentStatus === "initializing" || documentStatus === "loading") && <div className="notice memberHelperText">Loading profile document metadata...</div>}
        {documentStatus === "empty" && <div className="notice memberHelperText">No profile document records added yet.</div>}
        {documentStatus === "error" && <div className="notice memberHelperText">Profile document metadata could not be loaded or saved safely.</div>}

        {profileDocuments.length > 0 && (
          <div className="financeCardList">
            {profileDocuments.map((document) => (
              <article className="financeStackCard" key={document.id}>
                <h3>{document.document_type || "Document metadata"}</h3>
                <span>Status: {document.status || "Not set"}</span>
                <span>External reference: {document.external_reference || "Not set"}</span>
                <span>Remarks: {document.remarks || "Not set"}</span>
                <span>Created: {document.created_at || "Not returned"}</span>
                <span>Updated: {document.updated_at || "Not returned"}</span>
                {canManageProfileDocumentMetadata && (
                  <button type="button" className="filterButton" disabled={isDocumentBusy} onClick={() => startProfileDocumentEdit(document)}>Edit Metadata</button>
                )}
              </article>
            ))}
          </div>
        )}
      </section>
    );
  }

  if (member.backendId && selectedProfileStatus !== "ready") {
    return (
      <section className="stack">
        <div className="notice memberHelperText">
          {selectedProfileStatus === "error" ? documentMessage : "Loading the selected member profile..."}
        </div>
      </section>
    );
  }

  return (
    <section className="stack memberProfileScreen">
      <div className="detailGrid">
        <div className="profilePanel">
          <Avatar name={member.name} large />
          <h2>{member.dashboardName}</h2>
          <p>{member.id} - {member.clubRole}</p>
          <span className="tag">{member.status}</span>
        </div>
        {isEditingMember ? (
          <form className="fieldsPanel formPanel" onSubmit={submitMemberUpdate} style={{ gridColumn: "span 2" }}>
            <div className="fieldGrid" style={{ gridTemplateColumns: "1fr 1fr" }}>
              <label>
                Full Name
                <input
                  value={memberForm.name}
                  disabled={editStatus === "saving"}
                  maxLength={255}
                  onChange={(event) => setMemberForm({ ...memberForm, name: event.target.value })}
                  required
                />
              </label>
              <label>
                Phone
                <input
                  value={memberForm.phone}
                  disabled={editStatus === "saving"}
                  maxLength={255}
                  onChange={(event) => setMemberForm({ ...memberForm, phone: event.target.value })}
                  required
                />
              </label>
              <label>
                Email
                <input
                  type="email"
                  value={memberForm.email}
                  disabled={editStatus === "saving"}
                  maxLength={255}
                  onChange={(event) => setMemberForm({ ...memberForm, email: event.target.value })}
                />
              </label>
              <label>
                Blood Group
                <select
                  value={memberForm.bloodGroup}
                  disabled={editStatus === "saving"}
                  onChange={(event) => setMemberForm({ ...memberForm, bloodGroup: event.target.value })}
                  required
                >
                  {["O+", "A+", "B+", "AB+", "O-", "A-", "B-", "AB-"].map((group) => (
                    <option key={group} value={group}>{group}</option>
                  ))}
                </select>
              </label>
              <label>
                Status
                <select
                  value={memberForm.status}
                  disabled={editStatus === "saving"}
                  onChange={(event) => setMemberForm({ ...memberForm, status: event.target.value })}
                  required
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </label>
            </div>
            {editMessage && <div className="notice memberHelperText" style={{ marginTop: "1rem" }}>{editMessage}</div>}
            <div className="buttonRow" style={{ marginTop: "1rem" }}>
              <button type="submit" disabled={editStatus === "saving"}>
                {editStatus === "saving" ? "Saving..." : "Save Profile"}
              </button>
              <button type="button" className="secondaryButton" disabled={editStatus === "saving"} onClick={() => setIsEditingMember(false)}>
                Cancel
              </button>
            </div>
          </form>
        ) : (
          <div className="fieldsPanel">
            <Field label="Salutation" value={backendMember?.salutation || "Not provided"} />
            <Field label="Full name" value={backendMember?.full_name || member.name} />
            <Field label="Member ID" value={backendMember?.member_code || member.id} />
            <Field label="Membership status" value={backendMember?.status || member.status} />
            <Field label="Membership type" value={backendMember?.membership_type || member.membershipType || "Not provided"} />
            <Field label="Membership category" value={backendMember?.membership_category || member.membershipCategory || "Not provided"} />
            <Field label="Membership program" value={backendMember?.membership_program || member.membershipProgram || "Not provided"} />
            <Field label="Lion Join Date" value={backendMember?.joined_at || member.joinedAt || "Not provided"} />
            <Field label="Occupation" value={backendMember?.occupation || member.occupation || "Not provided"} />
            <Field label="Spouse name" value={backendMember?.spouse_name || "Not provided"} />
            <Field label="Wedding Anniversary" value={backendMember?.wedding_anniversary || "Not provided"} />
            <Field label="Gender" value={backendMember?.gender || "Not provided"} />
            <Field label="Phone" value={backendMember?.phone || "Not provided"} />
            <Field label="Email" value={backendMember?.email || "Not provided"} />
            <Field label="Address" value={[backendMember?.address_line_1, backendMember?.address_line_2, backendMember?.city_locality, backendMember?.district, backendMember?.state, backendMember?.postal_code, backendMember?.country].filter(Boolean).join(", ") || "Not provided"} />
            <Field label="Blood group" value={backendMember?.blood_group || member.bloodGroup || "Not provided"} />
            <Field label="Donor consent" value={backendMember?.blood_donor_consent ? "Enabled" : "Disabled"} />
            <Field label="Financial visibility" value={role === "member" ? "Own dues only" : "Role-based club view"} />
            {editMessage && <div className="notice memberHelperText" style={{ marginTop: "0.5rem" }}>{editMessage}</div>}
            {canEditMember && backendMember && (
              <div className="buttonRow" style={{ gridColumn: "span 2", marginTop: "1rem" }}>
                <button type="button" className="secondaryButton" onClick={startMemberEdit}>Edit Member Details</button>
              </div>
            )}
          </div>
        )}
      </div>
      <div className="notice memberHelperText">Member Details: review the member profile here, then use the finance summary and Profile Documents metadata section below. Backend authorization remains the source of truth.</div>
      {isOwnProfile && <ProfileUpdatesBackend role={role} mode="own" />}
      {member.backendId && <FinanceBackend role={role} view="summary" memberId={member.backendId} isPersonal={role === "member"} />}
      {renderProfileDocumentsSection()}
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
  tab,
  setTab,
  months,
  setMonths,
  selectedMonthKey,
  setSelectedMonthKey
}: {
  tab: FellowshipTab;
  setTab: (tab: FellowshipTab) => void;
  months: Record<string, FellowshipMonthRecord>;
  setMonths: React.Dispatch<React.SetStateAction<Record<string, FellowshipMonthRecord>>>;
  selectedMonthKey: string;
  setSelectedMonthKey: (key: string) => void;
}) {
  const { members: currentMembers, status, error, retry } = useCurrentMemberData();
  const [cancelDraftKey, setCancelDraftKey] = useState<string | null>(null);
  const [cancelPayment, setCancelPayment] = useState<{ memberId: string; paymentId: string } | null>(null);
  const [editingPayment, setEditingPayment] = useState<{ memberId: string; paymentId: string; amount: string } | null>(null);
  const [confirmFinalise, setConfirmFinalise] = useState(false);
  const monthKeys = Object.keys(months).sort();
  const latestMonthKey = monthKeys[monthKeys.length - 1];
  const month = months[selectedMonthKey] || months[monthKeys[0]];
  const monthLabel = fellowshipMonthLabel(month.key);
  const canonicalSequence = canonicalFellowshipMembers(currentMembers);
  const confirmedMonths = monthKeys.filter((key) => ["payment_tracking", "finalised"].includes(months[key].lifecycle));
  const completedByMember = new Map<string, string>();
  confirmedMonths.forEach((key) => months[key].confirmedSponsors.forEach((memberId) => completedByMember.set(memberId, key)));
  const latestConfirmed = confirmedMonths.length ? months[confirmedMonths[confirmedMonths.length - 1]] : null;
  const lastConfirmedId = latestConfirmed?.confirmedSponsors[latestConfirmed.confirmedSponsors.length - 1];
  const lastConfirmedIndex = canonicalSequence.findIndex((item) => fellowshipMemberKey(item) === lastConfirmedId);
  const nextSuggestedId = canonicalSequence.length
    ? fellowshipMemberKey(canonicalSequence[(lastConfirmedIndex + 1 + canonicalSequence.length) % canonicalSequence.length])
    : "";
  const parsedExpense = Number(month.totalExpenseInput);
  const validExpense = /^\d+(\.\d*)?$/.test(month.totalExpenseInput.trim()) && Number.isFinite(parsedExpense) && parsedExpense > 0;
  const draftShare = validExpense && month.selectedSponsors.length ? parsedExpense / month.selectedSponsors.length : 0;
  const activeSponsors = month.lifecycle === "draft" ? month.selectedSponsors : month.confirmedSponsors;
  const share = month.lifecycle === "draft" ? draftShare : month.confirmedSharePerSponsor;
  const totalPaid = month.confirmedSponsors.reduce((sum, memberId) => sum + (month.payments[memberId] || 0), 0);
  const hasTransactions = Object.values(month.payments).some((amount) => amount > 0)
    || Object.values(month.paymentHistories).some((history) => history.length > 0);
  const canCancelDraft = month.lifecycle === "draft" && month.confirmedSponsors.length === 0 && !hasTransactions;
  const cancellationExplanation = month.lifecycle === "finalised"
    ? "Finalised Fellowship accounts cannot be changed."
    : month.lifecycle === "payment_tracking" && hasTransactions
      ? "This month cannot be cancelled after payment recording has started."
      : month.lifecycle === "payment_tracking"
        ? "This month cannot be cancelled because sponsor confirmation is complete."
        : month.lifecycle === "unprepared"
          ? "This month has not been prepared, so there is no draft to cancel."
          : "Draft cancellation is unavailable while confirmed or payment data exists.";

  function updateMonth(key: string, updater: (current: FellowshipMonthRecord) => FellowshipMonthRecord) {
    setMonths((current) => ({ ...current, [key]: updater(current[key]) }));
  }

  function prepareSelectedMonth() {
    if (month.key !== latestMonthKey || month.lifecycle !== "unprepared" || monthKeys.some((key) => months[key].lifecycle === "draft")) return;
    updateMonth(month.key, (current) => ({ ...emptyFellowshipMonth(current.key, "draft"), notice: `Success: ${monthLabel} sponsor rotation is prepared.` }));
  }

  function cancelSelectedDraft() {
    if (!canCancelDraft || cancelDraftKey !== month.key) return;
    updateMonth(month.key, (current) => ({ ...emptyFellowshipMonth(current.key), notice: `Success: ${monthLabel} draft was cancelled. The same month is available to prepare again.` }));
    setCancelDraftKey(null);
    setTab("Overview");
  }

  function confirmSponsors() {
    if (month.lifecycle !== "draft" || !validExpense || month.selectedSponsors.length === 0) return;
    const ordered = sortFellowshipMemberKeys(month.selectedSponsors, currentMembers);
    const nextKey = nextFellowshipMonthKey(month.key);
    setMonths((current) => {
      const confirmed = {
        ...current[month.key],
        lifecycle: "payment_tracking" as FellowshipMonthLifecycle,
        selectedSponsors: ordered,
        confirmedSponsors: ordered,
        confirmedSharePerSponsor: parsedExpense / ordered.length,
        payments: {},
        paymentHistories: {},
        paymentInputs: {},
        notice: `Success: ${monthLabel} sponsors are confirmed. Payment tracking is open.`
      };
      return current[nextKey] ? { ...current, [month.key]: confirmed } : { ...current, [month.key]: confirmed, [nextKey]: emptyFellowshipMonth(nextKey) };
    });
    setTab("Payments");
  }

  function setSponsorSelected(memberId: string) {
    if (month.lifecycle !== "draft") return;
    updateMonth(month.key, (current) => {
      const selected = current.selectedSponsors.includes(memberId)
        ? current.selectedSponsors.filter((id) => id !== memberId)
        : sortFellowshipMemberKeys([...current.selectedSponsors, memberId], currentMembers);
      const queueStatuses = { ...current.queueStatuses };
      if (selected.includes(memberId)) queueStatuses[memberId] = "Selected";
      else delete queueStatuses[memberId];
      return { ...current, selectedSponsors: selected, queueStatuses };
    });
  }

  function deferSponsor(memberId: string) {
    if (month.lifecycle !== "draft") return;
    updateMonth(month.key, (current) => ({
      ...current,
      selectedSponsors: current.selectedSponsors.filter((id) => id !== memberId),
      queueStatuses: { ...current.queueStatuses, [memberId]: "Deferred for this month" }
    }));
  }

  function recordPayment(memberId: string, requestedAmount?: number) {
    if (month.lifecycle !== "payment_tracking") return;
    const paid = month.payments[memberId] || 0;
    const balance = roundFellowshipAmount(month.confirmedSharePerSponsor - paid);
    const amount = requestedAmount ?? Number(month.paymentInputs[memberId]);
    if (!Number.isFinite(amount) || amount <= 0 || amount > balance + 0.001) return;
    const entry = { id: `${month.key}-${memberId}-${Date.now()}`, date: new Date().toLocaleDateString("en-IN"), amount: roundFellowshipAmount(amount) };
    updateMonth(month.key, (current) => ({
      ...current,
      payments: { ...current.payments, [memberId]: roundFellowshipAmount((current.payments[memberId] || 0) + entry.amount) },
      paymentHistories: { ...current.paymentHistories, [memberId]: [...(current.paymentHistories[memberId] || []), entry] },
      paymentInputs: { ...current.paymentInputs, [memberId]: "" },
      notice: `Success: ${formatSponsorShare(entry.amount)} recorded for ${displayMemberName(memberId, currentMembers)} in ${monthLabel}.`
    }));
  }

  function replacePayment(memberId: string, paymentId: string, amount: number) {
    if (month.lifecycle !== "payment_tracking" || !Number.isFinite(amount) || amount <= 0) return;
    const history = month.paymentHistories[memberId] || [];
    const old = history.find((entry) => entry.id === paymentId);
    if (!old) return;
    const otherTotal = history.filter((entry) => entry.id !== paymentId).reduce((sum, entry) => sum + entry.amount, 0);
    if (otherTotal + amount > month.confirmedSharePerSponsor + 0.001) return;
    updateMonth(month.key, (current) => ({
      ...current,
      payments: { ...current.payments, [memberId]: roundFellowshipAmount(otherTotal + amount) },
      paymentHistories: { ...current.paymentHistories, [memberId]: history.map((entry) => entry.id === paymentId ? { ...entry, amount: roundFellowshipAmount(amount) } : entry) },
      notice: `Success: the selected ${monthLabel} payment was updated.`
    }));
    setEditingPayment(null);
  }

  function removePayment(memberId: string, paymentId: string) {
    if (month.lifecycle !== "payment_tracking" || cancelPayment?.paymentId !== paymentId) return;
    const history = (month.paymentHistories[memberId] || []).filter((entry) => entry.id !== paymentId);
    updateMonth(month.key, (current) => ({
      ...current,
      payments: { ...current.payments, [memberId]: roundFellowshipAmount(history.reduce((sum, entry) => sum + entry.amount, 0)) },
      paymentHistories: { ...current.paymentHistories, [memberId]: history },
      notice: `Success: only the selected ${monthLabel} payment transaction was cancelled.`
    }));
    setCancelPayment(null);
  }

  function finaliseMonth() {
    if (month.lifecycle !== "payment_tracking" || !confirmFinalise) return;
    const outstanding = month.confirmedSponsors.reduce((sum, memberId) => sum + Math.max(0, month.confirmedSharePerSponsor - (month.payments[memberId] || 0)), 0);
    updateMonth(month.key, (current) => ({
      ...current,
      lifecycle: "finalised",
      transferredOutstanding: roundFellowshipAmount(outstanding),
      notice: `Success: ${monthLabel} Fellowship accounts are finalised and read-only.`
    }));
    setConfirmFinalise(false);
  }

  if (status === "loading") return <div className="notice">Loading current members...</div>;
  if (status === "error") return <div className="notice"><strong>Current member data could not be loaded.</strong> {error} <button className="filterButton" onClick={retry}>Retry</button></div>;
  if (!month) return <div className="notice">Fellowship month state is unavailable.</div>;
  const lifecycleLabel: Record<FellowshipMonthLifecycle, string> = {
    unprepared: "Unprepared",
    draft: "Draft — Sponsor Selection",
    payment_tracking: "Payment Tracking Open",
    finalised: "Finalised — Read-only"
  };

  return (
    <section className="stack" style={{ minWidth: 0 }}>
      <div className="compactPanel">
        <div className="toolbar" style={{ gap: "12px", flexWrap: "wrap" }}><div><p className="eyebrow">FELLOWSHIP ADMINISTRATION</p><h2>Club Fellowship Management</h2></div><span className="statusPill">Authorized club workflow</span></div>
      </div>
      <div className="compactPanel">
        <div className="toolbar" style={{ gap: "12px", flexWrap: "wrap" }}>
          <div><p className="eyebrow">Fellowship Sponsorship — {monthLabel}</p><h2>Month lifecycle</h2></div>
          <span className="statusPill">{lifecycleLabel[month.lifecycle]}</span>
        </div>
        <div className="metricsGrid">
          <Metric label="Selected month" value={monthLabel} />
          <Metric label="Month key" value={month.key} />
          <Metric label="Lifecycle" value={lifecycleLabel[month.lifecycle]} />
          <Metric label="Next month available to prepare" value={fellowshipMonthLabel(nextFellowshipMonthKey(latestMonthKey))} />
        </div>
        <div style={{ display: "flex", gap: "12px", flexWrap: "wrap", marginTop: "12px" }}>
          <label className="moduleSelect" style={{ flex: "1 1 220px", margin: 0 }}>Fellowship month
            <select value={month.key} onChange={(event) => { setSelectedMonthKey(event.target.value); setConfirmFinalise(false); setCancelDraftKey(null); }}>
              {monthKeys.map((key) => <option key={key} value={key}>{fellowshipMonthLabel(key)} — {lifecycleLabel[months[key].lifecycle]}</option>)}
            </select>
          </label>
          <label className="moduleSelect" style={{ flex: "1 1 220px", margin: 0 }}>Fellowship section
            <select value={tab} onChange={(event) => setTab(event.target.value as FellowshipTab)}>
              <option value="Overview">Overview</option><option value="Queue">Sponsor Rotation</option><option value="Selected Sponsors">Selected Sponsors / Review</option><option value="Payments">Payments & History</option><option value="History">Month Summary</option>
            </select>
          </label>
        </div>
      </div>

      {month.notice && <div className="notice" style={{ backgroundColor: "#e6f4ea", color: "#137333" }}>{month.notice}</div>}

      {canCancelDraft ? (
        <div className="compactPanel">
          {cancelDraftKey === month.key ? <div className="stack"><strong>Cancel {monthLabel} Draft?</strong><span>This removes only {monthLabel} draft selections, defer decisions and draft calculations. Confirmed history and every other month stay unchanged.</span><div className="buttonRow"><button className="secondaryButton" onClick={cancelSelectedDraft}>Confirm Cancel {monthLabel} Draft</button><button onClick={() => setCancelDraftKey(null)}>Return Without Cancelling</button></div></div>
            : <button className="secondaryButton" onClick={() => setCancelDraftKey(month.key)}>Cancel {monthLabel} Draft</button>}
        </div>
      ) : <div className="notice">{cancellationExplanation}</div>}

      {tab === "Overview" && <div className="stack">
        <div className="compactPanel"><Toolbar title={`${monthLabel} Fellowship Overview`} actionLabel={lifecycleLabel[month.lifecycle]} /><div className="metricsGrid"><Metric label="Total expense" value={validExpense ? formatSponsorShare(parsedExpense) : "₹0.00"} /><Metric label="Selected sponsors" value={`${month.selectedSponsors.length}`} /><Metric label="Confirmed sponsors" value={`${month.confirmedSponsors.length}`} /><Metric label="Amount received" value={formatSponsorShare(totalPaid)} /></div></div>
        {month.lifecycle === "unprepared" && <div className="compactPanel"><p>Only the immediate next chronological month can be prepared. No payments, history, balances or finalisation state are copied.</p><button disabled={month.key !== latestMonthKey || monthKeys.some((key) => months[key].lifecycle === "draft")} onClick={prepareSelectedMonth}>Prepare {monthLabel} Sponsor Rotation</button></div>}
        {month.lifecycle !== "unprepared" && <button onClick={() => setTab("Queue")}>Open {monthLabel} Sponsor Rotation</button>}
      </div>}

      {tab === "Queue" && <div className="stack">
        <div className="compactPanel"><Toolbar title={`${monthLabel} Sponsor Rotation`} actionLabel={lifecycleLabel[month.lifecycle]} /><label className="moduleSelect">{monthLabel} total Fellowship expense (₹)<input value={month.totalExpenseInput} disabled={month.lifecycle !== "draft"} onChange={(event) => updateMonth(month.key, (current) => ({ ...current, totalExpenseInput: event.target.value }))} /></label><div className="notice">Next suggested member: <strong>{nextSuggestedId ? displayMemberName(nextSuggestedId, currentMembers) : "Unavailable"}</strong>. Drafts and cancellations do not consume turns.</div></div>
        {month.lifecycle === "unprepared" ? <div className="notice">Prepare {monthLabel} before selecting sponsors.</div> : <div className="financeCardList">{canonicalSequence.map((currentMember, index) => { const memberId=fellowshipMemberKey(currentMember); const queueStatus=month.queueStatuses[memberId]; const editable=month.lifecycle === "draft"; return <article className="financeStackCard" key={memberId}><div className="toolbar"><div style={{ display: "grid", gap: "4px" }}><strong>{index + 1}. {currentMember.name}</strong><small style={{ display: "block" }}>{completedByMember.has(memberId) ? `${fellowshipMonthLabel(completedByMember.get(memberId)!)} confirmed` : "No confirmed turn recorded"}</small></div><span className="tag">{memberId === nextSuggestedId ? "Next suggested" : queueStatus || "Eligible"}</span></div>{editable && <div className="buttonRow"><button onClick={() => setSponsorSelected(memberId)}>{month.selectedSponsors.includes(memberId) ? "Remove Selection" : "Select Sponsor"}</button><button className="secondaryButton" onClick={() => deferSponsor(memberId)}>Defer This Month</button>{queueStatus && <button className="secondaryButton" onClick={() => updateMonth(month.key,(current)=>{const statuses={...current.queueStatuses}; delete statuses[memberId]; return {...current,queueStatuses:statuses,selectedSponsors:current.selectedSponsors.filter((id)=>id!==memberId)};})}>Reset</button>}</div>}</article>; })}</div>}
      </div>}

      {tab === "Selected Sponsors" && <div className="stack">
        <div className="compactPanel"><Toolbar title={`Review & Confirm ${monthLabel} Sponsors`} actionLabel={lifecycleLabel[month.lifecycle]} /><div className="metricsGrid"><Metric label="Expense" value={validExpense ? formatSponsorShare(parsedExpense) : "₹0.00"} /><Metric label="Sponsor count" value={`${activeSponsors.length}`} /><Metric label="Share per sponsor" value={formatSponsorShare(share)} /><Metric label="State" value={lifecycleLabel[month.lifecycle]} /></div></div>
        {activeSponsors.length ? <div className="tableLike">{sortFellowshipMemberKeys(activeSponsors,currentMembers).map((memberId,index)=><div className="staticRow" key={memberId}><span>{index+1}</span><strong>{displayMemberName(memberId,currentMembers)}</strong><span>{formatSponsorShare(share)}</span><span className="tag">{month.lifecycle === "draft" ? "Ready to confirm" : "Confirmed"}</span></div>)}</div> : <div className="notice">No sponsors selected for {monthLabel}.</div>}
        {month.lifecycle === "draft" && <button disabled={!validExpense || month.selectedSponsors.length === 0} onClick={confirmSponsors}>Confirm {monthLabel} Sponsors & Start Payment Tracking</button>}
        {month.lifecycle === "payment_tracking" && !hasTransactions && <div className="notice" style={{ display:"flex",gap:"12px",flexWrap:"wrap",justifyContent:"space-between" }}><span>Sponsors are confirmed. Reopening is permitted only before payment recording starts.</span><button onClick={() => updateMonth(month.key,(current)=>({...current,lifecycle:"draft",confirmedSponsors:[],confirmedSharePerSponsor:0,notice:`${monthLabel} sponsor selection reopened before payments started.`}))}>Reopen Sponsor Selection</button></div>}
        {month.lifecycle === "payment_tracking" && hasTransactions && <div className="notice">Sponsor selection cannot be changed after payment recording has started.</div>}
      </div>}

      {tab === "Payments" && <div className="stack"><div className="compactPanel"><Toolbar title={`${monthLabel} Payments`} actionLabel={lifecycleLabel[month.lifecycle]} /><div className="metricsGrid"><Metric label="Share per sponsor" value={formatSponsorShare(month.confirmedSharePerSponsor)} /><Metric label="Received" value={formatSponsorShare(totalPaid)} /><Metric label="Outstanding" value={formatSponsorShare(Math.max(0,month.confirmedSharePerSponsor*month.confirmedSponsors.length-totalPaid))} /><Metric label="Transactions" value={`${Object.values(month.paymentHistories).reduce((sum,history)=>sum+history.length,0)}`} /></div></div>{month.lifecycle === "draft" || month.lifecycle === "unprepared" ? <div className="notice">Confirm {monthLabel} sponsors before recording payments.</div> : month.confirmedSponsors.map((memberId)=>{const paid=month.payments[memberId]||0; const balance=roundFellowshipAmount(month.confirmedSharePerSponsor-paid); const history=month.paymentHistories[memberId]||[]; return <article className="financeStackCard" key={memberId}><div className="toolbar"><div><strong>{displayMemberName(memberId,currentMembers)}</strong><small>{formatSponsorShare(paid)} paid · {formatSponsorShare(balance)} remaining</small></div><span className="tag">{hasPositiveFellowshipBalance(balance)?(paid>0?"Part-paid":"Pending"):"Paid"}</span></div>{month.lifecycle === "payment_tracking" && <div className="buttonRow" style={{flexWrap:"wrap"}}><input aria-label={`Payment for ${displayMemberName(memberId,currentMembers)}`} value={month.paymentInputs[memberId]||""} onChange={(event)=>updateMonth(month.key,(current)=>({...current,paymentInputs:{...current.paymentInputs,[memberId]:event.target.value}}))} /><button disabled={!hasPositiveFellowshipBalance(balance)} onClick={()=>recordPayment(memberId)}>Record Payment</button><button disabled={!hasPositiveFellowshipBalance(balance)} onClick={()=>recordPayment(memberId,balance)}>Record Remaining Balance</button></div>}<div className="stack">{history.map((entry)=><div className="staticRow" key={entry.id}><span>{entry.date}</span><strong>{formatSponsorShare(entry.amount)}</strong>{editingPayment?.paymentId===entry.id?<><input value={editingPayment.amount} onChange={(event)=>setEditingPayment({...editingPayment,amount:event.target.value})}/><button onClick={()=>replacePayment(memberId,entry.id,Number(editingPayment.amount))}>Save</button><button onClick={()=>setEditingPayment(null)}>Return</button></>:cancelPayment?.paymentId===entry.id?<><span>Cancel this exact transaction?</span><button onClick={()=>removePayment(memberId,entry.id)}>Confirm Cancel</button><button onClick={()=>setCancelPayment(null)}>Return</button></>:month.lifecycle === "payment_tracking"?<><button onClick={()=>setEditingPayment({memberId,paymentId:entry.id,amount:String(entry.amount)})}>Edit</button><button onClick={()=>setCancelPayment({memberId,paymentId:entry.id})}>Cancel</button></>:<span className="tag">Read-only</span>}</div>)}</div></article>})}</div>}

      {tab === "History" && <div className="stack"><div className="compactPanel"><Toolbar title={`${monthLabel} Month Summary`} actionLabel={lifecycleLabel[month.lifecycle]} /><div className="metricsGrid"><Metric label="Sponsors" value={`${month.confirmedSponsors.length}`} /><Metric label="Total expense" value={validExpense?formatSponsorShare(parsedExpense):"₹0.00"} /><Metric label="Amount received" value={formatSponsorShare(totalPaid)} /><Metric label="Transferred on finalisation" value={formatSponsorShare(month.transferredOutstanding)} /></div></div>{month.lifecycle === "payment_tracking" && (confirmFinalise?<div className="compactPanel"><strong>Finalise {monthLabel} Fellowship Accounts?</strong><p>The month becomes immutable and outstanding balances are retained as the transfer result.</p><div className="buttonRow"><button onClick={finaliseMonth}>Confirm Finalisation</button><button className="secondaryButton" onClick={()=>setConfirmFinalise(false)}>Return Without Finalising</button></div></div>:<button onClick={()=>setConfirmFinalise(true)}>Finalise {monthLabel} Fellowship Accounts</button>)}{month.lifecycle === "finalised" && <div className="notice">{monthLabel} is finalised, immutable and retained for viewing. Sponsors, payments and payment history remain attached to this month key.</div>}</div>}
    </section>
  );
}
function MemberFellowship({
  authenticatedUserId,
  months,
  selectedMonthKey
}: {
  authenticatedUserId: number | null;
  months: Record<string, FellowshipMonthRecord>;
  selectedMonthKey: string;
}) {
  const { members: currentMembers, status, error, retry } = useCurrentMemberData();

  if (status === "loading") return <div className="notice">Loading personal Fellowship information…</div>;
  if (status === "error") return <div className="notice"><strong>Personal Fellowship information is unavailable.</strong>{error && <span> {error}</span>} <button className="filterButton" onClick={retry}>Retry</button></div>;

  const linkedMember = authenticatedUserId === null
    ? null
    : currentMembers.find((currentMember) => currentMember.userId === authenticatedUserId && typeof currentMember.backendId === "number") || null;

  if (!linkedMember) {
    return (
      <section className="stack" style={{ minWidth: 0 }}>
        <section className="memberPanel">
          <p className="eyebrow">MY FELLOWSHIP</p>
          <h2>Personal Fellowship Summary</h2>
          <div className="notice memberHelperText"><strong>No genuine club member profile is linked to this development test account.</strong> Personal Fellowship information is unavailable.</div>
          <div className="notice memberHelperText">Use Operations → Fellowship for authorized club administration.</div>
        </section>
        <div className="notice memberHelperText">Prototype session data only. Fellowship records are not yet stored as shared backend records.</div>
      </section>
    );
  }

  const memberKey = String(linkedMember.backendId);
  const monthKeys = Object.keys(months).sort((left, right) => right.localeCompare(left));
  const selectedMonth = months[selectedMonthKey] || null;
  const hasPersonalRecord = (month: FellowshipMonthRecord) =>
    month.selectedSponsors.includes(memberKey)
    || month.confirmedSponsors.includes(memberKey)
    || Object.prototype.hasOwnProperty.call(month.payments, memberKey)
    || (month.paymentHistories[memberKey]?.length || 0) > 0;
  const personalHistory = monthKeys.map((key) => months[key]).filter(hasPersonalRecord);

  function personalStatus(month: FellowshipMonthRecord) {
    if (month.lifecycle === "unprepared") return "No Fellowship month is currently prepared";
    if (month.lifecycle === "draft") return month.selectedSponsors.includes(memberKey) ? "Selected — awaiting confirmation" : "Not selected this month";
    if (!month.confirmedSponsors.includes(memberKey)) return "Not selected this month";
    if (month.lifecycle === "finalised") return "Month finalised";
    return "Confirmed sponsor";
  }

  function paymentStatus(month: FellowshipMonthRecord) {
    if (!month.confirmedSponsors.includes(memberKey)) return null;
    const paid = month.payments[memberKey] || 0;
    const balance = roundFellowshipAmount(month.confirmedSharePerSponsor - paid);
    if (!month.paymentHistories[memberKey]?.length && paid === 0) return "Payment not started";
    if (!hasPositiveFellowshipBalance(balance)) return "Paid";
    return paid > 0 ? "Partly paid" : "Not paid";
  }

  const selectedHasRecord = Boolean(selectedMonth && hasPersonalRecord(selectedMonth));
  const selectedConfirmed = Boolean(selectedMonth?.confirmedSponsors.includes(memberKey));
  const selectedPaid = selectedConfirmed ? selectedMonth?.payments[memberKey] || 0 : null;
  const selectedBalance = selectedConfirmed && selectedMonth
    ? roundFellowshipAmount(selectedMonth.confirmedSharePerSponsor - (selectedPaid || 0))
    : null;
  const selectedDraftShare = selectedMonth?.lifecycle === "draft" && selectedMonth.selectedSponsors.includes(memberKey)
    ? Number(selectedMonth.totalExpenseInput) / selectedMonth.selectedSponsors.length
    : null;
  const selectedShare = selectedConfirmed
    ? selectedMonth?.confirmedSharePerSponsor ?? null
    : Number.isFinite(selectedDraftShare) && (selectedDraftShare || 0) > 0
      ? selectedDraftShare
      : null;
  const selectedTransactions = selectedMonth?.paymentHistories[memberKey] || [];

  return (
    <section className="stack" style={{ minWidth: 0 }}>
      <section className="memberPanel">
        <div className="toolbar" style={{ gap: "12px", flexWrap: "wrap" }}>
          <div><p className="eyebrow">MY FELLOWSHIP</p><h2>Personal Fellowship Summary</h2></div>
          <span className="statusPill">Read-only personal view</span>
        </div>
        <div className="memberFellowshipHero">
          <p className="eyebrow">{selectedMonth ? fellowshipMonthLabel(selectedMonth.key) : "Selected month unavailable"}</p>
          <h2>{selectedMonth ? personalStatus(selectedMonth) : "Personal Fellowship information is unavailable."}</h2>
          <p>This view is limited to the Fellowship record linked to your authenticated club member profile.</p>
        </div>
      </section>

      {selectedMonth?.lifecycle === "unprepared" ? (
        <div className="notice memberHelperText">No Fellowship month is currently prepared.</div>
      ) : !selectedMonth || !selectedHasRecord ? (
        <div className="notice memberHelperText">You have no Fellowship sponsorship record for the selected period.</div>
      ) : (
        <section className="memberPanel">
          <Toolbar title="Current or Selected Month" actionLabel={personalStatus(selectedMonth)} />
          <div className="memberDuesSummary">
            <Metric label="Fellowship month" value={fellowshipMonthLabel(selectedMonth.key)} />
            <Metric label="Sponsor status" value={personalStatus(selectedMonth)} />
            <Metric label="Personal share" value={selectedShare === null ? "Not available" : formatSponsorShare(selectedShare)} />
            <Metric label="Amount paid" value={selectedPaid === null ? "Not available" : formatSponsorShare(selectedPaid)} />
            <Metric label="Outstanding balance" value={selectedBalance === null ? "Not available" : formatSponsorShare(selectedBalance)} />
            <Metric label="Payment status" value={paymentStatus(selectedMonth) || "Not applicable"} />
          </div>
          {selectedTransactions.length > 0 && (
            <div className="stack">
              <strong>My payment transactions</strong>
              {selectedTransactions.map((entry) => <div className="staticRow" key={entry.id}><span>{entry.date}</span><strong>{formatSponsorShare(entry.amount)}</strong><span className="tag">Personal payment</span></div>)}
            </div>
          )}
        </section>
      )}

      <section className="memberPanel">
        <Toolbar title="My Fellowship History" actionLabel="Newest first" />
        {personalHistory.length === 0 ? (
          <div className="notice memberHelperText">You have no Fellowship sponsorship record for the selected period.</div>
        ) : (
          <div className="financeCardList">
            {personalHistory.map((month) => {
              const confirmed = month.confirmedSponsors.includes(memberKey);
              const paid = confirmed ? month.payments[memberKey] || 0 : null;
              const balance = confirmed ? roundFellowshipAmount(month.confirmedSharePerSponsor - (paid || 0)) : null;
              const draftShare = month.lifecycle === "draft" && month.selectedSponsors.includes(memberKey)
                ? Number(month.totalExpenseInput) / month.selectedSponsors.length
                : null;
              const share = confirmed ? month.confirmedSharePerSponsor : Number.isFinite(draftShare) && (draftShare || 0) > 0 ? draftShare : null;
              return (
                <article className="financeStackCard" key={month.key}>
                  <div className="toolbar" style={{ gap: "8px", flexWrap: "wrap" }}><strong>{fellowshipMonthLabel(month.key)}</strong><span className="tag">{personalStatus(month)}</span></div>
                  <div className="memberDuesSummary">
                    <Metric label="Personal share" value={share === null ? "Not available" : formatSponsorShare(share)} />
                    <Metric label="Amount paid" value={paid === null ? "Not available" : formatSponsorShare(paid)} />
                    <Metric label="Outstanding" value={balance === null ? "Not available" : formatSponsorShare(balance)} />
                    <Metric label="Payment status" value={paymentStatus(month) || "Not applicable"} />
                  </div>
                </article>
              );
            })}
          </div>
        )}
      </section>

      <div className="notice memberHelperText">Prototype session data only. Fellowship records are not yet stored as shared backend records.</div>
    </section>
  );
}
function FellowshipQueue({
  selectedSponsors,
  confirmationTitle,
  setScreen,
  setFellowshipTab,
  setHighlightedSponsor,
  tempQueueStatuses,
  handleSelectSponsor,
  handleSkipHoldSponsor,
  handleResetSponsor,
  amountPerSponsor,
  totalFellowshipExpense,
  totalFellowshipExpenseInput,
  isValidExpense,
  isFellowshipConfirmed
}: {
  selectedSponsors: string[];
  confirmationTitle: string;
  setScreen: (screen: Screen) => void;
  setFellowshipTab: (tab: FellowshipTab) => void;
  setHighlightedSponsor: (val: string | null) => void;
  tempQueueStatuses: Record<string, string>;
  handleSelectSponsor: (name: string) => void;
  handleSkipHoldSponsor: (name: string) => void;
  handleResetSponsor: (name: string) => void;
  amountPerSponsor: number;
  totalFellowshipExpense: number;
  totalFellowshipExpenseInput: string;
  isValidExpense: boolean;
  isFellowshipConfirmed: boolean;
}) {
  const { members: currentMembers, status: currentMemberStatus, error: currentMemberError, retry: retryCurrentMembers } = useCurrentMemberData();
  const fellowshipQueue = canonicalFellowshipMembers(currentMembers).map((currentMember, index) => ({ order: index + 1, member: fellowshipMemberKey(currentMember), status: "Eligible", last: "Not yet recorded", suggested: index === 0 }));
  const sponsorName = (memberId: string) => displayMemberName(memberId, currentMembers);
  const selectedCount = selectedSponsors.length;
  const isConfirmDisabled = selectedCount === 0 || !isValidExpense;

  if (currentMemberStatus === "loading") return <div className="notice">Loading current members...</div>;
  if (currentMemberStatus === "error") return <div className="notice"><strong>Current member data could not be loaded.</strong>{currentMemberError && <span> {currentMemberError}</span>} <button className="filterButton" onClick={retryCurrentMembers}>Retry</button></div>;
  if (currentMembers.length === 0) return <div className="notice">No current members are available.</div>;

  return (
    <section className="stack">
      <Toolbar title="Members Due for Sponsorship" actionLabel={`${selectedSponsors.length} selected`} />

      {!isValidExpense ? (
        <div style={{ padding: "12px 16px", backgroundColor: "#fff3cd", color: "#664d03", borderRadius: "6px", marginBottom: "1rem", fontSize: "0.9rem" }}>
          Enter the total fellowship expense.
        </div>
      ) : selectedCount === 0 ? (
        <div className="stack" style={{ gap: "8px", marginBottom: "1rem" }}>
          <div style={{ padding: "12px 16px", backgroundColor: "#e8f0fe", color: "#1a73e8", borderRadius: "6px", fontSize: "0.9rem" }}>
            <strong>Each member&apos;s share: ₹0.00</strong>
          </div>
          <div style={{ padding: "12px 16px", backgroundColor: "#fff3cd", color: "#664d03", borderRadius: "6px", fontSize: "0.9rem" }}>
            Select sponsors first to calculate each member’s share.
          </div>
          <div style={{ fontSize: "0.8rem", color: "#5f6368", padding: "0 12px" }}>
            * Final accounting can apply rounding rules before payment records are created.
          </div>
        </div>
      ) : (
        <div style={{ padding: "12px 16px", backgroundColor: "#e8f0fe", color: "#1a73e8", borderRadius: "6px", marginBottom: "1rem", fontSize: "0.9rem" }}>
          <strong>Each member&apos;s share: {formatSponsorShare(amountPerSponsor)}</strong> (Total {formatSponsorShare(totalFellowshipExpense)} divided among {selectedSponsors.length} sponsors).
          <div style={{ fontSize: "0.8rem", color: "#5f6368", marginTop: "4px" }}>
            * Final accounting can apply rounding rules before payment records are created.
          </div>
        </div>
      )}

      {isFellowshipConfirmed && (
        <div className="notice" style={{ backgroundColor: "#e8f0fe", color: "#1a73e8", marginBottom: "1rem" }}>
          This month’s sponsors are confirmed. Reopen the selection before changing the expense or sponsors.
        </div>
      )}

      <div className="stack" style={{ gap: "0.75rem", marginBottom: "1rem" }}>
        {fellowshipQueue.map((item) => {
          const isSelected = selectedSponsors.includes(item.member);
          const currentStatus = tempQueueStatuses[item.member] || item.status;

          const displayStatus = isSelected ? "Selected for this month" :
                                currentStatus === "Deferred for this month" ? "Deferred for this month" :
                                item.suggested && currentStatus === "Eligible" ? "Next suggested sponsor" :
                                currentStatus === "Eligible" ? "Due for sponsorship" : currentStatus;

          return (
            <div
              key={item.member}
              className="queueItem"
              style={{
                display: "flex",
                justifyContent: "space-between",
                alignItems: "center",
                padding: "12px 16px",
                border: "1px solid #dadce0",
                borderRadius: "6px",
                backgroundColor: isSelected ? "#e8f0fe" : currentStatus === "Deferred for this month" ? "#fce8e6" : "#fff",
                borderColor: isSelected ? "#1a73e8" : currentStatus === "Deferred for this month" ? "#d93025" : "#dadce0"
              }}
            >
              <div>
                <strong>{item.order}. {sponsorName(item.member)}</strong>
                <div style={{ fontSize: "0.85rem", opacity: 0.8, marginTop: "4px" }}>
                  Status: <span className="tag">{displayStatus}</span> • Last turn: {item.last}
                </div>
              </div>
              <div className="buttonRow" style={{ margin: 0, gap: "8px" }}>
                {isSelected ? (
                  <button
                    className="filterButton"
                    style={{ padding: "6px 12px" }}
                    onClick={() => handleSelectSponsor(item.member)}
                    disabled={isFellowshipConfirmed}
                  >
                    Deselect
                  </button>
                ) : currentStatus === "Deferred for this month" ? (
                  <button
                    className="filterButton secondaryButton"
                    style={{ padding: "6px 12px" }}
                    onClick={() => handleResetSponsor(item.member)}
                    disabled={isFellowshipConfirmed}
                  >
                    Restore to Rotation
                  </button>
                ) : (
                  <>
                    <button
                      className="filterButton"
                      style={{ padding: "6px 12px" }}
                      onClick={() => handleSelectSponsor(item.member)}
                      disabled={isFellowshipConfirmed}
                    >
                      Select
                    </button>
                    <button
                      className="filterButton secondaryButton"
                      style={{ padding: "6px 12px", color: "#d93025", borderColor: "#fce8e6" }}
                      onClick={() => handleSkipHoldSponsor(item.member)}
                      disabled={isFellowshipConfirmed}
                    >
                      Defer This Month
                    </button>
                  </>
                )}
              </div>
            </div>
          );
        })}
      </div>
      <div className="buttonRow">
        <div className="stack" style={{ gap: "6px" }}>
          {confirmationTitle === "Review & Confirm July 2026 Sponsors" && !isValidExpense && (
            <div className="notice" style={{ margin: 0, backgroundColor: "#fff3cd", color: "#664d03" }}>
              Enter the July 2026 total fellowship expense before reviewing and confirming sponsors.
            </div>
          )}
          <button onClick={() => { setScreen("confirm-sponsors"); setHighlightedSponsor(null); }} disabled={isConfirmDisabled}>
            {confirmationTitle}
          </button>
        </div>
        <button
          className="secondaryButton"
          onClick={() => {
            setScreen("fellowship-administration");
            setFellowshipTab("Overview");
          }}
        >
          Back to Overview
        </button>
      </div>
    </section>
  );
}

function ConfirmSponsors({
  title,
  monthLabel,
  selectedSponsors,
  setScreen,
  setFellowshipTab,
  setFellowshipConfirmationMessage,
  amountPerSponsor,
  totalFellowshipExpense,
  setIsFellowshipConfirmed,
  totalFellowshipExpenseInput,
  setConfirmedSponsors,
  setConfirmedSharePerSponsor,
  setSponsorPayments,
  setSponsorPaymentInputs,
  setSponsorPaymentHistories,
  setExpandedHistories,
  setLastRecordedPayment,
  setHighlightedSponsor
}: {
  title: string;
  monthLabel: string;
  selectedSponsors: string[];
  setScreen: (screen: Screen) => void;
  setFellowshipTab: (tab: FellowshipTab) => void;
  setFellowshipConfirmationMessage: (msg: string | null) => void;
  amountPerSponsor: number;
  totalFellowshipExpense: number;
  setIsFellowshipConfirmed: (val: boolean) => void;
  totalFellowshipExpenseInput: string;
  setConfirmedSponsors: (val: string[]) => void;
  setConfirmedSharePerSponsor: (val: number) => void;
  setSponsorPayments: React.Dispatch<React.SetStateAction<Record<string, number>>>;
  setSponsorPaymentInputs: React.Dispatch<React.SetStateAction<Record<string, string>>>;
  setSponsorPaymentHistories: React.Dispatch<React.SetStateAction<Record<string, Array<{ id: string; date: string; amount: number }>>>>;
  setExpandedHistories: React.Dispatch<React.SetStateAction<Record<string, boolean>>>;
  setLastRecordedPayment: (val: {
    memberName: string;
    paymentAmount: number;
    cumulativePaid: number;
    remainingBalance: number;
    type: "partial" | "full";
  } | null) => void;
  setHighlightedSponsor: (val: string | null) => void;

}) {
  const { members: currentMembers } = useCurrentMemberData();
  const sponsorName = (memberId: string) => displayMemberName(memberId, currentMembers);
  const orderedSelectedSponsors = sortFellowshipMemberKeys(selectedSponsors, currentMembers);
  const selectedCount = orderedSelectedSponsors.length;
  const expenseTrimmed = totalFellowshipExpenseInput.trim();
  const parsed = parseFloat(expenseTrimmed);
  const isValidExpense = /^\d+(\.\d*)?$/.test(expenseTrimmed) && !isNaN(parsed) && parsed > 0;
  const isConfirmDisabled = selectedCount === 0 || !isValidExpense;

  return (
    <section className="stack">
      <div className="compactPanel">
        <Toolbar title={title} actionLabel={monthLabel} />
        <div className="metricsGrid" style={{ marginTop: "1rem" }}>
          <Metric label="Total expense" value={isValidExpense ? formatSponsorShare(totalFellowshipExpense) : "Not entered (₹0.00)"} />
          <Metric label="Sponsor count" value={`${selectedCount}`} />
          <Metric label="Each sponsor’s share" value={isValidExpense && selectedCount > 0 ? formatSponsorShare(amountPerSponsor) : "₹0.00"} />
          <Metric label="Confirmation status" value="Not confirmed" />
        </div>
      </div>

      <div className="notice">Review the selected members and each member’s calculated fellowship share. Confirming this selection will prepare {monthLabel} payment tracking. This prototype does not create real payments or accounting entries.</div>

      {selectedCount === 0 ? (
        <div className="stack" style={{ gap: "8px", marginBottom: "1rem" }}>
          <div style={{ padding: "12px 16px", backgroundColor: "#e8f0fe", color: "#1a73e8", borderRadius: "6px", fontSize: "0.9rem" }}>
            <strong>Each member&apos;s share: ₹0.00</strong>
          </div>
          <div style={{ padding: "12px 16px", backgroundColor: "#fff3cd", color: "#664d03", borderRadius: "6px", fontSize: "0.9rem" }}>
            Select sponsors first to calculate each member’s share.
          </div>
          <div style={{ fontSize: "0.8rem", color: "#5f6368", padding: "0 12px" }}>
            * Final accounting can apply rounding rules before payment records are created.
          </div>
        </div>
      ) : !isValidExpense ? (
        <div style={{ padding: "12px 16px", backgroundColor: "#fff3cd", color: "#664d03", borderRadius: "6px", marginBottom: "1rem", fontSize: "0.9rem" }}>
          Enter the total fellowship expense.
        </div>
      ) : (
        <div style={{ padding: "12px 16px", backgroundColor: "#e8f0fe", color: "#1a73e8", borderRadius: "6px", marginBottom: "1rem", fontSize: "0.9rem" }}>
          <strong>Each member&apos;s share: {formatSponsorShare(amountPerSponsor)}</strong> (Total {formatSponsorShare(totalFellowshipExpense)} divided among {selectedSponsors.length} sponsors).
          <div style={{ fontSize: "0.8rem", color: "#5f6368", marginTop: "4px" }}>
            * Final accounting can apply rounding rules before payment records are created.
          </div>
        </div>
      )}

      <div className="tableLike">
        {selectedSponsors.length === 0 ? (
          <p style={{ padding: "16px", color: "#666", textAlign: "center" }}>No sponsors selected. Please go back to the Rotation and select sponsors.</p>
        ) : (
          orderedSelectedSponsors.map((sponsor, index) => (
            <div key={sponsor} className="staticRow">
              <span>{index + 1}</span>
              <strong>{sponsorName(sponsor)}</strong>
              <span>{isValidExpense ? formatSponsorShare(amountPerSponsor) : "₹0.00"}</span>
              <span className="tag">Ready to confirm</span>
            </div>
          ))
        )}
      </div>
      <div className="buttonRow" style={{ flexWrap: "wrap", gap: "8px" }}>
        <button
          disabled={isConfirmDisabled}
          onClick={() => {
            setIsFellowshipConfirmed(true);
            setConfirmedSponsors([...orderedSelectedSponsors]);
            setConfirmedSharePerSponsor(amountPerSponsor);
            setSponsorPayments({});
            setSponsorPaymentInputs({});
            setSponsorPaymentHistories({});
            setExpandedHistories({});
            setLastRecordedPayment(null);
            setHighlightedSponsor(null);
            setFellowshipConfirmationMessage(`Success: ${monthLabel} fellowship sponsors have been confirmed. Payment recording is now ready.`);
            setFellowshipTab("Payments");
            setScreen("fellowship-administration");
          }}
        >
          Confirm Sponsors & Start Payment Tracking
        </button>
        <button
          className="secondaryButton"
          onClick={() => {
            setFellowshipTab("Selected Sponsors");
            setScreen("fellowship-administration");
          }}
        >
          Back to Sponsor Selection
        </button>
        <button
          className="secondaryButton"
          onClick={() => {
            setFellowshipTab("Queue");
            setScreen("fellowship-administration");
          }}
        >
          Back to Sponsor Rotation
        </button>
        <button
          className="secondaryButton"
          onClick={() => {
            setFellowshipTab("Overview");
            setScreen("fellowship-administration");
          }}
        >
          Back to Fellowship Overview
        </button>
      </div>
    </section>
  );
}

function FellowshipSponsorDetail({
  sponsor,
  onBack
}: {
  sponsor: {
    memberId: string;
    name: string;
    amount: number;
    status: string;
    amountPaid?: number;
    balanceRemaining?: number;
    paymentStatus?: string;
    source: "Selected Sponsors" | "Payments";
  };
  onBack: () => void;
}) {
  const showPaymentDetail = sponsor.amountPaid !== undefined && sponsor.balanceRemaining !== undefined;

  return (
    <section className="stack">
      <div className="compactPanel">
        <div className="toolbar">
          <div>
            <p className="eyebrow">Review Details — Prototype only</p>
            <h2>Fellowship Sponsor Review</h2>
          </div>
          <button className="secondaryButton" onClick={onBack}>
            ← Back to {sponsor.source}
          </button>
        </div>
        <div style={{ display: "flex", flexDirection: "column", gap: "16px", padding: "16px" }}>
          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "12px" }}>
            <div>
              <span style={{ fontSize: "0.85rem", color: "#666", display: "block" }}>Sponsor Name</span>
              <strong style={{ fontSize: "1.1rem" }}>{sponsor.name}</strong>
            </div>
            <div>
              <span style={{ fontSize: "0.85rem", color: "#666", display: "block" }}>Fellowship Share</span>
              <strong style={{ fontSize: "1.1rem" }}>{formatSponsorShare(sponsor.amount)}</strong>
            </div>
            {showPaymentDetail && (
              <>
                <div>
                  <span style={{ fontSize: "0.85rem", color: "#666", display: "block" }}>Amount Paid</span>
                  <strong style={{ fontSize: "1.1rem" }}>{formatSponsorShare(sponsor.amountPaid ?? 0)}</strong>
                </div>
                <div>
                  <span style={{ fontSize: "0.85rem", color: "#666", display: "block" }}>Balance Remaining</span>
                  <strong style={{ fontSize: "1.1rem" }}>{formatSponsorShare(sponsor.balanceRemaining ?? 0)}</strong>
                </div>
                <div>
                  <span style={{ fontSize: "0.85rem", color: "#666", display: "block" }}>Payment Status</span>
                  <strong style={{ fontSize: "1.1rem", color: sponsor.paymentStatus === "Paid" ? "#137333" : sponsor.paymentStatus === "Partly paid" ? "#b06000" : "#d93025" }}>
                    {sponsor.paymentStatus}
                  </strong>
                </div>
              </>
            )}
            {!showPaymentDetail && (
              <div>
                <span style={{ fontSize: "0.85rem", color: "#666", display: "block" }}>Current Status</span>
                <span className="tag" style={{ display: "inline-block", marginTop: "4px" }}>{sponsor.status}</span>
              </div>
            )}
            <div>
              <span style={{ fontSize: "0.85rem", color: "#666", display: "block" }}>Source Module Section</span>
              <strong style={{ display: "inline-block", marginTop: "4px" }}>{sponsor.source}</strong>
            </div>
          </div>

          <div style={{ marginTop: "8px", padding: "12px", borderLeft: "4px solid #1a73e8", backgroundColor: "#f8f9fa", borderRadius: "4px" }}>
            <strong style={{ fontSize: "0.9rem", color: "#1a73e8" }}>Prototype Note</strong>
            <p style={{ margin: "4px 0 0 0", fontSize: "0.85rem", color: "#5f6368", lineHeight: "1.4" }}>
              This is a dummy prototype review screen. In the final system, you will be able to verify transaction proofs, create specific payment vouchers, edit sponsor shares, and trigger automatic WhatsApp and email payment notifications to this member.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}

function ledgerEntryType(row: (typeof ledgerRows)[number]) {
  if (row.type === "Monthly fellowship share") return "Fellowship sponsor contribution";
  return row.type;
}

function ledgerBalanceImpact(row: (typeof ledgerRows)[number]) {
  if (row.credit) return `${formatSponsorShare(row.credit)} reduced from dues`;
  return `+${formatSponsorShare(row.debit)} due`;
}

function ledgerExtraFields(row: (typeof ledgerRows)[number]) {
  if (row.credit) {
    return ["Payment Mode: UPI", "Receipt No: RCP-001"];
  }
  if (row.type === "Monthly fellowship share") {
    return ["Linked Module: Fellowship Sponsorship - June 2026"];
  }
  return [];
}

function Ledger({
  role,
  member,
  setScreen,
  isPersonal = false,
  amountPerSponsor
}: {
  role: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  isPersonal?: boolean;
  amountPerSponsor?: number;
}) {
  const finalAmountPerSponsor = amountPerSponsor ?? 2500;

  const mappedLedgerRows = ledgerRows.map((row) => {
    if (row.type === "Monthly fellowship share" || row.id === "ledger-003") {
      return { ...row, debit: finalAmountPerSponsor };
    }
    return row;
  });

  const mappedOutstandingDues = outstandingDues.map((due) => {
    if (due.label === "Monthly fellowship share" || due.id === "DUE-004") {
      return { ...due, amount: finalAmountPerSponsor };
    }
    return due;
  });

  const visibleRows = (role === "member" || isPersonal) ? mappedLedgerRows.filter((row) => row.member === member.name) : mappedLedgerRows;
  const memberPendingDues = mappedOutstandingDues.filter((due) => due.member === member.name && due.status === "Pending");
  const pendingTotal = memberPendingDues.reduce((sum, due) => sum + due.amount, 0);
  const memberDebits = visibleRows.reduce((sum, row) => sum + row.debit, 0);
  const memberCredits = visibleRows.reduce((sum, row) => sum + row.credit, 0);
  const netBalance = memberCredits - memberDebits;
  const currentDues = pendingTotal;
  const advanceBalance = currentDues === 0 && netBalance > 0 ? netBalance : 0;
  const lastPayment = [...visibleRows].reverse().find((row) => row.credit > 0);

  if (role === "member" || isPersonal) {
    return (
      <section className="stack">
        <Toolbar title="My Dues" actionLabel="Own records only" />
        <MemberFinanceSummaryPanel role={role} showPendingItems />
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

function MemberDuesDetail({
  role = "member",
  member,
  setScreen,
  amountPerSponsor
}: {
  role?: Role;
  member: Member;
  setScreen: (screen: Screen) => void;
  amountPerSponsor?: number;
}) {
  const finalAmountPerSponsor = amountPerSponsor ?? 2500;

  const mappedLedgerRows = ledgerRows.map((row) => {
    if (row.type === "Monthly fellowship share" || row.id === "ledger-003") {
      return { ...row, debit: finalAmountPerSponsor };
    }
    return row;
  });

  const mappedOutstandingDues = outstandingDues.map((due) => {
    if (due.label === "Monthly fellowship share" || due.id === "DUE-004") {
      return { ...due, amount: finalAmountPerSponsor };
    }
    return due;
  });

  const rows = mappedLedgerRows.filter((row) => row.member === member.name);
  const memberPendingDues = mappedOutstandingDues.filter((due) => due.member === member.name && due.status === "Pending");
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
        <button className="secondaryButton" onClick={() => setScreen("my-dues")}>Back to My Dues</button>
        <button className="secondaryButton" onClick={() => setScreen("dashboard")}>Back to Dashboard</button>
      </div>
      <MemberFinanceSummaryPanel role={role} showPendingItems />
      <section className="memberPanel">
        <Toolbar title="Current Dues Details" actionLabel="Member view" />
        <div className="notice memberHelperText">Current Dues, Member Finance Summary, Last Payment, Receipt Status, and Payment History are shown only through existing frontend data and backend APIs.</div>
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

function ProfileUpdateDetail({
  update,
  setScreen
}: {
  update: typeof pendingMemberUpdates[number] | null;
  setScreen: (screen: Screen) => void;
}) {
  if (!update) {
    return (
      <section className="stack">
        <div className="compactPanel">
          <button className="secondaryButton compactBackButton" onClick={() => setScreen("nav-detail")}>← Back to List</button>
          <div className="notice memberHelperText">No update request selected.</div>
        </div>
      </section>
    );
  }

  let changeDetails = {
    field: "Phone Number",
    oldValue: "+91 98765 10004",
    newValue: "+91 98765 99999",
    reason: "Correction of typo in directory record."
  };

  if (update.member.includes("Secretary")) {
    changeDetails = {
      field: "Anniversary Consent",
      oldValue: "Hidden / Restricted",
      newValue: "Visible to Members",
      reason: "Updating privacy settings to show anniversary to all club members."
    };
  } else if (update.member.includes("Treasurer")) {
    changeDetails = {
      field: "Blood Donor Consent",
      oldValue: "No Consent (Not Shared)",
      newValue: "Consent Granted (Show O+)",
      reason: "Opting in as active blood donor for club emergency requests."
    };
  }

  return (
    <section className="stack">
      <div className="compactPanel">
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("nav-detail")}>← Back to List</button>
        <Toolbar title="Profile Update Request Details" actionLabel="Review Pending Approval" />
      </div>

      <div className="memberPanel">
        <div className="toolbar">
          <h3>Request Information</h3>
          <span className="statusPill">{update.status}</span>
        </div>
        <div className="detailGrid">
          <div className="fieldGrid" style={{ gridTemplateColumns: "1fr 1fr" }}>
            <div className="field">
              <span className="fieldLabel">Member Name</span>
              <strong>{update.member}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Change Requested</span>
              <strong>{update.change}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Submitted Date</span>
              <strong>{update.submitted}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Submission Status</span>
              <strong>{update.status}</strong>
            </div>
          </div>
        </div>
      </div>

      <div className="memberPanel">
        <Toolbar title="Field Comparison (Before vs After)" actionLabel="Proposed Change" />
        <table className="tableLike" style={{ width: "100%", borderCollapse: "collapse", marginTop: "1rem" }}>
          <thead>
            <tr style={{ textAlign: "left", borderBottom: "1px solid #ccc" }}>
              <th style={{ padding: "0.5rem" }}>Field</th>
              <th style={{ padding: "0.5rem" }}>Current Value</th>
              <th style={{ padding: "0.5rem", color: "#2563eb" }}>Proposed Value</th>
            </tr>
          </thead>
          <tbody>
            <tr style={{ borderBottom: "1px solid #eee" }}>
              <td style={{ padding: "0.8rem 0.5rem" }}><strong>{changeDetails.field}</strong></td>
              <td style={{ padding: "0.8rem 0.5rem", color: "#ef4444" }}>{changeDetails.oldValue}</td>
              <td style={{ padding: "0.8rem 0.5rem", color: "#10b981", fontWeight: "bold" }}>{changeDetails.newValue}</td>
            </tr>
          </tbody>
        </table>
        <div style={{ marginTop: "1rem" }} className="notice">
          <strong>Member&apos;s Reason / Comments:</strong><br />
          {changeDetails.reason}
        </div>
      </div>

      <div className="buttonRow">
        <button onClick={() => {
          alert("Prototype approval simulation: Change approved successfully.");
          setScreen("nav-detail");
        }}>Approve Change</button>
        <button className="secondaryButton" onClick={() => {
          alert("Prototype rejection simulation: Change request rejected.");
          setScreen("nav-detail");
        }}>Reject</button>
      </div>
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
      <div className="notice memberHelperText">Use this internal MVP hub to find Record Payment, Receipts, Receipt PDF, and Payment History without adding new finance backend behavior.</div>
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
  const { session } = useDevAuth();
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
        if (!session) throw new Error("Payment session missing");
        const token = session.token;
        const authenticatedId = session.user.id;
        const loadedClubId = session.membership.club.id;

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
          .filter((member) => isImportedCurrentMember(member) && member.status === "active");

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
  }, [role, canRecordPayment, session]);

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
      <div className="notice memberHelperText">Select a member, review Current Dues and Last Payment, then record payment only if the existing backend context is ready.</div>
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
  const { session } = useDevAuth();
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
        if (!session) throw new Error("Receipt session missing");
        const token = session.token;
        const authenticatedId = session.user.id;
        const loadedClubId = session.membership.club.id;

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
            .filter((member) => isImportedCurrentMember(member) && member.status === "active");

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
  }, [canUsePstReceiptAccess, role, session]);

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
      <div className="notice memberHelperText">Receipt and Receipt PDF access are available here only for already issued payment receipts supported by the existing backend.</div>

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
  const { session } = useDevAuth();
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
        if (!session) throw new Error("Payment history session missing.");
        const token = session.token;
        const authenticatedId = session.user.id;
        const loadedClubId = session.membership.club.id;

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
            .filter((member) => isImportedCurrentMember(member) && member.status === "active");
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
  }, [canUseSelectedMemberHistory, role, session]);

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
      <div className="notice memberHelperText">Use Payment History to confirm existing payment records and receipt status after member selection. Refreshes and pagination reuse the current backend API.</div>

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
        <div className="notice memberHelperText">Member view loads only your own payment history. Other members&apos; payment history is not requested.</div>
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
  const { session } = useDevAuth();
  const canUseMemberImportPreview = role === "president" || role === "secretary" || role === "treasurer";
  const [status, setStatus] = useState<MemberImportPreviewStatus>(canUseMemberImportPreview ? "initializing" : "ready");
  const [message, setMessage] = useState(
    canUseMemberImportPreview
      ? "Loading member import preview access context..."
      : "Member import preview is available only to authorized officers."
  );
  const [previewToken, setPreviewToken] = useState<string | null>(null);
  const [previewClubId, setPreviewClubId] = useState<number | null>(null);
  const [pasteRows, setPasteRows] = useState("");
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
        if (!session) throw new Error("Member import preview session missing.");
        const token = session.token;
        const authenticatedId = session.user.id;
        const loadedClubId = session.membership.club.id;

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
  }, [canUseMemberImportPreview, role, session]);

  async function runMemberImportPreview(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    resetMemberImportCommitState();

    if (!canUseMemberImportPreview) {
      setStatus("error");
      setMessage("Normal Member role cannot use member import preview.");
      return;
    }

    const hasMeaningfulInput = pasteRows.trim().split(/\r?\n/).some((line) => line.trim().length > 0);
    const rows = parseMemberImportPreviewRows(pasteRows);
    if (!hasMeaningfulInput || !rows.length) {
      setPreview(null);
      setStatus("empty");
      setMessage("Enter at least one non-empty member row before running Preview.");
      return;
    }

    if (!previewToken || !previewClubId) {
      setStatus("error");
      setMessage("Member import preview cannot run without an active club context.");
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
            placeholder="Member Code, Full Name, Phone, Email"
          />
          <span className="memberHelperText"><strong>Example only — not submitted unless copied into the input field.</strong><br />Member Code, Full Name, Phone, Email<br />SYN-3FK-001, Synthetic Preview Member, 9000000000, synthetic.preview@example.test</span>
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

function OutstandingDues({ setScreen, amountPerSponsor }: { setScreen: (screen: Screen) => void; amountPerSponsor?: number }) {
  const finalAmountPerSponsor = amountPerSponsor ?? 2500;
  const mappedOutstandingDues = outstandingDues.map((due) => {
    if (due.label === "Monthly fellowship share" || due.id === "DUE-004") {
      return { ...due, amount: finalAmountPerSponsor };
    }
    return due;
  });
  return (
    <section className="stack">
      <Toolbar title="Outstanding Dues" actionLabel="Treasurer working list" />
      <div className="financeCardList">
        {mappedOutstandingDues.map((due) => (
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

function CollectionSummary({ detail, setDetail, amountPerSponsor }: { detail: CollectionDetail; setDetail: (detail: CollectionDetail) => void; amountPerSponsor?: number }) {
  if (!verifiedOperationalSourceConnected("collections")) return <section className="stack"><Toolbar title="Collection Summary" actionLabel="Not connected" /><div className="notice">Operational reports are not connected to a verified source yet.</div><div className="notice memberHelperText">Real reports and collection summaries will appear here only after a verified source is connected.</div></section>;

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
      {detail === "pending" && <CollectionDetailPanel title="Pending Dues">{pendingDuesRows(amountPerSponsor)}</CollectionDetailPanel>}
      {detail === "fellowship" && <CollectionDetailPanel title="Fellowship Pending">{fellowshipPendingRows(amountPerSponsor)}</CollectionDetailPanel>}
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

function pendingDuesRows(amountPerSponsor?: number) {
  const finalAmountPerSponsor = amountPerSponsor ?? 2500;
  const mappedOutstandingDues = outstandingDues.map((due) => {
    if (due.label === "Monthly fellowship share" || due.id === "DUE-004") {
      return { ...due, amount: finalAmountPerSponsor };
    }
    return due;
  });
  return mappedOutstandingDues.map((due) => (
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

function fellowshipPendingRows(amountPerSponsor?: number) {
  const finalAmountPerSponsor = amountPerSponsor ?? 2500;
  const rows = [
    ["June 2026", "Lion Secretary User", formatSponsorShare(finalAmountPerSponsor), formatSponsorShare(0), formatSponsorShare(finalAmountPerSponsor), "Pending"],
    ["June 2026", "Lion Treasurer User", formatSponsorShare(finalAmountPerSponsor), formatSponsorShare(0), formatSponsorShare(finalAmountPerSponsor), "Pending"],
    ["June 2026", "Lion Bijesh", formatSponsorShare(finalAmountPerSponsor), formatSponsorShare(finalAmountPerSponsor), formatSponsorShare(0), "Paid"]
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

function FellowshipPayments({ view = "Current Month", setScreen, amountPerSponsor }: { view?: FellowshipPaymentView; setScreen: (screen: Screen) => void; amountPerSponsor: number }) {
  const records = [
    { sponsor: "Lion Bijesh", month: "June 2026 Fellowship", amount: amountPerSponsor, status: "Paid", receipt: "RCP-010" },
    { sponsor: "Lion Secretary User", month: "June 2026 Fellowship", amount: amountPerSponsor, status: "Pending", receipt: "Not issued" },
    { sponsor: "Lion Treasurer User", month: "June 2026 Fellowship", amount: amountPerSponsor, status: "Pending", receipt: "Not issued" }
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
              <span>Amount: {formatSponsorShare(record.amount)}</span>
              <span>Payment Status: {record.status}</span>
              <span>Receipt: {record.receipt}</span>
            </div>
            <button className="filterButton" onClick={() => setScreen(record.status === "Paid" ? "receipt-list" : "record-member-payment")}>
              {record.status === "Paid" ? "Open Receipt List" : "Record Payment"}
            </button>
          </article>
        ))}
      </div>
    </section>
  );
}

function EventsMeetings({
  role,
  selectedSection,
  onSelectEventMeeting
}: {
  role: Role;
  member: Member;
  selectedSponsors: string[];
  setScreen: (screen: Screen) => void;
  selectedSection?: EventMeetingSection | null;
  onSelectEventMeeting?: (item: BackendEventMeeting) => void;
}) {
  const { session } = useDevAuth();
  const canManageEvents = role === "president" || role === "secretary";
  const [status, setStatus] = useState<"loading" | "ready" | "empty" | "missing-club" | "unauthenticated" | "forbidden" | "error">("loading");
  const [message, setMessage] = useState("Loading Events & Meetings from the backend...");
  const [token, setToken] = useState<string | null>(null);
  const [clubId, setClubId] = useState<number | null>(null);
  const [events, setEvents] = useState<BackendEventMeeting[]>([]);
  const [refreshKey, setRefreshKey] = useState(0);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState<EventMeetingFormState>(defaultEventMeetingForm());
  const [editingId, setEditingId] = useState<number | null>(null);
  const [cancelReason, setCancelReason] = useState("");

  useEffect(() => {
    const controller = new AbortController();

    async function loadEvents() {
      setStatus("loading");
      setMessage("Loading Events & Meetings from the verified backend...");
      setEvents([]);
      setEditingId(null);
      setCancelReason("");

      try {
        if (!session) throw new Error("unauthenticated");
        const loadedToken = session.token;
        const authHeaders = { Accept: "application/json", Authorization: "Bearer " + loadedToken };
        const loadedClubId = session.membership.club.id;
        if (typeof loadedClubId !== "number") throw new Error("missing-club");

        const url = API_BASE_URL + "/clubs/" + loadedClubId + "/events" + eventMeetingQuery(selectedSection, canManageEvents);
        const listResponse = await fetch(url, { headers: authHeaders, signal: controller.signal });
        if (listResponse.status === 401) throw new Error("unauthenticated");
        if (listResponse.status === 403) throw new Error("forbidden");
        if (!listResponse.ok) throw new Error(await eventApiError(listResponse, "Events & Meetings could not be loaded."));

        const payload = await listResponse.json();
        const loadedEvents = (payload.data ?? []) as BackendEventMeeting[];
        setToken(loadedToken);
        setClubId(loadedClubId);
        setEvents(loadedEvents);
        setStatus(loadedEvents.length === 0 ? "empty" : "ready");
        setMessage(loadedEvents.length === 0 ? "No Events & Meetings records match this view." : "Events & Meetings loaded from the verified backend.");
      } catch (error) {
        if (controller.signal.aborted) return;
        const value = error instanceof Error ? error.message : "Events & Meetings could not be loaded.";
        if (value === "unauthenticated") {
          setStatus("unauthenticated");
          setMessage("Please sign in again to view Events & Meetings.");
        } else if (value === "forbidden") {
          setStatus("forbidden");
          setMessage("This role is not authorized to view this Events & Meetings context.");
        } else if (value === "missing-club") {
          setStatus("missing-club");
          setMessage("No active club context is available for Events & Meetings.");
        } else {
          setStatus("error");
          setMessage(value);
        }
      }
    }

    loadEvents();

    return () => controller.abort();
  }, [role, selectedSection, canManageEvents, refreshKey, session]);

  function sectionForEvent(event: BackendEventMeeting): EventMeetingSection {
    return eventMeetingSection(event);
  }

  const sections: EventMeetingSection[] = selectedSection ? [selectedSection] : ["Upcoming Events", "Upcoming Meetings", "Past Events", "Meeting Notices"];
  const sectionedEvents = events.filter((event) => !selectedSection || sectionForEvent(event) === selectedSection);
  const title = selectedSection ?? "Events & Meetings";

  function beginCreate() {
    setEditingId(null);
    setForm(defaultEventMeetingForm());
    setMessage("Create a Draft event or meeting. Publish only after backend confirmation.");
  }

  function beginEdit(event: BackendEventMeeting) {
    setEditingId(event.id);
    setForm(formFromEventMeeting(event));
    setMessage("Editing uses the selected backend ID " + event.id + " and lock version " + (event.lock_version ?? "unavailable") + ".");
  }

  async function submitForm(event: React.FormEvent) {
    event.preventDefault();
    if (!token || !clubId) {
      setStatus("error");
      setMessage("Events & Meetings cannot submit without an authenticated club context.");
      return;
    }

    setSubmitting(true);
    try {
      const isEditing = editingId !== null;
      const response = await fetch(API_BASE_URL + "/clubs/" + clubId + "/events" + (isEditing ? "/" + editingId : ""), {
        method: isEditing ? "PATCH" : "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: "Bearer " + token },
        body: JSON.stringify(eventMeetingPayload(form, isEditing))
      });

      if (!response.ok) throw new Error(await eventApiError(response, isEditing ? "Event update failed." : "Event creation failed."));
      setMessage(isEditing ? "Event or meeting updated by the backend." : "Draft event or meeting created by the backend.");
      setEditingId(null);
      setForm(defaultEventMeetingForm());
      setRefreshKey((key) => key + 1);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Events & Meetings submission failed.");
    } finally {
      setSubmitting(false);
    }
  }

  async function runTransition(event: BackendEventMeeting, transition: "publish" | "cancel" | "complete") {
    if (!token || !clubId || typeof event.lock_version !== "number") {
      setMessage("Lifecycle action cannot run without token, club, and lock version.");
      return;
    }

    setSubmitting(true);
    try {
      const body: Record<string, string | number> = { lock_version: event.lock_version };
      if (transition === "cancel") body.cancellation_reason = cancelReason.trim();
      const response = await fetch(API_BASE_URL + "/clubs/" + clubId + "/events/" + event.id + "/" + transition, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: "Bearer " + token },
        body: JSON.stringify(body)
      });
      if (!response.ok) throw new Error(await eventApiError(response, "Lifecycle action failed."));
      setCancelReason("");
      setMessage("Lifecycle action confirmed by the backend: " + transition + ".");
      setRefreshKey((key) => key + 1);
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Lifecycle action failed.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <section className="stack">
      <Toolbar title={title} actionLabel={canManageEvents ? "Verified backend management" : "Verified backend read-only"} />
      {selectedSection && <p className="breadcrumbText">Operations → Events & Meetings → {selectedSection}</p>}
      <div className="notice">{message}</div>
      {status === "loading" && <div className="notice memberHelperText">Loading real Events & Meetings records...</div>}
      {status === "unauthenticated" && <div className="notice">Please sign in again before viewing Events & Meetings.</div>}
      {status === "forbidden" && <div className="notice">This role is not authorized for the selected Events & Meetings view.</div>}
      {status === "missing-club" && <div className="notice">No active club context is available.</div>}
      {status === "error" && <button className="secondaryButton" onClick={() => setRefreshKey((key) => key + 1)}>Retry Events API</button>}

      {canManageEvents && (
        <form className="compactPanel" onSubmit={submitForm}>
          <Toolbar title={editingId ? "Edit Event / Meeting" : "Create Draft Event / Meeting"} actionLabel="Backend write API" />
          <div className="formGrid">
            <label>Type<select value={form.type} onChange={(event) => setForm({ ...form, type: event.target.value as "event" | "meeting" })}><option value="event">Event</option><option value="meeting">Meeting</option></select></label>
            <label>Visibility<select value={form.visibility} onChange={(event) => setForm({ ...form, visibility: event.target.value as "members" | "management" })}><option value="members">Members</option><option value="management">Management</option></select></label>
            <label>Title<input value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} required /></label>
            <label>Venue<input value={form.venue} onChange={(event) => setForm({ ...form, venue: event.target.value })} /></label>
            <label>Description<textarea value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} /></label>
            <label><input type="checkbox" checked={form.is_all_day} onChange={(event) => setForm({ ...form, is_all_day: event.target.checked })} /> All-day record</label>
            {form.is_all_day ? (
              <>
                <label>Start date<input type="date" value={form.start_date} onChange={(event) => setForm({ ...form, start_date: event.target.value })} required /></label>
                <label>End date<input type="date" value={form.end_date} onChange={(event) => setForm({ ...form, end_date: event.target.value })} /></label>
              </>
            ) : (
              <>
                <label>Start time<input type="datetime-local" value={form.start_at} onChange={(event) => setForm({ ...form, start_at: event.target.value })} required /></label>
                <label>End time<input type="datetime-local" value={form.end_at} onChange={(event) => setForm({ ...form, end_at: event.target.value })} /></label>
              </>
            )}
          </div>
          <div className="buttonRow">
            <button disabled={submitting}>{editingId ? "Save backend update" : "Create backend Draft"}</button>
            {editingId && <button type="button" className="secondaryButton" onClick={beginCreate}>Cancel edit</button>}
          </div>
        </form>
      )}

      {status === "empty" && <div className="compactPanel"><div className="memberEmptyState">No real Events & Meetings records are available for this view.</div></div>}
      {sections.map((section) => {
        const sectionItems = sectionedEvents.filter((event) => sectionForEvent(event) === section);
        return (
          <div className="compactPanel" key={section}>
            <h2>{section}</h2>
            <div className={role === "member" ? "memberEventList" : "tableLike"}>
              {sectionItems.map((event) => (
                <article className={role === "member" ? "memberEventCard" : "adminEventCard"} key={event.id}>
                  <div>
                    <h3>{event.title}</h3>
                    <p>{formatEventSchedule(event)}</p>
                    <span>Venue: {event.venue || "Not specified"}</span>
                    <span>Type: {event.type}</span>
                    {canManageEvents && <span>Visibility: {event.visibility}</span>}
                  </div>
                  <span className="tag">{event.status}</span>
                  <button className="filterButton" onClick={() => onSelectEventMeeting?.(event)}>View details</button>
                  {canManageEvents && (
                    <div className="buttonRow">
                      <button className="secondaryButton" onClick={() => beginEdit(event)} disabled={submitting || event.status === "completed" || event.status === "cancelled"}>Edit</button>
                      {event.status === "draft" && <button onClick={() => runTransition(event, "publish")} disabled={submitting}>Publish</button>}
                      {event.status === "published" && <button onClick={() => runTransition(event, "complete")} disabled={submitting}>Complete</button>}
                      {(event.status === "draft" || event.status === "published") && <button className="secondaryButton" onClick={() => runTransition(event, "cancel")} disabled={submitting}>Cancel</button>}
                    </div>
                  )}
                </article>
              ))}
              {sectionItems.length === 0 && status !== "loading" && <div className="memberEmptyState">No real records in this section.</div>}
            </div>
          </div>
        );
      })}
      {canManageEvents && <label className="moduleSelect">Cancellation reason<input value={cancelReason} onChange={(event) => setCancelReason(event.target.value)} placeholder="Required before cancelling" /></label>}
    </section>
  );
}

function defaultEventMeetingForm(): EventMeetingFormState {
  return {
    type: "meeting",
    title: "",
    description: "",
    venue: "",
    visibility: "members",
    is_all_day: false,
    start_at: "",
    end_at: "",
    start_date: "",
    end_date: ""
  };
}

function formFromEventMeeting(event: BackendEventMeeting): EventMeetingFormState {
  return {
    id: event.id,
    type: event.type,
    title: event.title,
    description: event.description || "",
    venue: event.venue || "",
    visibility: event.visibility || "members",
    is_all_day: event.is_all_day,
    start_at: event.starts_at ? toDateTimeLocal(event.starts_at) : "",
    end_at: event.ends_at ? toDateTimeLocal(event.ends_at) : "",
    start_date: event.start_date || "",
    end_date: event.end_date || "",
    lock_version: event.lock_version
  };
}

function eventMeetingPayload(form: EventMeetingFormState, isEditing: boolean) {
  const payload: Record<string, string | boolean | number | undefined> = {
    title: form.title,
    description: form.description || undefined,
    venue: form.venue || undefined,
    visibility: form.visibility,
    is_all_day: form.is_all_day
  };
  if (!isEditing) payload.type = form.type;
  if (isEditing) payload.lock_version = form.lock_version;
  if (form.is_all_day) {
    payload.start_date = form.start_date;
    if (form.end_date) payload.end_date = form.end_date;
  } else {
    payload.start_at = toOffsetDateTime(form.start_at);
    if (form.end_at) payload.end_at = toOffsetDateTime(form.end_at);
  }
  return payload;
}

function eventMeetingQuery(section: EventMeetingSection | null | undefined, management: boolean) {
  const params = new URLSearchParams();
  if (section === "Past Events") params.set("scope", "past");
  else if (section === "Meeting Notices" && management) params.set("scope", "all");
  else params.set("scope", "upcoming");
  if (section === "Upcoming Events") params.set("type", "event");
  if (section === "Upcoming Meetings" || section === "Meeting Notices") params.set("type", "meeting");
  params.set("per_page", "50");
  const query = params.toString();
  return query ? "?" + query : "";
}

function formatEventSchedule(event: BackendEventMeeting) {
  if (event.is_all_day) return `${event.start_date || "Date unavailable"}${event.end_date && event.end_date !== event.start_date ? " → " + event.end_date : ""} · all day`;
  const timezone = event.timezone || "Asia/Kolkata";
  const start = event.starts_at ? new Intl.DateTimeFormat("en-IN", { dateStyle: "medium", timeStyle: "short", timeZone: timezone }).format(new Date(event.starts_at)) : "Start unavailable";
  const end = event.ends_at ? new Intl.DateTimeFormat("en-IN", { timeStyle: "short", timeZone: timezone }).format(new Date(event.ends_at)) : "";
  return end ? `${start} → ${end}` : start;
}

function toDateTimeLocal(value: string) {
  const date = new Date(value);
  const pad = (part: number) => String(part).padStart(2, "0");
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function toOffsetDateTime(value: string) {
  if (!value) return value;
  const date = new Date(value);
  return date.toISOString();
}

async function eventApiError(response: Response, fallback: string) {
  try {
    const payload = await response.json();
    const errors = payload.errors ? Object.values(payload.errors).flat().join(" ") : "";
    return errors || payload.message || fallback;
  } catch {
    return response.status === 401 ? "Please sign in again." : response.status === 403 ? "This role is not authorized for that Events & Meetings action." : response.status === 409 ? "This Events & Meetings record changed or is not in a valid lifecycle state. Refresh and try again." : fallback;
  }
}
function Announcements(_props: { role: Role; member: Member; selectedSponsors: string[] }) {
  return <AnnouncementsBackend />;
}

function DocumentAdministration({ onEdit }: { onEdit: (documentId: string) => void }) {
  return (
    <section className="stack" style={{ minWidth: 0 }}>
      <div className="compactPanel">
        <div className="toolbar" style={{ gap: "12px", flexWrap: "wrap" }}>
          <div><p className="eyebrow">DOCUMENT ADMINISTRATION</p><h2>Club Document Management</h2></div>
          <span className="statusPill">Authorized metadata workflow</span>
        </div>
        <div className="notice memberHelperText">Club document records currently contain metadata only. No club-document update API or shared file storage is connected.</div>
      </div>
      <div className="tableLike">
        {documents.map((item) => (
          <div className="staticRow documentAdminRow" key={item.id}>
            <strong>{item.title}</strong>
            <span>{item.category}</span>
            <span>{item.date}</span>
            <span className="tag">{item.visibility}</span>
            <button className="filterButton" onClick={() => onEdit(item.id)}>Edit</button>
          </div>
        ))}
      </div>
    </section>
  );
}

function PersonalDocumentDetail({ document, onBack }: { document: ClubDocumentRecord | null; onBack: () => void }) {
  return (
    <section className="stack" style={{ minWidth: 0 }}>
      <div className="compactPanel">
        <button className="secondaryButton compactBackButton" onClick={onBack}>← My Documents</button>
        <Toolbar title="Document Details" actionLabel="Member document access" />
      </div>
      {!document ? (
        <div className="notice memberHelperText">The selected document is unavailable or is not authorized for this role.</div>
      ) : (
        <div className="memberPanel">
          <div className="toolbar" style={{ gap: "8px", flexWrap: "wrap" }}><h3>{document.title}</h3><span className="statusPill">{document.visibility}</span></div>
          <div className="fieldGrid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(180px, 1fr))" }}>
            <Field label="Stable document ID" value={document.id} />
            <Field label="Category" value={document.category} />
            <Field label="Document date" value={document.date} />
            <Field label="Visibility" value={document.visibility} />
          </div>
          <div className="notice memberHelperText">Document content is not connected in this prototype.</div>
        </div>
      )}
    </section>
  );
}

function DocumentAdministrationEdit({
  document,
  draft,
  setDraft,
  onCancel
}: {
  document: ClubDocumentRecord | null;
  draft: DocumentEditDraft | null;
  setDraft: (draft: DocumentEditDraft) => void;
  onCancel: () => void;
}) {
  if (!document || !draft) {
    return <section className="compactPanel"><p className="eyebrow">DOCUMENT ADMINISTRATION</p><h2>Edit unavailable</h2><div className="notice">No stable club document record is selected.</div><button className="secondaryButton" onClick={onCancel}>Back to Document Administration</button></section>;
  }
  return (
    <section className="stack" style={{ minWidth: 0 }}>
      <div className="compactPanel">
        <p className="eyebrow">DOCUMENT ADMINISTRATION</p>
        <h2>Edit Club Document Metadata</h2>
        <div className="notice memberHelperText">Editing stable record <strong>{document.id}</strong>. The form is prepopulated from this exact document.</div>
      </div>
      <div className="memberPanel">
        <div className="fieldGrid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))" }}>
          <label>Title<input value={draft.title} onChange={(event) => setDraft({ ...draft, title: event.target.value })} /></label>
          <label>Category<input value={draft.category} onChange={(event) => setDraft({ ...draft, category: event.target.value })} /></label>
          <label>Date<input value={draft.date} onChange={(event) => setDraft({ ...draft, date: event.target.value })} /></label>
          <label>Visibility<select value={draft.visibility} onChange={(event) => setDraft({ ...draft, visibility: event.target.value as ClubDocumentRecord["visibility"] })}><option>Members</option><option>Board</option><option>Treasurer</option><option>Committee</option></select></label>
        </div>
        <div className="notice memberHelperText"><strong>Save is unavailable.</strong> No authorized club-document update API or shared backend record exists. No changes have been applied.</div>
        <div className="buttonRow"><button disabled title="No club-document update API is connected">Save unavailable</button><button className="secondaryButton" onClick={onCancel}>Cancel Without Changes</button></div>
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

const emptyClubProjectForm: ClubProjectForm = {
  record_type: "project", title: "", description: "", objective: "", service_category: "",
  visibility: "members", planned_start_date: "", planned_end_date: "", location: "",
  expected_beneficiary_count: "", actual_start_date: "", actual_end_date: "",
  actual_beneficiary_count: "", impact_summary: ""
};

function clubProjectForm(project: ClubProjectRecord): ClubProjectForm {
  return {
    record_type: project.record_type,
    title: project.title,
    description: project.description ?? "",
    objective: project.objective ?? "",
    service_category: project.service_category ?? "",
    visibility: project.visibility ?? "members",
    planned_start_date: project.planned_start_date ?? "",
    planned_end_date: project.planned_end_date ?? "",
    location: project.location ?? "",
    expected_beneficiary_count: project.expected_beneficiary_count?.toString() ?? "",
    actual_start_date: project.actual_start_date ?? "",
    actual_end_date: project.actual_end_date ?? "",
    actual_beneficiary_count: project.actual_beneficiary_count?.toString() ?? "",
    impact_summary: project.impact_summary ?? ""
  };
}

function ProjectsActivities({ role, setScreen }: { role: Role; setScreen: (screen: Screen) => void }) {
  const { session } = useDevAuth();
  const canManage = role === "president" || role === "secretary";
  const [token, setToken] = useState<string | null>(null);
  const [clubId, setClubId] = useState<number | null>(null);
  const [records, setRecords] = useState<ClubProjectRecord[]>([]);
  const [meta, setMeta] = useState<ClubProjectsMeta | null>(null);
  const [selected, setSelected] = useState<ClubProjectRecord | null>(null);
  const [view, setView] = useState<"list" | "detail" | "create" | "edit">("list");
  const [state, setState] = useState<"loading" | "ready" | "empty" | "submitting" | "error" | "forbidden" | "conflict">("loading");
  const [message, setMessage] = useState("Loading Projects and Activities from the backend...");
  const [query, setQuery] = useState("");
  const [recordType, setRecordType] = useState<"" | "project" | "activity">("");
  const [scope, setScope] = useState<"all" | "planned" | "active" | "completed" | "cancelled">("all");
  const [form, setForm] = useState<ClubProjectForm>(emptyClubProjectForm);

  async function apiMessage(response: Response, fallback: string) {
    try {
      const payload = await response.json() as { message?: string; errors?: Record<string, string[]> };
      return (payload.errors && Object.values(payload.errors)[0]?.[0]) || payload.message || fallback;
    } catch { return fallback; }
  }

  function fail(statusCode: number, text: string) {
    setState(statusCode === 403 ? "forbidden" : statusCode === 409 ? "conflict" : "error");
    setMessage(text);
  }

  async function safeProjectFetch(url: string, options: RequestInit, failureMessage: string): Promise<Response | null> {
    try {
      return await fetch(url, options);
    } catch {
      setState("error"); setMessage(failureMessage);
      return null;
    }
  }

  async function loadList(accessToken: string, activeClubId: number, page = 1, filters?: { q?: string; type?: string; scope?: string }) {
    setState("loading");
    setRecords([]); setMeta(null);
    setMessage("Loading Projects and Activities from the backend...");
    const params = new URLSearchParams({ per_page: "10", page: String(page), direction: "asc" });
    const q = filters?.q ?? query;
    const type = filters?.type ?? recordType;
    const selectedScope = filters?.scope ?? scope;
    if (q.trim()) params.set("q", q.trim());
    if (type) params.set("record_type", type);
    if (selectedScope !== "all") params.set("scope", selectedScope);
    let response: Response;
    try {
      response = await fetch(`${API_BASE_URL}/clubs/${activeClubId}/projects?${params}`, {
        headers: { Accept: "application/json", Authorization: `Bearer ${accessToken}` }
      });
    } catch {
      setState("error"); setMessage("Projects and Activities are unavailable.");
      return;
    }
    if (!response.ok) {
      setRecords([]); setMeta(null);
      fail(response.status, await apiMessage(response, "Projects and Activities are unavailable."));
      return;
    }
    const payload = await response.json() as { data?: ClubProjectRecord[]; meta?: ClubProjectsMeta };
    const loaded = payload.data ?? [];
    setRecords(loaded); setMeta(payload.meta ?? null);
    setState(loaded.length ? "ready" : "empty");
    setMessage(loaded.length ? "Backend records loaded." : "No Projects or Activities match the current filters.");
  }

  useEffect(() => {
    let cancelled = false;
    async function initialize() {
      await Promise.resolve();
      if (cancelled) return;
      setToken(null); setClubId(null); setRecords([]); setSelected(null); setView("list");
      setForm(emptyClubProjectForm); setState("loading");
      setMessage("Loading role-authorized Projects and Activities...");
      try {
        if (!session) throw new Error("Projects session missing.");
        const accessToken = session.token;
        const activeClubId = session.membership.club.id;
        if (typeof activeClubId !== "number") throw new Error("Active club context is unavailable.");
        if (!cancelled) { setToken(accessToken); setClubId(activeClubId); await loadList(accessToken, activeClubId, 1); }
      } catch (error) {
        if (!cancelled) { setState("error"); setMessage(error instanceof Error ? error.message : "Projects and Activities are unavailable."); }
      }
    }
    void initialize();
    return () => { cancelled = true; };
  }, [role, session]);

  async function openRecord(id: number) {
    if (!token || !clubId) return;
    setState("loading"); setMessage("Loading the selected stable backend record...");
    const response = await safeProjectFetch(`${API_BASE_URL}/clubs/${clubId}/projects/${id}`, { headers: { Accept: "application/json", Authorization: `Bearer ${token}` } }, "The selected record is unavailable.");
    if (!response) { setSelected(null); setView("list"); return; }
    if (!response.ok) { setSelected(null); setView("list"); fail(response.status, await apiMessage(response, "The selected record is unavailable.")); return; }
    const payload = await response.json() as { data: ClubProjectRecord };
    setSelected(payload.data); setView("detail"); setState("ready"); setMessage(`Loaded backend record #${payload.data.id}.`);
  }

  function payload(includeOperational = false) {
    const result: Record<string, string | number | null> = {
      title: form.title.trim(), description: form.description.trim() || null, objective: form.objective.trim() || null,
      service_category: form.service_category.trim() || null, visibility: form.visibility,
      planned_start_date: form.planned_start_date || null, planned_end_date: form.planned_end_date || null,
      location: form.location.trim() || null,
      expected_beneficiary_count: form.expected_beneficiary_count === "" ? null : Number(form.expected_beneficiary_count)
    };
    if (includeOperational) {
      result.actual_start_date = form.actual_start_date || null; result.actual_end_date = form.actual_end_date || null;
      result.actual_beneficiary_count = form.actual_beneficiary_count === "" ? null : Number(form.actual_beneficiary_count);
      result.impact_summary = form.impact_summary.trim() || null;
    }
    return result;
  }

  async function save() {
    if (!token || !clubId || !canManage || !form.title.trim()) return;
    const editing = view === "edit" && selected;
    setState("submitting"); setMessage(editing ? "Saving the exact backend record..." : "Creating a Draft in the backend...");
    const response = await safeProjectFetch(editing ? `${API_BASE_URL}/clubs/${clubId}/projects/${selected.id}` : `${API_BASE_URL}/clubs/${clubId}/projects`, {
      method: editing ? "PATCH" : "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
      body: JSON.stringify(editing ? { ...payload(selected.status === "active"), lock_version: selected.lock_version } : { ...payload(), record_type: form.record_type })
    }, "The backend did not save this record.");
    if (!response) return;
    if (!response.ok) { fail(response.status, await apiMessage(response, "The backend did not save this record.")); return; }
    const body = await response.json() as { data: ClubProjectRecord };
    await loadList(token, clubId, 1);
    setSelected(body.data); setView("detail"); setState("ready"); setMessage(`Backend confirmed record #${body.data.id}.`);
  }

  async function transition(action: "plan" | "start" | "complete" | "cancel") {
    if (!token || !clubId || !selected || !canManage || selected.lock_version === undefined) return;
    const safeDate = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
    const body: Record<string, string | number> = { lock_version: selected.lock_version };
    if (action === "start") body.actual_start_date = selected.planned_start_date ?? safeDate;
    if (action === "complete") {
      body.actual_start_date = selected.actual_start_date ?? selected.planned_start_date ?? safeDate;
      body.actual_end_date = safeDate;
      if (selected.actual_beneficiary_count != null) body.actual_beneficiary_count = selected.actual_beneficiary_count;
      if (selected.impact_summary) body.impact_summary = selected.impact_summary;
    }
    if (action === "cancel") body.cancellation_reason = "Cancelled through the verified Projects and Activities frontend.";
    setState("submitting"); setMessage(`Submitting ${action} to the backend...`);
    const response = await safeProjectFetch(`${API_BASE_URL}/clubs/${clubId}/projects/${selected.id}/${action}`, {
      method: "POST", headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` }, body: JSON.stringify(body)
    }, `The backend did not complete the ${action} transition.`);
    if (!response) return;
    if (!response.ok) { fail(response.status, await apiMessage(response, `The backend rejected the ${action} transition.`)); return; }
    const result = await response.json() as { data: ClubProjectRecord };
    await loadList(token, clubId, meta?.current_page ?? 1);
    setSelected(result.data); setView("detail"); setState("ready"); setMessage(`${action} confirmed by the backend for record #${result.data.id}.`);
  }

  const notice = <div className="notice memberHelperText"><strong>{state === "forbidden" ? "Forbidden: " : state === "conflict" ? "Conflict: " : ""}</strong>{message}</div>;

  if (view === "create" || view === "edit") {
    const activeEdit = view === "edit" && selected?.status === "active";
    return <section className="stack" style={{ minWidth: 0 }}><Toolbar title={view === "create" ? "Create Project / Activity Draft" : `Edit Record #${selected?.id}`} actionLabel="Backend workflow" />{notice}<div className="formPanel">
      <div className="fieldGrid" style={{ gridTemplateColumns: "repeat(auto-fit, minmax(210px, 1fr))" }}>
        <label>Record type<select value={form.record_type} disabled={view === "edit"} onChange={e => setForm({ ...form, record_type: e.target.value as ClubProjectForm["record_type"] })}><option value="project">Project</option><option value="activity">Activity</option></select></label>
        <label>Title<input value={form.title} disabled={activeEdit} onChange={e => setForm({ ...form, title: e.target.value })} /></label>
        {!activeEdit && <label>Visibility<select value={form.visibility} onChange={e => setForm({ ...form, visibility: e.target.value as ClubProjectForm["visibility"] })}><option value="members">Members</option><option value="management">Management</option></select></label>}
        {!activeEdit && <label>Category<input value={form.service_category} onChange={e => setForm({ ...form, service_category: e.target.value })} /></label>}
        {!activeEdit && <label>Planned start<input type="date" value={form.planned_start_date} onChange={e => setForm({ ...form, planned_start_date: e.target.value })} /></label>}
        {!activeEdit && <label>Planned end<input type="date" value={form.planned_end_date} onChange={e => setForm({ ...form, planned_end_date: e.target.value })} /></label>}
        <label>Location<input value={form.location} onChange={e => setForm({ ...form, location: e.target.value })} /></label>
        {!activeEdit && <label>Expected beneficiaries<input type="number" min="0" value={form.expected_beneficiary_count} onChange={e => setForm({ ...form, expected_beneficiary_count: e.target.value })} /></label>}
        {activeEdit && <label>Actual beneficiaries<input type="number" min="0" value={form.actual_beneficiary_count} onChange={e => setForm({ ...form, actual_beneficiary_count: e.target.value })} /></label>}
      </div>
      <label>Description<textarea value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} /></label>
      {!activeEdit && <label>Objective<textarea value={form.objective} onChange={e => setForm({ ...form, objective: e.target.value })} /></label>}
      {activeEdit && <label>Impact summary<textarea value={form.impact_summary} onChange={e => setForm({ ...form, impact_summary: e.target.value })} /></label>}
      <div className="buttonRow"><button onClick={() => void save()} disabled={state === "submitting" || !form.title.trim()}>Save to Backend</button><button className="secondaryButton" onClick={() => { setView(selected ? "detail" : "list"); setState("ready"); setMessage("No unsaved changes were applied."); }}>Cancel Without Saving</button></div>
    </div></section>;
  }

  if (view === "detail" && selected) return <section className="stack" style={{ minWidth: 0 }}><Toolbar title={selected.title} actionLabel={`${selected.record_type} #${selected.id}`} />{notice}
    <article className="activityCard" style={{ overflowWrap: "anywhere" }}><div className="toolbar"><h2>{selected.record_type === "project" ? "Project" : "Activity"} details</h2><span className="tag">{selected.status}</span></div><div className="fieldGrid">
      <Field label="Stable backend ID" value={String(selected.id)} /><Field label="Visibility" value={selected.visibility ?? "members"} /><Field label="Category" value={selected.service_category ?? "Not recorded"} />
      <Field label="Planned dates" value={`${selected.planned_start_date ?? "Not set"} — ${selected.planned_end_date ?? "Not set"}`} /><Field label="Actual dates" value={`${selected.actual_start_date ?? "Not set"} — ${selected.actual_end_date ?? "Not set"}`} />
      <Field label="Location" value={selected.location ?? "Not recorded"} /><Field label="Lead member" value={selected.lead_member?.full_name ?? "Not assigned"} /><Field label="Beneficiaries" value={String(selected.actual_beneficiary_count ?? selected.expected_beneficiary_count ?? "Not recorded")} />
    </div>{selected.description && <p>{selected.description}</p>}{selected.objective && <p><strong>Objective:</strong> {selected.objective}</p>}{selected.impact_summary && <p><strong>Impact:</strong> {selected.impact_summary}</p>}{canManage && selected.cancellation_reason && <p><strong>Cancellation reason:</strong> {selected.cancellation_reason}</p>}</article>
    {canManage && <div className="buttonRow">{["draft", "planned", "active"].includes(selected.status) && <button className="secondaryButton" onClick={() => { setForm(clubProjectForm(selected)); setView("edit"); }}>Edit Exact Record</button>}{selected.status === "draft" && <button onClick={() => void transition("plan")}>Plan</button>}{selected.status === "planned" && <button onClick={() => void transition("start")}>Start</button>}{["planned", "active"].includes(selected.status) && <button onClick={() => void transition("complete")}>Complete</button>}{["draft", "planned", "active"].includes(selected.status) && <button className="secondaryButton" onClick={() => void transition("cancel")}>Cancel Record</button>}</div>}
    <button className="secondaryButton" onClick={() => { setSelected(null); setView("list"); setState(records.length ? "ready" : "empty"); setMessage("Returned to the current backend list."); }}>← Back to Projects / Activities</button>
  </section>;

  return <section className="stack" style={{ minWidth: 0 }}><Toolbar title="Projects / Activities" actionLabel={canManage ? "Backend management" : "Backend read-only"} /><div className="notice">This screen uses club-scoped backend records only. It has no dummy fallback or local-only save.</div>{notice}
    <div className="filterBar" style={{ alignItems: "end" }}><label>Search<input value={query} onChange={e => setQuery(e.target.value)} placeholder="Title, objective, location" /></label><label>Type<select value={recordType} onChange={e => setRecordType(e.target.value as typeof recordType)}><option value="">All types</option><option value="project">Projects</option><option value="activity">Activities</option></select></label><label>Status<select value={scope} onChange={e => setScope(e.target.value as typeof scope)}><option value="all">All visible</option><option value="planned">Planned</option><option value="active">Active</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></label><button className="filterButton" onClick={() => token && clubId && void loadList(token, clubId, 1)} disabled={!token || state === "loading"}>Apply Filters</button><button className="filterButton" onClick={() => { setQuery(""); setRecordType(""); setScope("all"); if (token && clubId) void loadList(token, clubId, 1, { q: "", type: "", scope: "all" }); }} disabled={!token}>Clear</button>{canManage && <button onClick={() => { setSelected(null); setForm(emptyClubProjectForm); setView("create"); setState("ready"); setMessage("New records are created as Draft only."); }}>Create Draft</button>}</div>
    {records.length > 0 && <div className="activityGrid">{records.map(project => <article className="activityCard" key={project.id} style={{ minWidth: 0, overflowWrap: "anywhere" }}><div className="toolbar"><h2>{project.title}</h2><span className="tag">{project.status}</span></div><div className="fieldGrid"><Field label="Type" value={project.record_type === "project" ? "Project" : "Activity"} /><Field label="Stable ID" value={String(project.id)} /><Field label="Start" value={project.actual_start_date ?? project.planned_start_date ?? "Not set"} /><Field label="Location" value={project.location ?? "Not recorded"} /></div><button className="filterButton" onClick={() => void openRecord(project.id)}>View Record #{project.id}</button></article>)}</div>}
    {meta && meta.last_page > 1 && <div className="buttonRow"><button className="secondaryButton" disabled={meta.current_page <= 1 || !token || !clubId} onClick={() => token && clubId && void loadList(token, clubId, meta.current_page - 1)}>Previous</button><span>Page {meta.current_page} of {meta.last_page} · {meta.total} records</span><button className="secondaryButton" disabled={meta.current_page >= meta.last_page || !token || !clubId} onClick={() => token && clubId && void loadList(token, clubId, meta.current_page + 1)}>Next</button></div>}
    {role !== "member" && <button className="secondaryButton" onClick={() => setScreen("reports-tools-module")}>Open Reports & Tools</button>}
  </section>;
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

const celebrationMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

function celebrationDayMonth(value: string) {
  const [, month, day] = value.split("-");
  return `${Number(day)} ${celebrationMonths[Number(month) - 1]}`;
}

function Celebrations({ role, setScreen }: { role: Role; setScreen: (screen: Screen) => void }) {
  const [period, setPeriod] = useState<{ month: number; year: number } | null>(null);
  useEffect(() => {
    const currentDate = new Date();
    void Promise.resolve().then(() => setPeriod({ month: currentDate.getMonth() + 1, year: currentDate.getFullYear() }));
  }, []);
  const month = period?.month ?? 0;
  return (
    <section className="stack">
      <Toolbar title="Celebrations" actionLabel={period ? `${celebrationMonths[month - 1]} ${period.year}` : "Loading current period"} />
      <div className="notice memberHelperText">Greeting reminders are unavailable until a verified reminder workflow is connected.</div>
      <label className="moduleSelect">Celebration month
        <select value={month || ""} disabled={!period} onChange={(event) => setPeriod((current) => current ? { ...current, month: Number(event.target.value) } : current)}>
          {!period && <option value="">Loading current month</option>}
          {celebrationMonths.map((name, index) => <option key={name} value={index + 1}>{name}</option>)}
        </select>
      </label>
      <MemberCollectionState>{(currentMembers) => {
        const inMonth = (value?: string | null) => value ? Number(value.split("-")[1]) === month : false;
        const birthdays = currentMembers.filter((member) => inMonth(member.birthDate));
        const lionsAnniversaries = currentMembers.filter((member) => inMonth(member.joinedAt));
        const weddingAnniversaries = currentMembers.filter((member) => inMonth(member.weddingAnniversary));
        return <div className="stack">
          <section className="compactPanel"><Toolbar title="Birthdays" actionLabel={`${birthdays.length} recorded`} />
            {birthdays.length ? <div className="tableLike">{birthdays.map((member) => <div className="staticRow" key={`birthday-${member.id}`}><Avatar name={member.name} /><strong>{member.name}</strong><span>Birthday</span><span>{celebrationDayMonth(member.birthDate!)}</span></div>)}</div> : <div className="notice">No birthdays recorded for this period.</div>}
          </section>
          <section className="compactPanel"><Toolbar title="Lions Membership Anniversary" actionLabel={`${lionsAnniversaries.length} recorded`} />
            {lionsAnniversaries.length ? <div className="tableLike">{lionsAnniversaries.map((member) => <div className="staticRow" key={`lions-anniversary-${member.id}`}><Avatar name={member.name} /><strong>{member.name}</strong><span>Lions Membership Anniversary</span><span>{celebrationDayMonth(member.joinedAt!)}</span></div>)}</div> : <div className="notice">No Lions Membership Anniversaries recorded for this period.</div>}
          </section>
          <section className="compactPanel"><Toolbar title="Wedding Anniversary" actionLabel={`${weddingAnniversaries.length} recorded`} />
            {weddingAnniversaries.length ? <div className="tableLike">{weddingAnniversaries.map((member) => <div className="staticRow" key={`wedding-${member.id}`}><Avatar name={member.name} /><strong>{member.name}</strong><span>Wedding Anniversary</span><span>{celebrationDayMonth(member.weddingAnniversary!)}</span></div>)}</div> : <div className="notice">No wedding anniversaries recorded for this period.</div>}
          </section>
        </div>;
      }}</MemberCollectionState>
    </section>
  );
}
function PersonalMemberIdentityState({ children }: { children: (member: Member) => ReactNode }) {
  const { members: currentMembers, status, error, authenticatedUserId } = useCurrentMemberData();
  if (status === "loading") {
    return <section className="compactPanel"><div className="notice">Loading authenticated member identity...</div></section>;
  }
  if (status === "error" || authenticatedUserId === null) {
    return <section className="compactPanel"><div className="notice"><strong>Personal member information is unavailable.</strong>{error && <span> {error}</span>}</div></section>;
  }
  const matches = currentMembers.filter((member) => member.userId === authenticatedUserId && typeof member.backendId === "number");
  if (matches.length === 0) {
    return (
      <section className="compactPanel">
        <div className="notice">No genuine club member profile is linked to this development test account. Personal member information is unavailable.</div>
        <div className="notice memberHelperText">Personal member information can be shown only after a verified account-to-member link exists.</div>
      </section>
    );
  }
  if (matches.length > 1) {
    return <section className="compactPanel"><div className="notice"><strong>Personal member information is unavailable because the authenticated account has multiple genuine member links.</strong></div></section>;
  }
  return <>{children(matches[0])}</>;
}

function PersonalMemberProfile({ role }: { role: Role }) {
  return (
    <PersonalMemberIdentityState>
      {(member) => <PersonalProfileContext.Provider value><MemberProfile key={member.backendId} member={member} role={role} /></PersonalProfileContext.Provider>}
    </PersonalMemberIdentityState>
  );
}

function PersonalDigitalCard() {
  return (
    <PersonalMemberIdentityState>
      {(member) => <DigitalCard member={member} />}
    </PersonalMemberIdentityState>
  );
}

type OwnDonorProfile = { blood_group: string | null; blood_donor_consent: boolean };

function DonorConsent({ onOpenRequests }: { onOpenRequests: () => void }) {
  const { session, authenticatedFetch } = useDevAuth();
  const [profile, setProfile] = useState<OwnDonorProfile | null>(null);
  const [bloodGroup, setBloodGroup] = useState("");
  const [donorConsent, setDonorConsent] = useState(false);
  const [state, setState] = useState<"loading" | "ready" | "submitting" | "success" | "validation" | "conflict" | "forbidden" | "error">("loading");
  const [message, setMessage] = useState("Loading your authoritative donor-consent settings...");

  useEffect(() => {
    let cancelled = false;
    void Promise.resolve().then(async () => {
      if (cancelled) return;
      setProfile(null);
      setState("loading");
      setMessage("Loading your authoritative donor-consent settings...");
      if (!session?.membership.member || !session.membership.club.id) {
        setState("forbidden");
        setMessage("A unique authenticated user-to-member link is required. Blood donor consent settings are unavailable.");
        return;
      }
      const response = await authenticatedFetch(`/clubs/${session.membership.club.id}/members/me`);
      if (!response.ok) throw Object.assign(new Error(await donorConsentApiMessage(response, "Blood donor consent settings are unavailable.")), { status: response.status });
      const body = await response.json() as { member?: { id?: unknown; blood_group?: unknown; blood_donor_consent?: unknown } };
      if (!body.member || body.member.id !== session.membership.member?.id || (body.member.blood_group !== null && typeof body.member.blood_group !== "string") || typeof body.member.blood_donor_consent !== "boolean") {
        throw new Error("The authoritative own-member response is malformed or does not match the authenticated member link.");
      }
      if (!cancelled) {
        const loaded = { blood_group: body.member.blood_group, blood_donor_consent: body.member.blood_donor_consent };
        setProfile(loaded);
        setBloodGroup(loaded.blood_group ?? "");
        setDonorConsent(loaded.blood_donor_consent);
        setState("ready");
        setMessage("Current values loaded from your authoritative member profile.");
      }
    }).catch((error: Error & { status?: number }) => {
      if (!cancelled) {
        setProfile(null);
        setState(error.status === 403 ? "forbidden" : "error");
        setMessage(error.message || "Blood donor consent settings are unavailable.");
      }
    });
    return () => { cancelled = true; };
  }, [authenticatedFetch, session]);

  const canonicalBloodGroup = bloodGroup.trim().toUpperCase();
  const changedBloodGroup = profile !== null && canonicalBloodGroup !== (profile.blood_group ?? "");
  const changedConsent = profile !== null && donorConsent !== profile.blood_donor_consent;
  const hasChanges = changedBloodGroup || changedConsent;

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!profile || !session?.membership.club.id || !hasChanges || state === "submitting") {
      setState("validation");
      setMessage("Change the blood group or donor consent before submitting a reviewed request.");
      return;
    }
    const changes: Record<string, string | boolean> = {};
    if (changedBloodGroup) changes.blood_group = canonicalBloodGroup;
    if (changedConsent) changes.blood_donor_consent = donorConsent;
    setState("submitting");
    setMessage("Submitting a Pending profile-update request...");
    try {
      // authenticatedFetch supplies the canonical http://127.0.0.1:8000/api/v1 client base.
      const response = await authenticatedFetch(`/clubs/${session.membership.club.id}/profile-update-requests`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ changes })
      });
      if (!response.ok) {
        const errorMessage = await donorConsentApiMessage(response, "The donor-consent request was not submitted.");
        setState(response.status === 403 ? "forbidden" : response.status === 409 ? "conflict" : response.status === 422 ? "validation" : "error");
        setMessage(errorMessage);
        return;
      }
      setState("success");
      setMessage("Pending request submitted. Your current profile values remain authoritative until President or Secretary review and approval.");
    } catch {
      setState("error");
      setMessage("The donor-consent request could not be submitted. Your current profile values were not changed.");
    }
  }

  return (
    <section className="formPanel">
      <Toolbar title="Blood Donor Consent" actionLabel="Reviewed profile update" />
      <div className="notice memberHelperText">This screen uses your authenticated own-member record. A proposal creates a Pending profile-update request for President or Secretary review; it does not directly change your member profile.</div>
      {profile && <form onSubmit={submit}>
        <div className="fieldGrid"><Field label="Current blood group" value={profile.blood_group ?? "Not recorded"} /><Field label="Current donor consent" value={profile.blood_donor_consent ? "Yes" : "No"} /></div>
        <label>Proposed blood group<input aria-label="Proposed blood group" value={bloodGroup} onChange={event => setBloodGroup(event.target.value)} disabled={state === "submitting" || state === "success"} /></label>
        <label>Proposed consent to donate<select aria-label="Proposed consent to donate" value={donorConsent ? "yes" : "no"} onChange={event => setDonorConsent(event.target.value === "yes")} disabled={state === "submitting" || state === "success"}><option value="yes">Yes</option><option value="no">No</option></select></label>
        <div className="notice memberHelperText">Changing consent from Yes to No is a new reviewed profile-update request. Withdrawing an existing Pending profile-update request is a separate action available in My Profile Update Requests.</div>
        <div className="buttonRow"><button type="submit" disabled={!hasChanges || state === "submitting" || state === "success"}>Submit Pending Request</button><button type="button" className="secondaryButton" onClick={onOpenRequests}>View My Profile Update Requests</button></div>
      </form>}
      <div className="notice" role="status"><strong>{state === "conflict" ? "Pending request conflict: " : state === "forbidden" ? "Unavailable: " : ""}</strong>{message}</div>
    </section>
  );
}

async function donorConsentApiMessage(response: Response, fallback: string) {
  try {
    const body = await response.json() as { message?: string; errors?: Record<string, string[]> };
    return (body.errors && Object.values(body.errors)[0]?.[0]) || body.message || fallback;
  } catch {
    return fallback;
  }
}

function DonorSearch({ bloodGroup, setBloodGroup, allowedDonors }: { bloodGroup: string; setBloodGroup: (group: string) => void; allowedDonors: Member[] }) {
  const { status, error, retry } = useCurrentMemberData();
  if (status === "loading") return <div className="notice">Loading current members...</div>;
  if (status === "error") return <div className="notice"><strong>Current member data could not be loaded.</strong>{error && <span> {error}</span>} <button className="filterButton" onClick={retry}>Retry</button></div>;
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
  if (!verifiedOperationalSourceConnected("reports")) return <section className="stack"><Toolbar title="Reports" actionLabel="Not connected" /><div className="notice">Operational reports are not connected to a verified source yet.</div><div className="notice memberHelperText">Real reports and collection summaries will appear here only after a verified source is connected.</div></section>;

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
        <Toolbar title="Role Assignment" actionLabel="Frontend prototype" />
        <p className="prototypeNote">Leadership assignments are shown on Board &amp; Committees only when stable imported-member identity is verified. They are not yet stored in the backend.</p>
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

function CommitteeDetail({ committee, setScreen }: { committee: { id: string; name: string; lead: string; count: number; purpose: string } | null; setScreen: (screen: Screen) => void; }) {
  const [selectedMemberId, setSelectedMemberId] = useState("");
  if (!committee) return <section className="stack"><div className="compactPanel"><button className="secondaryButton" onClick={() => setScreen("nav-detail")}>← Back to List</button><div className="notice">No committee selected.</div></div></section>;
  return (
    <section className="stack">
      <div className="compactPanel"><button className="secondaryButton" onClick={() => setScreen("nav-detail")}>← Back to List</button><Toolbar title="Committee Details" actionLabel="Active Committee" /></div>
      <div className="memberPanel"><div className="fieldGrid">
        <Field label="Committee Name" value={committee.name} /><Field label="Lead / Chair" value="Legacy / unlinked" /><Field label="Roster positions" value={`${committee.count}`} /><Field label="Purpose" value={committee.purpose} />
      </div></div>
      <div className="memberPanel"><Toolbar title="Committee Member Selector" actionLabel="No automatic assignment" />
        <div className="notice">The existing dummy roster remains legacy/unlinked. Selecting a current member here does not assign an office.</div>
        <MemberCollectionState>{(currentMembers) => <label className="moduleSelect">Current member
          <select value={selectedMemberId} onChange={(event) => setSelectedMemberId(event.target.value)}><option value="">Select a current member</option>{currentMembers.map((member) => <option key={member.id} value={member.id}>{member.name} — {member.id}</option>)}</select>
        </label>}</MemberCollectionState>
      </div>
    </section>
  );
}
function EventMeetingDetail({
  item,
  setScreen
}: {
  item: BackendEventMeeting | null;
  setScreen: (screen: Screen) => void;
}) {
  if (!item) {
    return (
      <section className="stack">
        <div className="compactPanel">
          <button className="secondaryButton compactBackButton" onClick={() => setScreen("events-meetings")}>← Back to List</button>
          <div className="notice memberHelperText">No backend event or meeting record is selected.</div>
        </div>
      </section>
    );
  }

  return (
    <section className="stack">
      <div className="compactPanel">
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("events-meetings")}>← Events & Meetings</button>
        <Toolbar title="Event / Meeting Details" actionLabel="Verified backend record" />
      </div>
      <div className="memberPanel">
        <div className="toolbar"><h3>{item.type === "meeting" ? "Meeting" : "Event"} #{item.id}</h3><span className="statusPill">{item.status}</span></div>
        <div className="fieldGrid" style={{ gridTemplateColumns: "1fr 1fr" }}>
          <Field label="Stable backend ID" value={String(item.id)} />
          <Field label="Title" value={item.title} />
          <Field label="Schedule" value={formatEventSchedule(item)} />
          <Field label="Venue" value={item.venue || "Not specified"} />
          <Field label="Type" value={item.type} />
          <Field label="Status" value={item.status} />
          {item.visibility && <Field label="Visibility" value={item.visibility} />}
          {typeof item.lock_version === "number" && <Field label="Lock version" value={String(item.lock_version)} />}
        </div>
        {item.description && <div className="notice memberHelperText">{item.description}</div>}
        {item.cancellation_reason && <div className="notice">Cancellation reason: {item.cancellation_reason}</div>}
      </div>
    </section>
  );
}
function AnnouncementSingleDetail({
  item,
  setScreen
}: {
  item: { title: string; meta: string[]; sourceSection: AnnouncementDetail } | null;
  setScreen: (screen: Screen) => void;
}) {
  if (!item) {
    return (
      <section className="stack">
        <div className="compactPanel">
          <button className="secondaryButton compactBackButton" onClick={() => setScreen("announcement-detail")}>← Back to List</button>
          <div className="notice memberHelperText">No announcement selected.</div>
        </div>
      </section>
    );
  }

  const getMetaValue = (prefix: string) => {
    const found = item.meta.find(m => m.startsWith(prefix));
    return found ? found.replace(prefix, "").trim() : "N/A";
  };

  const audience = getMetaValue("Audience:");
  const status = getMetaValue("Status:");
  const type = getMetaValue("Type:");
  const owner = getMetaValue("Owner:");
  const priority = getMetaValue("Priority:");

  return (
    <section className="stack">
      <div className="compactPanel">
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("announcement-detail")}>
          ← {item.sourceSection}
        </button>
        <Toolbar title="Announcement Details" actionLabel="Operations / Announcements" />
      </div>

      <div className="memberPanel">
        <div className="toolbar">
          <h3>Announcement Information</h3>
          {status !== "N/A" && <span className="statusPill">{status}</span>}
        </div>
        <div className="detailGrid">
          <div className="fieldGrid" style={{ gridTemplateColumns: "1fr 1fr" }}>
            <div className="field">
              <span className="fieldLabel">Announcement Title</span>
              <strong>{item.title}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Audience</span>
              <strong>{audience}</strong>
            </div>
            {type !== "N/A" && (
              <div className="field">
                <span className="fieldLabel">Announcement Type</span>
                <strong>{type}</strong>
              </div>
            )}
            {owner !== "N/A" && (
              <div className="field">
                <span className="fieldLabel">Owner / Submitted By</span>
                <strong>{owner}</strong>
              </div>
            )}
            {priority !== "N/A" && (
              <div className="field">
                <span className="fieldLabel">Priority</span>
                <strong>{priority}</strong>
              </div>
            )}
            <div className="field" style={{ gridColumn: "span 2" }}>
              <span className="fieldLabel">Content Description</span>
              <p style={{ marginTop: "0.5rem", color: "var(--muted)", lineHeight: 1.5 }}>
                This is a prototype announcement view. Real announcement body and content attachment support will be configured during the database integration phase.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function ClubDocumentDetail({
  item,
  setScreen
}: {
  item: { title: string; meta: string[]; sourceTitle: string } | null;
  setScreen: (screen: Screen) => void;
}) {
  if (!item) {
    return (
      <section className="stack">
        <div className="compactPanel">
          <button className="secondaryButton compactBackButton" onClick={() => setScreen("nav-detail")}>← Back to List</button>
          <div className="notice memberHelperText">No document selected.</div>
        </div>
      </section>
    );
  }

  const getMetaValue = (prefix: string) => {
    const found = item.meta.find(m => m.startsWith(prefix));
    return found ? found.replace(prefix, "").trim() : "N/A";
  };

  const type = getMetaValue("Type:");
  const visibility = getMetaValue("Visibility:");
  const status = getMetaValue("Status:");

  return (
    <section className="stack">
      <div className="compactPanel">
        <button className="secondaryButton compactBackButton" onClick={() => setScreen("nav-detail")}>
          ← {item.sourceTitle}
        </button>
        <Toolbar title="Document Details" actionLabel="Operations / Documents" />
      </div>

      <div className="memberPanel">
        <div className="toolbar">
          <h3>Document Metadata</h3>
          {status !== "N/A" && <span className="statusPill">{status}</span>}
        </div>
        <div className="detailGrid">
          <div className="fieldGrid" style={{ gridTemplateColumns: "1fr 1fr" }}>
            <div className="field">
              <span className="fieldLabel">Document Title</span>
              <strong>{item.title}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Document Type</span>
              <strong>{type}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Visibility / Access</span>
              <strong>{visibility}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Status</span>
              <strong>{status}</strong>
            </div>
            <div className="field" style={{ gridColumn: "span 2" }}>
              <span className="fieldLabel">Document Preview Notice</span>
              <p style={{ marginTop: "0.5rem", color: "var(--muted)", lineHeight: 1.5 }}>
                This is a secure prototype document viewer. Real document downloads, PDF previews, and role-based storage permissions will be configured during the file system database integration phase.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

function ActivityDetail({
  item,
  onBack
}: {
  item: {
    name: string;
    date: string;
    categoryCommittee: string;
    peopleServed: string;
    reportStatus: string;
    sourceTitle: string;
  } | null;
  onBack: (sourceTitle: string) => void;
}) {
  if (!item) {
    return (
      <section className="stack">
        <div className="compactPanel">
          <button className="secondaryButton compactBackButton" onClick={() => onBack("This Month")}>← Back to List</button>
          <div className="notice memberHelperText">No activity selected.</div>
        </div>
      </section>
    );
  }

  return (
    <section className="stack">
      <div className="compactPanel">
        <button className="secondaryButton compactBackButton" onClick={() => onBack(item.sourceTitle)}>
          ← {item.sourceTitle}
        </button>
        <Toolbar title="Activity Details" actionLabel="Operations / Projects & Activities" />
      </div>

      <div className="memberPanel">
        <div className="toolbar">
          <h3>Activity Information</h3>
          <span className="statusPill">{item.reportStatus}</span>
        </div>
        <div className="detailGrid">
          <div className="fieldGrid" style={{ gridTemplateColumns: "1fr 1fr" }}>
            <div className="field">
              <span className="fieldLabel">Activity Name</span>
              <strong>{item.name}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Date</span>
              <strong>{item.date}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Category / Lead Committee</span>
              <strong>{item.categoryCommittee}</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">People Served</span>
              <strong>{item.peopleServed} Served</strong>
            </div>
            <div className="field">
              <span className="fieldLabel">Report Document Status</span>
              <strong>{item.reportStatus}</strong>
            </div>
            <div className="field" style={{ gridColumn: "span 2" }}>
              <span className="fieldLabel">Activity Description & Impact Note</span>
              <p style={{ marginTop: "0.5rem", color: "var(--muted)", lineHeight: 1.5 }}>
                This is a secure prototype activity preview page. Real activity reports, expense attachments, service metrics calculation, and photo uploads will be configured during the database integration phase.
              </p>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

