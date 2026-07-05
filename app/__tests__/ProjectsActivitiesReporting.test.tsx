import React from "react";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const auth = vi.hoisted(() => ({
  session: {
    token: "projects-report-token",
    membership: { club: { id: 7 } }
  }
}));

vi.mock("../DevAuthSession", () => ({ useDevAuth: () => auth }));

import ReportsBackend from "../ReportsBackend";

function json(payload: unknown) {
  return new Response(JSON.stringify(payload), { status: 200, headers: { "Content-Type": "application/json" } });
}

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe("Projects and Activities verified report", () => {
  it("offers backend-supported type and category filters without fabricated actions", async () => {
    global.fetch = vi.fn(async (input: RequestInfo | URL) => {
      const url = String(input);
      if (url.endsWith("/reports")) return json({ data: [{
        key: "projects-register",
        title: "Projects & Activities Register",
        description: "Verified club-scoped Projects and Activities records.",
        allowed_filters: ["date_from", "date_to", "status", "record_type", "service_category", "q", "page", "per_page"],
        aggregate_only: false,
        sensitivity: "internal"
      }] });
      return json({ data: { key: "projects-register", title: "Projects & Activities Register", description: "Verified records.", generated_at: "2026-07-06T00:00:00Z", club_timezone: "Asia/Kolkata", date_basis: "effective start date", applied_filters: {}, summary: { total_records: 0 }, rows: [] }, meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } });
    }) as typeof fetch;

    render(<ReportsBackend role="president" onReminders={() => undefined} />);
    fireEvent.click(await screen.findByRole("button", { name: "Run report" }));

    expect(await screen.findByLabelText("Record type")).toBeInTheDocument();
    expect(screen.getByLabelText("Service category")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Export|Download|Generate Report/ })).not.toBeInTheDocument();

    fireEvent.change(screen.getByLabelText("Record type"), { target: { value: "activity" } });
    fireEvent.change(screen.getByLabelText("Service category"), { target: { value: "Vision" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply" }));

    await waitFor(() => expect(String(vi.mocked(global.fetch).mock.calls.at(-1)?.[0])).toContain("record_type=activity"));
    expect(String(vi.mocked(global.fetch).mock.calls.at(-1)?.[0])).toContain("service_category=Vision");
  });
});
