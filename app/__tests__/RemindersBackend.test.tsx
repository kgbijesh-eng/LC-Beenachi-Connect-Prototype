import React from "react";
import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const authState = vi.hoisted(() => ({
  role: "president" as "president" | "secretary" | "treasurer" | "member",
  session: {
    token: "reminders-test-token",
    membership: { club: { id: 1 } }
  }
}));

vi.mock("../DevAuthSession", async (importOriginal) => ({
  ...await importOriginal<typeof import("../DevAuthSession")>(),
  useDevAuth: () => ({ session: authState.session })
}));

import RemindersBackend from "../RemindersBackend";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe("Notifications source truth", () => {
  it.each([
    ["president", true],
    ["secretary", true],
    ["treasurer", false],
    ["member", false]
  ] as const)("shows truthful reminder-only capability for %s", async (role, canManage) => {
    authState.role = role;
    Object.assign(authState.session.membership, { authority_as_of: "2020-01-01T00:00:00Z", effective_office: canManage ? { office: role, effective_until: "2099-01-01T00:00:00Z" } : null, permissions: canManage ? [{ slug: "reminders.manage" }] : [] });
    global.fetch = vi.fn(async () => new Response(JSON.stringify({
      data: [],
      meta: { current_page: 1, last_page: 1, total: 0 }
    }), { status: 200, headers: { "Content-Type": "application/json" } })) as typeof fetch;

    render(<RemindersBackend role={role} onBack={vi.fn()} />);

    expect(await screen.findByRole("heading", { name: "Reminders" })).toBeInTheDocument();
    expect(screen.getByText("Notification delivery and notification read status are not connected.")).toBeInTheDocument();
    expect(screen.queryByText("Notifications & Reminders")).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /mark.*read/i })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /dismiss/i })).not.toBeInTheDocument();
    expect(Boolean(screen.queryByRole("heading", { name: "Create Draft Reminder" }))).toBe(canManage);
  });
});
