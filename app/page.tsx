"use client";

import { useMemo, useState } from "react";

type Role = "president" | "secretary" | "treasurer" | "member";
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
type FinanceSection = "Club Account Statement" | "Member Ledger / Dues" | "Payments / Receipts" | "Outstanding Dues" | "Collection Summary" | "Fellowship Payments";
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
    clubRole: "President/Admin",
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
  verifiedBy: "Lion Bijesh - President/Admin",
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
  { title: "PST review due", role: "President/Admin", due: "14 Jun 2026", priority: "High", status: "Open" },
  { title: "Close May activity reports", role: "Secretary", due: "07 Jun 2026", priority: "Medium", status: "Pending" },
  { title: "Verify fellowship collection", role: "Treasurer", due: "28 Jun 2026", priority: "High", status: "Open" }
];

const roleLabels: Record<Role, string> = {
  president: "President/Admin",
  secretary: "Secretary",
  treasurer: "Treasurer",
  member: "Normal Member"
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
      { label: "Today Celebration List", screen: "report-detail", reportSection: "Celebration Reports", reportDetail: "Today Celebration List" },
      { label: "Monthly Birthday Report", screen: "report-detail", reportSection: "Celebration Reports", reportDetail: "Monthly Birthday Report" },
      { label: "Monthly Anniversary Report", screen: "report-detail", reportSection: "Celebration Reports", reportDetail: "Monthly Anniversary Report" }
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

const peopleMenuItems: NavItem[] = [
  {
    label: "Members",
    children: [
      detailItem("People", "Active Members"),
      detailItem("People", "Inactive Members"),
      detailItem("People", "Profile Update Pending")
    ]
  },
  {
    label: "Board & Committees",
    children: [
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
      { label: "Voluntary Contribution / Collection Box", screen: "collection-box" },
      { label: "Receipt List", screen: "receipt-list" },
      { label: "Payment History", screen: "payment-history" }
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
    { title: "People", items: peopleMenuItems },
    { title: "Operations", items: operationsMenuItems },
    { title: "Reports & Tools", items: [
      ...reportMenuItems,
      { label: "Notifications & Reminders", screen: "notifications-reminders" },
      { label: "Authorized Donor Search", screen: "donor-search" }
    ] }
  ],
  treasurer: [
    { title: "Direct", direct: true, items: [{ label: "Dashboard", screen: "dashboard" }] },
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
      { label: "My Dues", screen: "ledger" },
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
  { label: "My Fellowship", short: "FE", screen: "fellowship" as Screen },
  { label: "Digital Card", short: "ID", screen: "digital-card" as Screen },
  { label: "Announcements", short: "AN", screen: "announcements" as Screen },
  { label: "Events", short: "EV", screen: "events-meetings" as Screen },
  { label: "Documents", short: "DO", screen: "documents" as Screen },
  { label: "Blood Donor", short: "BD", screen: "donor-consent" as Screen },
  { label: "Directory", short: "DR", screen: "members" as Screen }
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
        <Header role={roleLabels[role]} user={activeMember.dashboardName} screen={screen} />
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
          <NavDetailScreen parent={navDetail.parent} title={navDetail.title} setScreen={setScreen} />
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
        {screen === "receipt-list" && <ReceiptList />}
        {screen === "payment-history" && <PaymentHistory />}
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

function Header({ role, user, screen }: { role: string; user: string; screen: Screen }) {
  const title = role === "Normal Member" && screen === "ledger" ? "My Dues" : screenTitle(screen);
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
    "collection-box": "Voluntary Contribution / Collection Box",
    "receipt-list": "Receipt List",
    "payment-history": "Payment History",
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
          <button onClick={() => switchRole("president")}>Enter as President/Admin</button>
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
      {section === "Outstanding Dues" && <OutstandingDues setScreen={setScreen} />}
      {section === "Collection Summary" && <CollectionSummary detail={collectionDetail} setDetail={setCollectionDetail} />}
      {section === "Fellowship Payments" && <FellowshipPayments view={fellowshipPaymentView} setScreen={setScreen} />}
    </section>
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
  const [previewTitle, setPreviewTitle] = useState("Dues reminder preview");
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
        <div className="notice">Normal Members only receive reminders. They do not have access to this control screen.</div>
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
            <button className="clickableMetric" onClick={() => setSection("Dues Reminders")} disabled={role === "secretary"}>
              <span>Pending Due Reminders</span>
              <strong>12</strong>
              <small>{role === "secretary" ? "Treasurer access" : "Open dues reminders"}</small>
            </button>
            <button className="clickableMetric" onClick={() => setSection("Meeting Reminders")} disabled={role === "treasurer"}>
              <span>Meeting Reminders Scheduled</span>
              <strong>2</strong>
              <small>{role === "treasurer" ? "Secretary access" : "Open meeting reminders"}</small>
            </button>
            <button className="clickableMetric" onClick={() => setSection("Celebration Reminders")} disabled={role === "treasurer"}>
              <span>Celebrations Today</span>
              <strong>3</strong>
              <small>{role === "treasurer" ? "Secretary access" : "Open celebrations"}</small>
            </button>
            <button className="clickableMetric" onClick={() => setSection("Announcement Reminders")} disabled={role === "treasurer"}>
              <span>Announcements Pending</span>
              <strong>1</strong>
              <small>{role === "treasurer" ? "Secretary access" : "Open announcements"}</small>
            </button>
            <button className="clickableMetric" onClick={() => setSection("Reminder History")}>
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
          <div className="notice">Normal Members see only today's celebrations and recently completed celebrations. Secretary/Admin manage official greeting status here.</div>
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
    "Celebration Reports": ["Today celebration list", "Monthly birthday report", "Monthly anniversary report"],
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
    { receiptNo: "RCP-045", party: "Collection Box", type: "Voluntary Contribution / Collection Box", amount: money(2350), mode: "Cash", date: "02 Jun 2026", recordedBy: "Lion Treasurer User" },
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

function NavDetailScreen({ parent, title, setScreen }: { parent: string; title: string; setScreen: (screen: Screen) => void }) {
  if (parent === "Operations" && title === "Draft / Pending") {
    return <DraftPendingAnnouncements setScreen={setScreen} />;
  }

  const rows = navDetailCards(parent, title);
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
        { title: "Medical Camp Announcement", meta: ["Type: Event / Activity", "Owner: President/Admin", "Status: Pending Approval"] },
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

function navDetailCards(parent: string, title: string): { title: string; meta: string[]; action: string }[] {
  if (parent === "People" && ["Today", "This Week", "Birthdays", "Anniversaries"].includes(title)) {
    return peopleCelebrationCards(title);
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
      `Date: ${item.date}`,
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
      { title: "Member: Lion Bijesh", meta: ["Role: President/Admin", "Status: Active", `Report: ${detail}`, "Action: Review"] },
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
    return celebrationsForRole("president").slice(0, 4).map((item) => ({
      title: item.name,
      meta: [
        `Celebration Type: ${item.type}`,
        `Date: ${item.date}`,
        `Contact: ${item.contact}`,
        `Greeting Status: ${item.wishStatus}`,
        `Report: ${detail}`
      ]
    }));
  }
  if (section === "Blood Donor Consent Report") {
    return members.filter((member) => member.donorConsent).map((member) => ({
      title: `Donor: ${member.name}`,
      meta: [
        `Blood Group: ${member.bloodGroup}`,
        `Contact: ${member.consentContact ? member.phone : "Contact hidden"}`,
        "Consent Status: Consented",
        `Report: ${detail}`
      ]
    }));
  }
  if (section === "Document Reports") {
    return documents.map((item) => ({
      title: item.title,
      meta: [
        `Category: ${item.category}`,
        `Visibility: ${item.visibility}`,
        "Status: Published",
        `Report: ${detail}`
      ]
    }));
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
    { label: "Celebration reminders", value: "4 this week", detail: "celebration-reminders", action: "Review" },
    { label: "Important club reminders", value: "PST review due", detail: "club-reminders", action: "Review" }
  ];
  return (
    <section className="pstPanel">
      <div className="toolbar">
        <h2>PST Coordination Overview</h2>
        <span className="statusPill">President/Admin + Secretary + Treasurer</span>
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
        <div className="notice">This dashboard detail is not visible for Normal Member role in the final app flow.</div>
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
  return "President/Admin review detail";
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
    ["Confirmation status", "Pending President/Admin approval"],
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
        <Action title="Board 2026" body="President/Admin, Secretary, Treasurer and board members." onClick={() => undefined} />
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
    Overview: "Step 3 — Select sponsors",
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
            <Action title="Generate reminder draft" body="Prepare sponsor reminder message." onClick={() => undefined} />
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
      <Toolbar title="Member Ledger / Dues" actionLabel={role === "secretary" ? "Finance summary view" : "Finance workflow"} />
      <div className="tableLike">
        {visibleRows.map((row) => (
          <div key={`${row.date}-${row.member}-${row.type}`} className="staticRow ledgerRow">
            <span>{row.date}</span>
            <strong>{row.member}</strong>
            <span>{row.type}</span>
            <span>{row.debit ? money(row.debit) : "-"}</span>
            <span>{row.credit ? money(row.credit) : "-"}</span>
          </div>
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
      <div className="notice">Dummy data only. Real ledger, receipt, voucher, and payment posting will be implemented in backend phase.</div>
      <div className="actionGrid">
        <Action title="Record Member Payment" body="Clear member dues and preview a dummy receipt." onClick={() => setScreen("record-member-payment")} />
        <Action title="Voluntary Contribution / Collection Box" body="Record club income without changing member dues." onClick={() => setScreen("collection-box")} />
        <Action title="Receipt List" body="View dummy receipt rows." onClick={() => setScreen("receipt-list")} />
        <Action title="Payment History" body="View dummy payment history rows." onClick={() => setScreen("payment-history")} />
      </div>
    </section>
  );
}

function RecordMemberPayment({ role }: { role: Role }) {
  const canEdit = role === "president" || role === "treasurer";
  const selectedMember = members[3];
  const selectedDues = outstandingDues.filter((due) => due.member === selectedMember.name);
  const outstandingBefore = selectedDues.reduce((sum, due) => sum + due.amount, 0);
  const paymentAmount = selectedDues.filter((due) => due.selected).reduce((sum, due) => sum + due.amount, 0);
  const outstandingAfter = outstandingBefore - paymentAmount;
  const selectedDueItems = selectedDues.filter((due) => due.selected);

  return (
    <section className="stack">
      <Toolbar title="Record Member Payment" actionLabel="Member dues payment" />
      <div className="notice">Dummy data only. Real ledger, receipt, voucher, and payment posting will be implemented in backend phase.</div>
      {!canEdit && <div className="notice">Secretary and non-finance roles can view summaries, but payment editing is disabled by default.</div>}
      <div className="formPanel">
        <div className="fieldGrid">
          <label>Member<select defaultValue={selectedMember.id} disabled={!canEdit}>{members.map((member) => <option key={member.id} value={member.id}>{member.name}</option>)}</select></label>
          <label>Payment mode<select defaultValue="upi" disabled={!canEdit}><option value="cash">Cash</option><option value="bank">Bank transfer</option><option value="upi">UPI</option></select></label>
          <label>Reference number<input value="UPI-REF-20260603-001" readOnly /></label>
          <label>Payment amount<input value={money(paymentAmount)} readOnly /></label>
        </div>
      </div>
      <div className="compactPanel">
        <Toolbar title="Outstanding Dues" actionLabel="Compact dummy list" />
        <div className="financeCardList">
          {selectedDues.map((due) => (
            <article className="financeStackCard" key={due.id}>
              <h3>{due.selected ? `✓ ${due.label}` : due.label}</h3>
              <span>Amount: {money(due.amount)}</span>
              <span>Status: {due.selected ? "Selected due" : "Available"}</span>
            </article>
          ))}
        </div>
      </div>
      <div className="metricsGrid">
        <Metric label="Outstanding before payment" value={money(outstandingBefore)} />
        <Metric label="Selected due items" value={`${selectedDueItems.length} selected`} />
        <Metric label="Payment amount" value={money(paymentAmount)} />
        <Metric label="Outstanding after payment" value={money(outstandingAfter)} />
      </div>
      <ReceiptPreview
        title="Dummy Receipt Preview"
        rows={[
          ["Receipt No", "RCP-001"],
          ["Member", "Lion Ajith Kumar"],
          ["Amount", money(paymentAmount)],
          ["Mode", "UPI"],
          ["Allocated to", "Monthly dues - June: ₹500"],
          ["Allocated to", "Special Collection: ₹500"],
          ["Ledger Impact", "Credit member account ₹1,000"]
        ]}
      />
      <button disabled={!canEdit}>Create Payment + Credit Ledger Entry</button>
    </section>
  );
}

function CollectionBoxReceipt({ role }: { role: Role }) {
  const canEdit = role === "president" || role === "treasurer";

  return (
    <section className="stack">
      <Toolbar title="Voluntary Contribution / Collection Box" actionLabel="Club income receipt" />
      <div className="notice">Dummy data only. Real ledger, receipt, voucher, and payment posting will be implemented in backend phase.</div>
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
      <button disabled={!canEdit}>Record Collection Box Receipt</button>
    </section>
  );
}

function ReceiptList() {
  const receipts = [
    { receiptNo: "RCP-001", partyLabel: "Name", party: "Lion Ajith Kumar", type: "Member dues", amount: money(1000), mode: "UPI" },
    { receiptNo: voluntaryContribution.voucher, partyLabel: "Source", party: "Collection Box", type: "Voluntary Contribution / Collection Box", amount: money(voluntaryContribution.amount), mode: voluntaryContribution.mode },
    { receiptNo: "RCP-044", partyLabel: "Name", party: "Lion Bijesh", type: "Monthly dues", amount: money(500), mode: "Bank" }
  ];

  return (
    <section className="stack">
      <Toolbar title="Receipt List" actionLabel="Dummy receipts" />
      <div className="notice">Dummy data only. Real ledger, receipt, voucher, and payment posting will be implemented in backend phase.</div>
      <div className="financeCardList">
        {receipts.map((receipt) => (
          <article className="financeStackCard" key={receipt.receiptNo}>
            <h3>Receipt No: {receipt.receiptNo}</h3>
            <span>{receipt.partyLabel}: {receipt.party}</span>
            <span>Receipt Type: {receipt.type}</span>
            <span>Amount: {receipt.amount}</span>
            <span>Mode: {receipt.mode}</span>
          </article>
        ))}
      </div>
    </section>
  );
}

function PaymentHistory() {
  const rows = [
    { date: "03 Jun 2026", partyLabel: "Party", party: "Lion Ajith Kumar", type: "Member payment", amount: money(1000), impact: "Credit member account" },
    { date: "02 Jun 2026", partyLabel: "Source", party: "Collection Box", type: "Club income receipt", amount: money(voluntaryContribution.amount), impact: "Club income only" },
    { date: "01 Jun 2026", partyLabel: "Party", party: "Lion Bijesh", type: "Monthly dues", amount: money(500), impact: "Credit member account" }
  ];

  return (
    <section className="stack">
      <Toolbar title="Payment History" actionLabel="Dummy audit trail" />
      <div className="notice">Dummy data only. Real ledger, receipt, voucher, and payment posting will be implemented in backend phase.</div>
      <div className="financeCardList">
        {rows.map((row) => (
          <article className="financeStackCard" key={`${row.date}-${row.party}-${row.type}`}>
            <h3>Date: {row.date}</h3>
            <span>{row.partyLabel}: {row.party}</span>
            <span>Type: {row.type}</span>
            <span>Amount: {row.amount}</span>
            <span>Ledger Impact: {row.impact}</span>
          </article>
        ))}
      </div>
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
  const title = selectedSection ? selectedSection : "Events & Meetings";
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
        const sectionTitle = role === "member" && section === "Upcoming Meetings" ? "Upcoming Meetings & Events" : section;
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
              {sectionItems.length === 0 && <div className="memberEmptyState">{role === "member" || section === "Meeting Notices" ? emptyMessages[section] : "No items in this section."}</div>}
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
      <div className="notice">Authorized donor search is for President/Admin and Secretary in V1. Results include consented donors only.</div>
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
    ["President/Admin", "Full club-level visibility; user and role management; approval rights; fellowship and reports oversight; finance visibility."],
    ["Secretary", "Meetings/events, announcements, documents, projects/activities, reports view; no payment editing by default."],
    ["Treasurer", "Ledger, dues, payments, receipts, fellowship payment status, finance reports, member records visibility."],
    ["Board Member", "View assigned records and reports where allowed; no finance editing."],
    ["Committee Chair", "Manage assigned projects/activities and view assigned documents/events."],
    ["Normal Member", "Own profile, own dues, my fellowship, events/announcements/documents, digital card, consent settings."],
    ["Auditor / Read-only", "Read-only reports and audit visibility where approved; no edit permissions."],
    ["Platform Super Admin - future SaaS internal only", "Future platform role for multi-tenant operations; not a normal LC Beenachi dashboard role."]
  ];
  return (
    <section className="stack">
      <Toolbar title="Settings / Roles" actionLabel="President/Admin" />
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
