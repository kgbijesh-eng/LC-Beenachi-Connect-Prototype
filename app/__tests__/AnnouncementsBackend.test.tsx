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
  it("keeps initial list and automatic detail loading side-effect free, then marks only a deliberate unread click", async () => {
    signIn();
    const unread = { ...announcement, is_read: false, read_at: null };
    let resolveMark: ((response: Response) => void) | undefined;
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "PUT") {
        return new Promise<Response>(resolve => { resolveMark = resolve; });
      }
      if (url.endsWith("/41")) return json({ data: unread });
      return json({ data: [unread], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
    });

    render(<AnnouncementsBackend />);
    const card = await screen.findByRole("button", { name: /Authoritative club notice/ });
    await screen.findByText("Verified announcement body.");
    expect(card).toHaveTextContent("Unread");
    expect(screen.getByText("Read status").parentElement).toHaveTextContent("Unread");
    expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "PUT")).toHaveLength(0);

    fireEvent.click(card);
    await waitFor(() => expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "PUT")).toHaveLength(1));
    expect(auth.authenticatedFetch).toHaveBeenCalledWith(
      "/clubs/19/announcements/41/read",
      expect.objectContaining({ method: "PUT", signal: expect.any(AbortSignal) })
    );
    fireEvent.click(card);
    expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "PUT")).toHaveLength(1);
    expect(card).toHaveTextContent("Unread");

    resolveMark?.(json({ data: { announcement_id: 41, is_read: true, read_at: "2026-07-30T08:00:00.000000Z" } }));
    await waitFor(() => expect(card).not.toHaveTextContent("Unread"));
    expect(screen.getByText("Read status").parentElement).toHaveTextContent("Read");

    fireEvent.click(card);
    expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "PUT")).toHaveLength(1);
  });

  it("leaves failed marks unread and permits a later deliberate retry", async () => {
    signIn();
    const unread = { ...announcement, is_read: false, read_at: null };
    let attempts = 0;
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "PUT") {
        attempts += 1;
        return attempts === 1
          ? json({ message: "Read state is temporarily unavailable." }, 503)
          : json({ data: { announcement_id: 41, is_read: true, read_at: "2026-07-30T08:05:00.000000Z" } });
      }
      if (url.endsWith("/41")) return json({ data: unread });
      return json({ data: [unread], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
    });

    render(<AnnouncementsBackend />);
    const card = await screen.findByRole("button", { name: /Authoritative club notice/ });
    await screen.findByText("Verified announcement body.");
    fireEvent.click(card);
    await screen.findByText("Read state is temporarily unavailable.");
    expect(card).toHaveTextContent("Unread");
    expect(screen.getByText("Read status").parentElement).toHaveTextContent("Unread");

    fireEvent.click(card);
    await waitFor(() => expect(attempts).toBe(2));
    await waitFor(() => expect(card).not.toHaveTextContent("Unread"));
    expect(screen.getByText("Read status").parentElement).toHaveTextContent("Read");
  });

  it("does not mark already-read or unpublished management announcements", async () => {
    signIn(["announcements.view", "announcements.management.view"]);
    const read = { ...announcement, is_read: true, read_at: "2026-07-30T07:00:00.000000Z" };
    const draft = { ...draftAnnouncement, id: 52, title: "Unpublished management draft", is_read: false, read_at: null };
    auth.authenticatedFetch.mockImplementation(async (url: string) => {
      if (url.endsWith("/41")) return json({ data: read });
      if (url.endsWith("/52")) return json({ data: draft });
      return json({ data: [read, draft], meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 } });
    });

    render(<AnnouncementsBackend />);
    const readCard = await screen.findByRole("button", { name: /Authoritative club notice/ });
    await screen.findByText("Verified announcement body.");
    fireEvent.click(readCard);
    const draftCard = screen.getByRole("button", { name: /Unpublished management draft/ });
    fireEvent.click(draftCard);
    await screen.findByText("Draft A body.");
    fireEvent.click(draftCard);

    expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "PUT")).toHaveLength(0);
    expect(draftCard).not.toHaveTextContent("Unread");
  });

  it("does not mark expired management records but keeps published replacement predecessors eligible", async () => {
    signIn(["announcements.view", "announcements.management.view"]);
    const expired = {
      ...announcement,
      id: 61,
      title: "Expired management notice",
      effective_status: "expired",
      stored_status: "published",
      is_read: false,
      read_at: null
    };
    const replaced = {
      ...announcement,
      id: 62,
      title: "Published replacement predecessor",
      effective_status: "replaced",
      stored_status: "published",
      is_read: false,
      read_at: null
    };
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "PUT") {
        return json({ data: { announcement_id: 62, is_read: true, read_at: "2026-07-30T08:07:00.000000Z" } });
      }
      if (url.endsWith("/61")) return json({ data: expired });
      if (url.endsWith("/62")) return json({ data: replaced });
      return json({ data: [expired, replaced], meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 } });
    });

    render(<AnnouncementsBackend />);
    const expiredCard = await screen.findByRole("button", { name: /Expired management notice/ });
    await screen.findByRole("heading", { name: "Expired management notice" });
    expect(expiredCard).not.toHaveTextContent("Unread");
    expect(screen.queryByText("Read status")).not.toBeInTheDocument();
    fireEvent.click(expiredCard);
    expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "PUT")).toHaveLength(0);

    const replacedCard = screen.getByRole("button", { name: /Published replacement predecessor/ });
    expect(replacedCard).toHaveTextContent("Unread");
    fireEvent.click(replacedCard);
    await waitFor(() => expect(auth.authenticatedFetch).toHaveBeenCalledWith(
      "/clubs/19/announcements/62/read",
      expect.objectContaining({ method: "PUT" })
    ));
    await waitFor(() => expect(replacedCard).not.toHaveTextContent("Unread"));
  });

  it("does not copy detail read state from a previously selected announcement with a different id", async () => {
    signIn();
    const first = { ...announcement, is_read: true, read_at: "2026-07-30T07:00:00.000000Z" };
    const secondListItem = {
      ...announcement,
      id: 42,
      title: "Second announcement",
      body: "Second list body.",
      is_read: undefined,
      read_at: undefined
    };
    const secondDetail = {
      ...announcement,
      id: 42,
      title: "Second announcement",
      body: "Second detail body."
    };
    auth.authenticatedFetch.mockImplementation(async (url: string) => {
      if (url.endsWith("/41")) return json({ data: first });
      if (url.endsWith("/42")) return json({ data: secondDetail });
      return json({ data: [first, secondListItem], meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 } });
    });

    render(<AnnouncementsBackend />);
    await screen.findByText("Verified announcement body.");
    expect(screen.getByText("Read status").parentElement).toHaveTextContent("Read");
    fireEvent.click(screen.getByRole("button", { name: /Second announcement/ }));
    await screen.findByText("Second detail body.");
    expect(screen.queryByText("Read status")).not.toBeInTheDocument();
    expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "PUT")).toHaveLength(0);
  });

  it("preserves same-announcement read state when a mutation response omits read fields", async () => {
    signIn(["announcements.view", "announcements.management.view", "announcements.drafts.manage"]);
    const readDraft = { ...draftAnnouncement, is_read: true, read_at: "2026-07-30T07:30:00.000000Z" };
    const updated = { ...draftAnnouncement, title: "Updated read draft", body: "Updated read body.", lock_version: 2 };
    let listCalls = 0;
    let resolveRefresh: ((response: Response) => void) | undefined;
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "PATCH") return json({ data: updated });
      if (url.endsWith("/51")) return json({ data: readDraft });
      listCalls += 1;
      if (listCalls === 1) {
        return json({ data: [readDraft], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
      }
      return new Promise<Response>(resolve => { resolveRefresh = resolve; });
    });

    render(<AnnouncementsBackend />);
    await screen.findByText("Draft A body.");
    expect(screen.getByText("Read status").parentElement).toHaveTextContent("Read");
    fireEvent.click(screen.getByRole("button", { name: "Edit draft" }));
    fireEvent.change(screen.getByLabelText("Announcement title"), { target: { value: "Updated read draft" } });
    fireEvent.change(screen.getByLabelText("Announcement body"), { target: { value: "Updated read body." } });
    fireEvent.click(screen.getByRole("button", { name: "Save draft" }));

    await screen.findByText("Updated read body.");
    expect(screen.getByText("Read status").parentElement).toHaveTextContent("Read");
    expect(auth.authenticatedFetch.mock.calls.some(([, init]) => init?.method === "PUT")).toBe(false);
    resolveRefresh?.(json({ data: [{ ...updated, is_read: true, read_at: readDraft.read_at }], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }));
  });

  it("does not mark during automatic reselection after a list filter refresh", async () => {
    signIn();
    const unread = { ...announcement, is_read: false, read_at: null };
    auth.authenticatedFetch.mockImplementation(async (url: string) => url.endsWith("/41")
      ? json({ data: unread })
      : json({ data: [unread], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } }));

    render(<AnnouncementsBackend />);
    await screen.findByText("Verified announcement body.");
    fireEvent.change(screen.getByLabelText("Priority"), { target: { value: "high" } });
    await waitFor(() => expect(auth.authenticatedFetch.mock.calls.filter(([url]) => String(url).includes("priority=high")).length).toBeGreaterThan(0));
    expect(auth.authenticatedFetch.mock.calls.filter(([, init]) => init?.method === "PUT")).toHaveLength(0);
  });

  it("aborts an in-flight mark and ignores its stale response after session replacement", async () => {
    signIn();
    const unread = { ...announcement, is_read: false, read_at: null };
    const replacement = {
      ...announcement,
      id: 91,
      title: "Replacement session notice",
      body: "Replacement session body.",
      is_read: false,
      read_at: null
    };
    let resolveMark: ((response: Response) => void) | undefined;
    let markSignal: AbortSignal | undefined;
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "PUT") {
        markSignal = init.signal ?? undefined;
        return new Promise<Response>(resolve => { resolveMark = resolve; });
      }
      if (url.includes("/clubs/20/")) {
        return url.endsWith("/91")
          ? json({ data: replacement })
          : json({ data: [replacement], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
      }
      return url.endsWith("/41")
        ? json({ data: unread })
        : json({ data: [unread], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
    });

    const rendered = render(<AnnouncementsBackend />);
    const oldCard = await screen.findByRole("button", { name: /Authoritative club notice/ });
    await screen.findByText("Verified announcement body.");
    fireEvent.click(oldCard);
    await waitFor(() => expect(markSignal).toBeDefined());

    auth.session = {
      ...auth.session!,
      identity: "replacement@example.test",
      membership: { ...auth.session!.membership, id: 8, club: { id: 20 } }
    };
    rendered.rerender(<AnnouncementsBackend />);
    expect(await screen.findByText("Replacement session body.")).toBeInTheDocument();
    await waitFor(() => expect(markSignal?.aborted).toBe(true));

    resolveMark?.(json({ data: { announcement_id: 41, is_read: true, read_at: "2026-07-30T08:10:00.000000Z" } }));
    await Promise.resolve();
    const replacementCard = screen.getByRole("button", { name: /Replacement session notice/ });
    expect(replacementCard).toHaveTextContent("Unread");
    expect(screen.getByText("Read status").parentElement).toHaveTextContent("Unread");
  });

  it("synchronizes edited detail, list row, expiry, lock version, and next publish without refresh", async () => {
    signIn(["announcements.view", "announcements.management.view", "announcements.drafts.manage", "announcements.publish"]);
    const other = { ...announcement, id: 77, title: "Other announcement" };
    const draft = { ...draftAnnouncement, id: 51, title: "Old title", body: "Old body.", expires_at: null, lock_version: 1 } as unknown as typeof draftAnnouncement;
    const updated = { ...draft, title: "New title", body: "New body.", expires_at: "2026-08-01T12:00:00.000Z", lock_version: 2 } as unknown as typeof draftAnnouncement;
    const published = { ...updated, effective_status: "published", stored_status: "published", lock_version: 3 } as unknown as typeof draftAnnouncement;
    let list = [draft, other];
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "PATCH") { list = [updated, other]; return json({ data: updated }); }
      if (init?.method === "POST") { list = [published, other]; return json({ data: published }); }
      if (url.endsWith("/51")) return json({ data: list[0] });
      return json({ data: list, meta: { current_page: 1, last_page: 1, per_page: 10, total: 2 } });
    });
    render(<AnnouncementsBackend />);
    await screen.findByText("Old body.");
    fireEvent.click(screen.getByRole("button", { name: "Edit draft" }));
    fireEvent.change(screen.getByLabelText("Announcement title"), { target: { value: "New title" } });
    fireEvent.change(screen.getByLabelText("Announcement body"), { target: { value: "New body." } });
    fireEvent.change(screen.getByLabelText("Announcement expiry"), { target: { value: "2026-08-01T12:00" } });
    fireEvent.click(screen.getByRole("button", { name: "Save draft" }));
    await screen.findByText("New body.");
    expect(screen.getByRole("heading", { name: "New title" })).toBeInTheDocument();
    expect(screen.getByText(/Expires/)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /New title/ })).toBeInTheDocument();
    vi.spyOn(window, "confirm").mockReturnValue(true);
    fireEvent.click(screen.getByRole("button", { name: "Publish" }));
    await waitFor(() => expect(auth.authenticatedFetch.mock.calls.some(([, init]) => init?.method === "POST" && String(init.body).includes('"lock_version":2'))).toBe(true));
    expect(screen.queryByText("Other announcement")).toBeInTheDocument();
    expect(auth.authenticatedFetch.mock.calls.some(([, init]) => init?.method === "PUT")).toBe(false);
  });

  it("refetches detail when a mutation response is incomplete and preserves stale-409 protection", async () => {
    signIn(["announcements.view", "announcements.management.view", "announcements.drafts.manage"]);
    const draft = { ...draftAnnouncement, id: 61, lock_version: 1 };
    const refreshed = { ...draft, title: "Authoritative refreshed title", body: "Authoritative body", lock_version: 2 };
    let detailReads = 0;
    auth.authenticatedFetch.mockImplementation(async (url: string, init?: RequestInit) => {
      if (init?.method === "PATCH") {
        return json({
          data: {
            id: 61,
            title: "Projected title",
            body: "Projected body",
            lock_version: 2
          }
        });
      }
      if (url.endsWith("/61")) { detailReads += 1; return json({ data: detailReads > 1 ? refreshed : draft }); }
      return json({ data: [draft], meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 } });
    });
    render(<AnnouncementsBackend />);
    await screen.findByText("Draft A body.");
    fireEvent.click(screen.getByRole("button", { name: "Edit draft" }));
    fireEvent.click(screen.getByRole("button", { name: "Save draft" }));
    await screen.findByText("Authoritative body");
    expect(detailReads).toBeGreaterThan(1);
  });
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
