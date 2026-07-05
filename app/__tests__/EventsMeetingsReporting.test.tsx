import { describe, expect, it, vi } from "vitest";
import { eventMeetingSection } from "../eventMeetingPeriod";

describe("Events and Meetings period classification", () => {
  it("keeps an ongoing timed event upcoming until its verified end", () => {
    vi.setSystemTime(new Date("2026-07-05T10:00:00Z"));

    expect(eventMeetingSection({
      type: "event",
      status: "published",
      starts_at: "2026-07-05T09:00:00Z",
      ends_at: "2026-07-05T11:00:00Z"
    })).toBe("Upcoming Events");
  });

  it("moves a record to past only after its effective end", () => {
    vi.setSystemTime(new Date("2026-07-05T12:00:00Z"));

    expect(eventMeetingSection({
      type: "meeting",
      status: "published",
      starts_at: "2026-07-05T09:00:00Z",
      ends_at: "2026-07-05T11:00:00Z"
    })).toBe("Past Events");
  });
});
