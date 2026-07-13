import React from "react";
import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import ProfileUpdatesBackend from "../ProfileUpdatesBackend";

const authState = vi.hoisted(() => ({
  session: {
    token: "profile-update-test-token",
    membership: {
      club: { id: 1 },
      member: { id: 35, member_code: "26766860", full_name: "Ranjith MR" },
      role: { slug: "treasurer" }
    }
  }
}));

vi.mock("../DevAuthSession", () => ({
  useDevAuth: () => ({ session: authState.session })
}));

function json(payload: unknown, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { "Content-Type": "application/json" }
  });
}

beforeEach(() => {
  global.fetch = vi.fn(async () => json({ data: [] })) as typeof fetch;
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

describe("ProfileUpdatesBackend role contract", () => {
  it("does not expose review management controls for Treasurer even if review mode is mounted", async () => {
    render(<ProfileUpdatesBackend role="treasurer" mode="review" />);

    expect(await screen.findByText("This role is not authorized to access club-wide profile update review.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "View Request" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Approve Request" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Reject" })).not.toBeInTheDocument();
    expect(screen.queryByLabelText("Rejection reason")).not.toBeInTheDocument();
  });
});
