import React from "react";
import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { getApiBaseUrl } from "../apiBaseUrl";

const auth = vi.hoisted(() => ({
  session: null as null | {
    identity: string;
    membership: {
      id: number;
      club: { id: number };
      permissions: { slug: string }[];
    };
  },
  authenticatedFetch: vi.fn()
}));

vi.mock("../DevAuthSession", () => ({
  useDevAuth: () => ({ session: auth.session, authenticatedFetch: auth.authenticatedFetch })
}));

import AnnouncementsBackend, { AnnouncementDashboardStatus } from "../AnnouncementsBackend";

const announcement = {
  id: 41,
  title: "Authoritative club notice",
  body: "Verified announcement body.",
  priority: "high",
  effective_status: "published",
  stored_status: "published",
  audience: "club-members",
  published_at: "2026-07-28T10:00:00.000Z",
  expires_at: null,
  club_timezone: "Asia/Kolkata",
  issued_by: "LC Beenachi",
  published_by_role: "President",
  lock_version: 2
};

const draftAnnouncement = {
  ...announcement,
  id: 51,
  title: "Draft announcement A",
  body: "Draft A body.",
  priority: "urgent",
  effective_status: "draft",
  stored_status: "draft",
  published_at: null,
  lock_version: 1
};

function json(data: unknown, status = 200) {
  return new Response(JSON.stringify(data), { status, headers: { "Content-Type": "application/json" } });
}

function signIn(permissions: string[] = ["announcements.view"]) {
  auth.session = {
    identity: "verified@example.test",
    membership: {
      id: 7,
      club: { id: 19 },
      permissions: permissions.map(slug => ({ slug }))
    }
  };
}

beforeEach(() => {
  auth.session = null;
  auth.authenticatedFetch.mockReset();
});

afterEach(cleanup);
afterEach(() => { vi.unstubAllEnvs(); vi.restoreAllMocks(); });

