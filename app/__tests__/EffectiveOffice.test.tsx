import ClubAccountStatementBackend from "../ClubAccountStatementBackend";
import AnnouncementsBackend, { AnnouncementDashboardStatus } from "../AnnouncementsBackend";
import { act, cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { DevAuthProvider, hasEffectivePermission, roleFromMembership, useDevAuth, type DevMembership } from "../DevAuthSession";

describe("effective office", () => {
  const membership: DevMembership = {
    id: 1, status: "active", club: { id: 1 }, role: { slug: "member" }, member: null,
    authority_as_of: "2026-11-14T00:00:00Z",
    effective_office: { office: "president", effective_until: "2026-12-31T00:00:00Z" }
  };

  it("recognizes office on an ordinary stored role", () => {
    const clock = vi.spyOn(Date, "now").mockReturnValue(Date.parse("2026-11-14T01:00:00Z"));
    try {
      expect(roleFromMembership(membership)).toBe("president");
    } finally {
      clock.mockRestore();
    }
  });

  it("denies a legacy President at the exact effective end", () => {
    const clock = vi.spyOn(Date, "now").mockReturnValue(Date.parse("2026-12-31T00:00:00Z"));
    try {
      expect(roleFromMembership({ ...membership, role: { slug: "president-admin" } })).toBe("member");
    } finally {
      clock.mockRestore();
    }
  });
});


function Controls() {
  const auth = useDevAuth();
  return <>
    <button onClick={() => void auth.login("member@example.test", "test-password")}>Sign in</button>
    {hasEffectivePermission(auth.session, "finance.void") && <button onClick={() => {
      void auth.authenticatedFetch("/clubs/1/members/9/payments", { method: "POST" }).then(response => {
        document.dispatchEvent(new CustomEvent("write-result", { detail: response.status }));
      });
    }}>Void payment</button>}
    {auth.session?.membership.permissions?.some(permission => permission.slug === "finance.self.view") && <button>My payments</button>}
  </>;
}

const start = Date.parse("2026-11-14T00:00:00Z");
const limit = 2_147_483_647;

async function mountControls(duration: number, denyWrite = false, announcements = false) {
  vi.useFakeTimers();
  vi.setSystemTime(start);
  vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "http://127.0.0.1:8017");
  const payload = (active: boolean) => ({
    user: { id: 1, name: "Member", email: "member@example.test" },
    instance_organisation: { id: 1, code: "LCB", name: "Club" },
    club_memberships: [{
      id: 1, club_id: 1, status: "active", club: { id: 1, code: "LCB" },
      role: { slug: "member" }, member: null,
      authority_as_of: new Date(Date.now()).toISOString(),
      effective_office: active ? { office: "president", effective_until: new Date(start + duration).toISOString() } : null,
      permissions: active ? [{ slug: "finance.void" }, { slug: "announcements.management.view" }, { slug: "announcements.drafts.manage" }, { slug: "announcements.view" }, { slug: "finance.self.view" }] : [{ slug: "finance.self.view" }]
    }]
  });
  const response = (value: unknown, status = 200) => new Response(JSON.stringify(value), { status });
  const fetchMock = vi.spyOn(globalThis, "fetch")
    .mockResolvedValueOnce(response({ token: "synthetic" }))
    .mockResolvedValueOnce(response(payload(true)));
  if (denyWrite) fetchMock.mockResolvedValueOnce(response({}, 403));
  fetchMock.mockImplementation(async url => String(url).includes("/announcements") ? response({ data: [], meta: { total: 0 } }) : response(payload(false)));
  render(<DevAuthProvider><Controls />{announcements && <AnnouncementScreens />}</DevAuthProvider>);
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Sign in" })));
  expect(screen.getByRole("button", { name: "Void payment" })).toBeVisible();
  return fetchMock;
}

afterEach(() => {
  cleanup();
  vi.useRealTimers();
  vi.unstubAllEnvs();
  vi.restoreAllMocks();
});

it("reschedules beyond the browser limit and removes the mounted officer control at expiry", async () => {
  const fetchMock = await mountControls(limit + 5_000);
  await act(async () => vi.advanceTimersByTimeAsync(limit));
  expect(screen.getByRole("button", { name: "Void payment" })).toBeVisible();
  expect(fetchMock).toHaveBeenCalledTimes(2);
  await act(async () => vi.advanceTimersByTimeAsync(5_000));
  expect(screen.queryByRole("button", { name: "Void payment" })).toBeNull();
  expect(screen.getByRole("button", { name: "My payments" })).toBeVisible();
  expect(fetchMock).toHaveBeenCalledTimes(3);
});

