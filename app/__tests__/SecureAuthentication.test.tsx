import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { DevAuthProvider, useDevAuth } from "../DevAuthSession";
import AnnouncementsBackend from "../AnnouncementsBackend";
import LoginScreen from "../LoginScreen";
import Prototype from "../page";

const verifiedIdentity = {
  instance_organisation: { id: 1, code: "LCB", name: "LC Beenachi" },
  user: { id: 7, name: "Test Member", email: "member@example.test" },
  club_memberships: [{
    id: 4,
    club_id: 1,
    status: "active",
    club: { id: 1, code: "LCB", name: "LC Beenachi" },
    role: { slug: "member", name: "Member" },
    permissions: [{ slug: "announcements.view", name: "View Announcements" }],
    member: { id: 9, member_code: "TEST-009", full_name: "Test Member" }
  }]
};

function AuthHarness() {
  const auth = useDevAuth();
  return (
    <div>
      <span data-testid="status">{auth.status}</span>
      <span data-testid="identity">{auth.session?.user.email ?? "none"}</span>
      <span data-testid="announcement-permission">{auth.session?.membership.permissions?.some(permission => permission.slug === "announcements.view") ? "yes" : "no"}</span>
      {!auth.session && <LoginScreen login={auth.login} error={auth.error} submitting={auth.status === "authenticating"} />}
      {auth.session && <button onClick={() => void auth.logout()}>Log out</button>}
      {auth.session && <button onClick={() => void auth.authenticatedFetch("/protected")}>Load protected</button>}
    </div>
  );
}

function AnnouncementAuthHarness() {
  const auth = useDevAuth();
  return auth.session
    ? <AnnouncementsBackend />
    : <LoginScreen login={auth.login} error={auth.error} submitting={auth.status === "authenticating"} />;
}

afterEach(() => {
  cleanup();
  vi.unstubAllEnvs();
  vi.restoreAllMocks();
  sessionStorage.clear();
  localStorage.clear();
});