describe("production announcements workspace", () => {
  it("uses the configured origin for list and create-draft requests", async () => {
    vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "http://127.0.0.1:8017");
    signIn(["announcements.view", "announcements.management.view", "announcements.drafts.manage"]);
    const calls: [string, RequestInit | undefined][] = [];
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      const absolute = `${getApiBaseUrl()}${url}`;
      calls.push([absolute, init]);
      return init?.method === "POST"
        ? json({ data: { ...draftAnnouncement, id: 99, title: "Configured draft" } }, 201)
        : json({ data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } });
    });
    render(<AnnouncementsBackend />);
    await screen.findByText("No announcements match this view.");
    const list = calls.find(([url, init]) => url.includes("/announcements?") && !init?.method);
    expect(list?.[0]).toMatch(/^http:\/\/127\.0\.0\.1:8017\/api\/v1\/clubs\/19\/announcements\?/);
    fireEvent.click(screen.getByRole("button", { name: "Create draft" }));
    fireEvent.change(screen.getByLabelText("Announcement title"), { target: { value: "Configured draft" } });
    fireEvent.change(screen.getByLabelText("Announcement body"), { target: { value: "Configured body" } });
    fireEvent.click(screen.getByRole("button", { name: "Save draft" }));
    await waitFor(() => expect(calls.some(([url, init]) => url === "http://127.0.0.1:8017/api/v1/clubs/19/announcements" && init?.method === "POST")).toBe(true));
    expect(calls.every(([url]) => !url.includes(":8000"))).toBe(true);
  });
  it("performs no protected request before a verified session exists", () => {
    render(<AnnouncementsBackend />);
    expect(auth.authenticatedFetch).not.toHaveBeenCalled();
    expect(screen.queryByRole("heading", { name: "Announcements" })).not.toBeInTheDocument();
  });

  it("loads the verified club list and detail with loading, success, and backend-derived controls", async () => {
    signIn([
      "announcements.view",
      "announcements.management.view",
      "announcements.drafts.manage",
      "announcements.replace",
      "announcements.withdraw",
      "announcements.archive"
    ]);
    auth.authenticatedFetch.mockImplementation(async (url: string) => url.endsWith("/41")
      ? json({ data: announcement })
      : json({ data: [announcement], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }));

    render(<AnnouncementsBackend />);
    expect(screen.getByText("Loading announcements…")).toBeInTheDocument();
    expect(await screen.findByRole("button", { name: /Authoritative club notice/ })).toBeInTheDocument();
    expect(await screen.findByText("Verified announcement body.")).toBeInTheDocument();
    expect(auth.authenticatedFetch).toHaveBeenCalledWith(expect.stringContaining("/clubs/19/announcements?"), expect.objectContaining({ signal: expect.any(AbortSignal) }));
    expect(screen.getByRole("button", { name: "Create draft" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Create replacement" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Withdraw" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Publish" })).not.toBeInTheDocument();
  });

  it("keeps an ordinary reader read-only and does not expose management/all", async () => {
    signIn();
    auth.authenticatedFetch.mockImplementation(async (url: string) => url.endsWith("/41")
      ? json({ data: { ...announcement, stored_status: undefined, audience: undefined, lock_version: undefined } })
      : json({ data: [announcement], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }));
    render(<AnnouncementsBackend />);
    await screen.findByText("Verified announcement body.");
    expect(screen.queryByRole("option", { name: "Management / all" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Create draft" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Withdraw" })).not.toBeInTheDocument();
  });

  it("supports title search, priority filtering, pagination, empty and forbidden states", async () => {
    signIn(["announcements.view", "announcements.management.view"]);
    auth.authenticatedFetch.mockImplementation(async (url: string) => {
      if (url.endsWith("/41")) return json({ data: announcement });
      if (url.includes("scope=all")) return json({ message: "This action is unauthorized." }, 403);
      if (url.includes("q=board")) return json({ data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } });
      const requestedPage = url.includes("page=2") ? 2 : 1;
      return json({ data: requestedPage === 1 ? [announcement] : [], meta: { current_page: requestedPage, last_page: 2, per_page: 10, total: 11 } });
    });
    render(<AnnouncementsBackend />);
    fireEvent.click(await screen.findByRole("button", { name: "Next" }));
    expect(await screen.findByText("No announcements match this view.")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Search title"), { target: { value: "board" } });
    fireEvent.change(screen.getByLabelText("Priority"), { target: { value: "urgent" } });
    fireEvent.click(screen.getByRole("button", { name: "Search" }));
    await waitFor(() => expect(auth.authenticatedFetch).toHaveBeenCalledWith(expect.stringMatching(/q=board.*priority=urgent|priority=urgent.*q=board/), expect.anything()));
    fireEvent.change(screen.getByLabelText("View"), { target: { value: "all" } });
    expect((await screen.findAllByText("You are not authorized to view this announcement scope.")).length).toBeGreaterThan(0);
  });

  it("renders field validation, blocks duplicate saves, and respects backend 403", async () => {
    signIn(["announcements.view", "announcements.management.view", "announcements.drafts.manage"]);
    let resolveSave: ((response: Response) => void) | undefined;
    auth.authenticatedFetch.mockImplementation((url: string, init?: RequestInit) => {
      if (init?.method === "POST") return new Promise<Response>(resolve => { resolveSave = resolve; });
      return Promise.resolve(json({ data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }));
    });
    render(<AnnouncementsBackend />);
    await screen.findByText("No announcements match this view.");
    fireEvent.click(screen.getByRole("button", { name: "Create draft" }));
    fireEvent.change(screen.getByLabelText("Announcement title"), { target: { value: "Draft" } });
    fireEvent.change(screen.getByLabelText("Announcement body"), { target: { value: "Body" } });
    fireEvent.click(screen.getByRole("button", { name: "Save draft" }));
    expect(screen.getByRole("button", { name: "Saving…" })).toBeDisabled();
    fireEvent.click(screen.getByRole("button", { name: "Saving…" }));
    expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "POST")).toHaveLength(1);
    resolveSave?.(json({ message: "This action is unauthorized." }, 403));
    expect(await screen.findByText("The backend did not authorize this action.")).toBeInTheDocument();

    auth.authenticatedFetch.mockResolvedValueOnce(json({ message: "Invalid", errors: { title: ["Title is required."] } }, 422));
    fireEvent.click(screen.getByRole("button", { name: "Save draft" }));
    expect(await screen.findByText("Title is required.")).toBeInTheDocument();
  });

  it("handles stale 409 without overwriting or automatically resubmitting", async () => {
    signIn(["announcements.view", "announcements.management.view", "announcements.drafts.manage"]);
    auth.authenticatedFetch.mockImplementation(async (_url: string, init?: RequestInit) => init?.method === "POST"
      ? json({ message: "The announcement has changed. Refresh and try again." }, 409)
      : json({ data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }));
    render(<AnnouncementsBackend />);
    await screen.findByText("No announcements match this view.");
    fireEvent.click(screen.getByRole("button", { name: "Create draft" }));
    fireEvent.change(screen.getByLabelText("Announcement title"), { target: { value: "Stale draft" } });
    fireEvent.change(screen.getByLabelText("Announcement body"), { target: { value: "Must not overwrite." } });
    fireEvent.click(screen.getByRole("button", { name: "Save draft" }));
    expect(await screen.findByText("The announcement has changed. Refresh the detail before trying again.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Save draft" })).toBeEnabled();
    expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "POST")).toHaveLength(1);
    expect(screen.queryByText("Announcement saved.")).not.toBeInTheDocument();
  });

  it("clears loaded data on logout and ignores a request resolved after logout", async () => {
    signIn();
    let resolvePending: ((response: Response) => void) | undefined;
    let listCalls = 0;
    auth.authenticatedFetch.mockImplementation((url: string) => {
      if (url.endsWith("/41")) return Promise.resolve(json({ data: announcement }));
      listCalls += 1;
      if (listCalls === 1) return Promise.resolve(json({ data: [announcement], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }));
      return new Promise<Response>(resolve => { resolvePending = resolve; });
    });
    const rendered = render(<AnnouncementsBackend />);
    expect(await screen.findByText("Verified announcement body.")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Priority"), { target: { value: "urgent" } });
    await waitFor(() => expect(resolvePending).toBeDefined());
    auth.session = null;
    rendered.rerender(<AnnouncementsBackend />);
    expect(screen.queryByText("Authoritative club notice")).not.toBeInTheDocument();
    resolvePending?.(json({ data: [{ ...announcement, title: "Logged-out stale record" }], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }));
    await Promise.resolve();
    expect(screen.queryByText("Logged-out stale record")).not.toBeInTheDocument();
  });

  it.each([
    ["identity", (current: NonNullable<typeof auth.session>) => ({ ...current, identity: "new-identity@example.test" })],
    ["membership", (current: NonNullable<typeof auth.session>) => ({ ...current, membership: { ...current.membership, id: 88 } })],
    ["club", (current: NonNullable<typeof auth.session>) => ({ ...current, membership: { ...current.membership, club: { id: 77 } } })]
  ] as const)("isolates late responses after a verified %s change", async (_boundary, replaceSession) => {
    signIn();
    let resolveOld: ((response: Response) => void) | undefined;
    let listCalls = 0;
    const replacement = { ...announcement, id: 202, title: "Replacement-session announcement", body: "Replacement session body." };
    auth.authenticatedFetch.mockImplementation((url: string) => {
      if (url.endsWith("/202")) return Promise.resolve(json({ data: replacement }));
      if (url.includes("/announcements?")) {
        listCalls += 1;
        if (listCalls === 1) return new Promise<Response>(resolve => { resolveOld = resolve; });
        return Promise.resolve(json({ data: [replacement], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }));
      }
      return Promise.resolve(json({ message: "Not found" }, 404));
    });
    const rendered = render(<AnnouncementsBackend />);
    await waitFor(() => expect(resolveOld).toBeDefined());
    auth.session = replaceSession(auth.session!);
    rendered.rerender(<AnnouncementsBackend />);
    expect(await screen.findByText("Replacement session body.")).toBeInTheDocument();
    resolveOld?.(json({ data: [{ ...announcement, title: "Obsolete session announcement" }], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }));
    await Promise.resolve();
    expect(screen.queryByText("Obsolete session announcement")).not.toBeInTheDocument();
    expect(screen.getByText("Replacement session body.")).toBeInTheDocument();
  });

  it("presents detail 404 safely, clears stale detail, and keeps the list usable", async () => {
    signIn();
    const concealed = { ...announcement, id: 42, title: "Concealed selection" };
    auth.authenticatedFetch.mockImplementation(async (url: string) => {
      if (url.endsWith("/41")) return json({ data: announcement });
      if (url.endsWith("/42")) return json({ message: "Announcement not found.", exception: "SensitiveBackendException" }, 404);
      return json({ data: [announcement, concealed], meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 } });
    });
    render(<AnnouncementsBackend />);
    expect(await screen.findByText("Verified announcement body.")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: /Concealed selection/ }));
    expect(await screen.findByText("Announcement detail is unavailable.")).toBeInTheDocument();
    expect(screen.queryByText("Verified announcement body.")).not.toBeInTheDocument();
    expect(screen.queryByText("SensitiveBackendException")).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Authoritative club notice/ })).toBeEnabled();
  });

  it("binds an approval to its announcement and version and never rediscovers it", async () => {
    signIn(["announcements.view", "announcements.management.view", "announcements.approve"]);
    const draftB = { ...draftAnnouncement, id: 52, title: "Draft announcement B", body: "Draft B body." };
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "POST" && url.endsWith("/approvals")) {
        return json({ data: { id: 901, announcement_id: 51, announcement_lock_version: 1, decision: "approved", reason: null, decided_at: "2026-07-28T10:00:00Z" } }, 201);
      }
      if (url.endsWith("/51")) return json({ data: draftAnnouncement });
      if (url.endsWith("/52")) return json({ data: draftB });
      return json({ data: [draftAnnouncement, draftB], meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 } });
    });
    render(<AnnouncementsBackend />);
    await screen.findByText("Draft A body.");
    fireEvent.click(screen.getByRole("button", { name: "Approve" }));
    expect(await screen.findByRole("button", { name: "Revoke approval" })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: /Draft announcement B/ }));
    expect(await screen.findByText("Draft B body.")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Revoke approval" })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: /Draft announcement A/ }));
    await screen.findByText("Draft A body.");
    expect(screen.queryByRole("button", { name: "Revoke approval" })).not.toBeInTheDocument();
    expect(auth.authenticatedFetch.mock.calls.some(([url]) => String(url).includes("approval-history") || String(url).includes("decisions"))).toBe(false);
  });

  it("clears approval context after successful revocation and version-changing reload", async () => {
    signIn(["announcements.view", "announcements.management.view", "announcements.approve"]);
    vi.spyOn(window, "prompt").mockReturnValue("Approval no longer applies");
    vi.spyOn(window, "confirm").mockReturnValue(true);
    let detailVersion = 1;
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "POST" && url.endsWith("/approvals")) return json({ data: { id: 902, announcement_id: 51, announcement_lock_version: 1, decision: "approved" } }, 201);
      if (init?.method === "POST" && url.includes("/approvals/902/revoke")) return json({ data: { id: 903, announcement_id: 51, announcement_lock_version: 1, decision: "revoked" } }, 201);
      if (url.endsWith("/51")) return json({ data: { ...draftAnnouncement, lock_version: detailVersion } });
      return json({ data: [draftAnnouncement], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
    });
    render(<AnnouncementsBackend />);
    await screen.findByText("Draft A body.");
    fireEvent.click(screen.getByRole("button", { name: "Approve" }));
    const revoke = await screen.findByRole("button", { name: "Revoke approval" });
    await waitFor(() => expect(revoke).toBeEnabled());
    fireEvent.click(revoke);
    await waitFor(() => expect(auth.authenticatedFetch).toHaveBeenCalledWith(
      "/clubs/19/announcements/51/approvals/902/revoke",
      expect.objectContaining({ method: "POST" })
    ));
    await waitFor(() => expect(screen.queryByRole("button", { name: "Revoke approval" })).not.toBeInTheDocument());

    await screen.findByText("Draft A body.");
    fireEvent.click(screen.getByRole("button", { name: "Approve" }));
    expect(await screen.findByRole("button", { name: "Revoke approval" })).toBeInTheDocument();
    detailVersion = 2;
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "POST" && url.endsWith("/approvals")) return json({}, 409);
      if (url.endsWith("/51")) return json({ data: { ...draftAnnouncement, lock_version: detailVersion } });
      return json({ data: [draftAnnouncement], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
    });
    fireEvent.click(screen.getByRole("button", { name: "Approve" }));
    await screen.findByText("The announcement has changed or its lifecycle no longer permits this action. Refresh and try again.");
    await waitFor(() => expect(screen.queryByRole("button", { name: "Revoke approval" })).not.toBeInTheDocument());
  });

  it("does not retain current-session approval context across logout or session replacement", async () => {
    signIn(["announcements.view", "announcements.management.view", "announcements.approve"]);
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "POST" && url.endsWith("/approvals")) {
        return json({ data: { id: 904, announcement_id: 51, announcement_lock_version: 1, decision: "approved" } }, 201);
      }
      if (url.endsWith("/51")) return json({ data: draftAnnouncement });
      return json({ data: [draftAnnouncement], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
    });
    const rendered = render(<AnnouncementsBackend />);
    await screen.findByText("Draft A body.");
    fireEvent.click(screen.getByRole("button", { name: "Approve" }));
    expect(await screen.findByRole("button", { name: "Revoke approval" })).toBeInTheDocument();

    auth.session = null;
    rendered.rerender(<AnnouncementsBackend />);
    expect(screen.queryByRole("heading", { name: "Announcements" })).not.toBeInTheDocument();

    signIn(["announcements.view", "announcements.management.view", "announcements.approve"]);
    auth.session!.identity = "replacement@example.test";
    rendered.rerender(<AnnouncementsBackend />);
    await screen.findByText("Draft A body.");
    expect(screen.queryByRole("button", { name: "Revoke approval" })).not.toBeInTheDocument();
  });

  it("provides labelled, keyboard-reachable controls under the responsive workspace contract", async () => {
    signIn(["announcements.view", "announcements.management.view", "announcements.drafts.manage"]);
    auth.authenticatedFetch.mockResolvedValue(json({ data: [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } }));
    const rendered = render(<AnnouncementsBackend />);
    await screen.findByText("No announcements match this view.");
    expect(rendered.container.querySelector(".announcementWorkspace")).toBeInTheDocument();
    expect(rendered.container.querySelector(".announcementSplit")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Create draft" }));
    expect(screen.getByLabelText("Announcement title")).toBeVisible();
    expect(screen.getByLabelText("Announcement body")).toBeVisible();
    expect(screen.getByLabelText("Announcement priority")).toBeVisible();
    expect(screen.getByLabelText("Announcement audience")).toBeVisible();
    expect(screen.getByLabelText("Announcement expiry")).toBeVisible();
    expect(screen.getByRole("button", { name: "Save draft" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Close" })).toBeEnabled();
  });

  it("aborts obsolete requests when the verified session is replaced", async () => {
    signIn();
    const signals: AbortSignal[] = [];
    auth.authenticatedFetch.mockImplementation((_url: string, init?: RequestInit) => {
      signals.push(init?.signal as AbortSignal);
      return new Promise<Response>(() => undefined);
    });
    const rendered = render(<AnnouncementsBackend />);
    await waitFor(() => expect(signals.length).toBeGreaterThan(0));
    const obsoleteSignal = signals[0];
    auth.session = { ...auth.session!, identity: "replacement@example.test", membership: { ...auth.session!.membership, id: 8, club: { id: 20 } } };
    rendered.rerender(<AnnouncementsBackend />);
    expect(obsoleteSignal.aborted).toBe(true);
  });
});

describe("announcement dashboard status", () => {
  it("uses an explicit published member query for management sessions", async () => {
    signIn(["announcements.view", "announcements.management.view"]);
    auth.authenticatedFetch.mockResolvedValue(json({ data: [], meta: { current_page: 1, last_page: 1, per_page: 1, total: 3 } }));
    render(<AnnouncementDashboardStatus />);
    expect(await screen.findByText("3 current published member announcements.")).toBeInTheDocument();
    expect(auth.authenticatedFetch).toHaveBeenCalledWith(expect.stringContaining("status=published"), expect.objectContaining({ signal: expect.any(AbortSignal) }));
    expect(auth.authenticatedFetch).toHaveBeenCalledWith(expect.stringContaining("audience=club-members"), expect.anything());
  });

  it("uses the ordinary current projection without forbidden management filters", async () => {
    signIn(["announcements.view"]);
    auth.authenticatedFetch.mockResolvedValue(json({ data: [], meta: { current_page: 1, last_page: 1, per_page: 1, total: 0 } }));
    render(<AnnouncementDashboardStatus />);
    expect(await screen.findByText("0 current published member announcements.")).toBeInTheDocument();
    const url = auth.authenticatedFetch.mock.calls[0][0] as string;
    expect(url).not.toContain("status=");
    expect(url).not.toContain("audience=");
  });
});