it("removes the officer control when a suspended tab resumes after expiry", async () => {
  await mountControls(5_000);
  vi.setSystemTime(start + 6_000);
  await act(async () => window.dispatchEvent(new Event("focus")));
  expect(screen.queryByRole("button", { name: "Void payment" })).toBeNull();
  expect(screen.getByRole("button", { name: "My payments" })).toBeVisible();
});

it("returns the rejected write and refreshes permissions without retrying it", async () => {
  const fetchMock = await mountControls(60_000, true);
  const result = vi.fn();
  document.addEventListener("write-result", result, { once: true });
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Void payment" })));
  expect(result.mock.calls[0][0].detail).toBe(403);
  expect(screen.queryByRole("button", { name: "Void payment" })).toBeNull();
  expect(screen.getByRole("button", { name: "My payments" })).toBeVisible();
  expect(fetchMock.mock.calls.map(([url]) => new URL(String(url)).pathname)).toEqual([
    "/api/v1/auth/login", "/api/v1/auth/me", "/api/v1/clubs/1/members/9/payments", "/api/v1/auth/me"
  ]);
});


function AnnouncementScreens() {
  const { session } = useDevAuth();
  return session ? <><AnnouncementsBackend /><AnnouncementDashboardStatus /></> : null;
}

it("removes draft creation and management queries from mounted announcement screens at expiry", async () => {
  const fetchMock = await mountControls(5_000, false, true);
  expect(screen.getByRole("button", { name: "Create draft" })).toBeVisible();
  const dashboardQueries = () => fetchMock.mock.calls.map(([url]) => new URL(String(url)))
    .filter(url => url.pathname.endsWith("/announcements") && url.searchParams.get("per_page") === "1");
  expect(dashboardQueries().at(-1)?.searchParams.get("effective_state")).toBe("current");

  await act(async () => vi.advanceTimersByTimeAsync(5_000));
  expect(screen.queryByRole("button", { name: "Create draft" })).toBeNull();
  expect(dashboardQueries().at(-1)?.searchParams.has("effective_state")).toBe(false);
});

function StatementScreen() {
  const { session } = useDevAuth();
  return session ? <ClubAccountStatementBackend role={session.role} /> : null;
}

it("keeps a persistent statement denial visible when refreshed authority is unchanged", async () => {
  vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "http://127.0.0.1:8017");
  let authorityReads = 0;
  const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async input => {
    const path = new URL(String(input)).pathname;
    if (path.endsWith("/auth/login")) return Response.json({ token: "synthetic" });
    if (path.endsWith("/account-statement")) return Response.json({}, { status: 403 });
    if (!path.endsWith("/auth/me")) throw new Error("Unexpected request: " + path);
    authorityReads += 1;
    // A third authority read would mean the denied statement was fetched again.
    if (authorityReads > 2) return new Promise<Response>(() => {});
    return Response.json({
      user: { id: 1, name: "Officer", email: "member@example.test" },
      instance_organisation: { id: 1, code: "LCB", name: "Club" },
      club_memberships: [{
        id: 1, status: "active", club: { id: 1, code: "LCB" },
        role: { slug: "treasurer" }, member: null,
        authority_as_of: new Date(start + authorityReads * 1000).toISOString(),
        effective_office: { office: "treasurer", effective_until: "2099-01-01T00:00:00Z" },
        permissions: [{ slug: "finance.club.statement.view" }]
      }]
    });
  });
  render(<DevAuthProvider><Controls /><StatementScreen /></DevAuthProvider>);
  await act(async () => fireEvent.click(screen.getByRole("button", { name: "Sign in" })));

  expect(screen.getByText("You are not authorized to view the Club Account Statement.")).toBeVisible();
  expect(fetchMock.mock.calls.map(([url]) => new URL(String(url)).pathname)).toEqual([
    "/api/v1/auth/login", "/api/v1/auth/me", "/api/v1/clubs/1/finance/account-statement", "/api/v1/auth/me"
  ]);
});