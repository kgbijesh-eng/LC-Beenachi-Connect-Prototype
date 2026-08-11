export type EventMeetingPeriodRecord = {
  type: "event" | "meeting";
  status: "draft" | "published" | "completed" | "cancelled";
  starts_at: string | null;
  ends_at: string | null;
};

export function eventMeetingSection(event: EventMeetingPeriodRecord) {
  if (event.status === "draft") return "Meeting Notices" as const;
  const effectiveEnd = event.ends_at ?? event.starts_at;
  if (effectiveEnd && new Date(effectiveEnd).getTime() < Date.now()) return "Past Events" as const;
  return event.type === "meeting" ? "Upcoming Meetings" as const : "Upcoming Events" as const;
}