describe("secure frontend authentication", () => {
  it("uses the configured origin for login, verification, and logout", async () => {
    vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", "http://127.0.0.1:8017");
    const fetchMock = vi.spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: "configured-token" }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(verifiedIdentity), { status: 200 }))
      .mockResolvedValueOnce(new Response("{}", { status: 200 }));
    render(<DevAuthProvider><AuthHarness /></DevAuthProvider>);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "runtime-secret" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));
    await screen.findByRole("button", { name: "Log out" });
    expect(fetchMock.mock.calls[0][0]).toBe("http://127.0.0.1:8017/api/v1/auth/login");
    expect(fetchMock.mock.calls[1][0]).toBe("http://127.0.0.1:8017/api/v1/auth/me");
    expect(fetchMock.mock.calls[1][1]).toEqual(expect.objectContaining({ headers: expect.objectContaining({ Authorization: "Bearer configured-token" }) }));
    fireEvent.click(screen.getByRole("button", { name: "Log out" }));
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("unauthenticated"));
    expect(fetchMock.mock.calls[2][0]).toBe("http://127.0.0.1:8017/api/v1/auth/logout");
    expect(fetchMock.mock.calls[2][1]).toEqual(expect.objectContaining({ method: "POST", headers: expect.objectContaining({ Authorization: "Bearer configured-token" }) }));
    expect(fetchMock.mock.calls.every(([url]) => !String(url).includes(":8000"))).toBe(true);
  });

  it.each([
    ["missing", ""],
    ["invalid", "javascript:alert(1)"]
  ])("renders a safe unavailable state for %s production API configuration", async (_label, configuredBase) => {
    vi.stubEnv("NODE_ENV", "production");
    vi.stubEnv("NEXT_PUBLIC_API_BASE_URL", configuredBase);
    const fetchMock = vi.spyOn(globalThis, "fetch");
    render(<DevAuthProvider><AuthHarness /></DevAuthProvider>);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "runtime-secret" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));
    const alert = await screen.findByRole("alert");
    expect(alert).toHaveTextContent("The application service is currently unavailable because its API connection is not configured.");
    expect(alert).not.toHaveTextContent("Invalid login credentials.");
    expect(alert).not.toHaveTextContent(configuredBase || "javascript");
    expect(fetchMock).not.toHaveBeenCalled();
    expect(screen.getByRole("button", { name: "Sign in" })).toBeEnabled();
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));
    await waitFor(() => expect(fetchMock).not.toHaveBeenCalled());
  });
  it("shows the login form initially without attempting automatic authentication", () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    render(<DevAuthProvider><AuthHarness /></DevAuthProvider>);

    expect(screen.getByLabelText("Email")).toBeInTheDocument();
    expect(screen.getByLabelText("Password")).toHaveAttribute("type", "password");
    expect(screen.queryByText(/ranjith-treasurer/i)).not.toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("renders the unauthenticated form in its dedicated full-page container, outside the authenticated shell", () => {
    vi.spyOn(globalThis, "fetch");
    render(<Prototype />);

    const loginPage = screen.getByTestId("unauthenticated-login-page");
    expect(loginPage).toHaveClass("loginPage");
    expect(loginPage).not.toHaveClass("shell");
    expect(screen.getByTestId("login-card")).toBeInTheDocument();
    expect(loginPage.querySelector(".sidebar")).toBeNull();
    expect(loginPage.querySelector("nav")).toBeNull();
  });

  it("submits runtime credentials, verifies /auth/me, and retains only the active in-memory token", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: "fake-bearer-token" }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(verifiedIdentity), { status: 200 }))
      .mockResolvedValueOnce(new Response("{}", { status: 200 }));
    const sessionSet = vi.spyOn(Storage.prototype, "setItem");

    render(<DevAuthProvider><AuthHarness /></DevAuthProvider>);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "fake-runtime-secret" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    await waitFor(() => expect(screen.getByTestId("identity")).toHaveTextContent("member@example.test"));
    expect(screen.getByTestId("announcement-permission")).toHaveTextContent("yes");
    expect(fetchMock).toHaveBeenNthCalledWith(1, "http://127.0.0.1:8000/api/v1/auth/login", expect.objectContaining({
      method: "POST",
      body: JSON.stringify({ email: "member@example.test", password: "fake-runtime-secret" })
    }));
    expect(fetchMock).toHaveBeenNthCalledWith(2, "http://127.0.0.1:8000/api/v1/auth/me", expect.objectContaining({
      headers: expect.objectContaining({ Authorization: "Bearer fake-bearer-token" })
    }));
    expect(sessionSet).not.toHaveBeenCalled();
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);

    fireEvent.click(screen.getByRole("button", { name: "Load protected" }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(3));
    expect(fetchMock.mock.calls[2][0]).toBe("http://127.0.0.1:8000/api/v1/protected");
    expect(new Headers(fetchMock.mock.calls[2][1]?.headers).get("Authorization")).toBe("Bearer fake-bearer-token");
  });

  it("adds the verified in-memory bearer token to announcement requests only after /auth/me succeeds", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: "announcement-session-token" }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(verifiedIdentity), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 }
      }), { status: 200 }));
    const storageSet = vi.spyOn(Storage.prototype, "setItem");

    render(<DevAuthProvider><AnnouncementAuthHarness /></DevAuthProvider>);
    expect(fetchMock).not.toHaveBeenCalled();
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "runtime-only-secret" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    await screen.findByText("No announcements match this view.");
    expect(fetchMock).toHaveBeenNthCalledWith(2, "http://127.0.0.1:8000/api/v1/auth/me", expect.objectContaining({
      headers: expect.objectContaining({ Authorization: "Bearer announcement-session-token" })
    }));
    const announcementCall = fetchMock.mock.calls.find(([url]) => String(url).includes("/clubs/1/announcements?"));
    expect(announcementCall).toBeDefined();
    expect(new Headers(announcementCall?.[1]?.headers).get("Authorization")).toBe("Bearer announcement-session-token");
    expect(fetchMock.mock.invocationCallOrder[2]).toBeGreaterThan(fetchMock.mock.invocationCallOrder[1]);
    expect(storageSet).not.toHaveBeenCalled();
  });

  it("clears the password and displays only a generic invalid-credentials error", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValueOnce(
      new Response(JSON.stringify({ message: "sensitive backend detail" }), { status: 401 })
    );

    render(<DevAuthProvider><AuthHarness /></DevAuthProvider>);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "fake-invalid-secret" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    await waitFor(() => expect(screen.getByRole("alert")).toHaveTextContent("Unable to sign in with the provided credentials."));
    expect(screen.queryByText("sensitive backend detail")).not.toBeInTheDocument();
    expect(screen.getByLabelText("Password")).toHaveValue("");
  });

  it("rejects a token when session verification fails", async () => {
    vi.spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: "unverified-token" }), { status: 200 }))
      .mockResolvedValueOnce(new Response("{}", { status: 401 }));

    render(<DevAuthProvider><AuthHarness /></DevAuthProvider>);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "fake-runtime-secret" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));

    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("unauthenticated"));
    expect(screen.getByTestId("identity")).toHaveTextContent("none");
  });

  it("calls backend logout and clears the frontend session even when logout fails", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch")
      .mockResolvedValueOnce(new Response(JSON.stringify({ token: "fake-bearer-token" }), { status: 200 }))
      .mockResolvedValueOnce(new Response(JSON.stringify(verifiedIdentity), { status: 200 }))
      .mockRejectedValueOnce(new Error("network unavailable"));

    render(<DevAuthProvider><AuthHarness /></DevAuthProvider>);
    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "fake-runtime-secret" } });
    fireEvent.click(screen.getByRole("button", { name: "Sign in" }));
    await screen.findByRole("button", { name: "Log out" });
    fireEvent.click(screen.getByRole("button", { name: "Log out" }));

    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("unauthenticated"));
    expect(fetchMock).toHaveBeenLastCalledWith("http://127.0.0.1:8000/api/v1/auth/logout", expect.objectContaining({
      method: "POST",
      headers: expect.objectContaining({ Authorization: "Bearer fake-bearer-token" })
    }));
    expect(screen.getByLabelText("Email")).toBeInTheDocument();
  });
});
