import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { DevAuthProvider, useDevAuth } from "../DevAuthSession";
import Prototype, { LoginScreen } from "../page";

const verifiedIdentity = {
  user: { id: 7, name: "Test Member", email: "member@example.test" },
  club_memberships: [{
    id: 4,
    club_id: 1,
    club: { id: 1, code: "LCB", name: "LC Beenachi" },
    role: { slug: "member", name: "Member" },
    member: { id: 9, member_code: "TEST-009", full_name: "Test Member" }
  }]
};

function AuthHarness() {
  const auth = useDevAuth();
  return (
    <div>
      <span data-testid="status">{auth.status}</span>
      <span data-testid="identity">{auth.session?.user.email ?? "none"}</span>
      {!auth.session && <LoginScreen login={auth.login} error={auth.error} submitting={auth.status === "authenticating"} />}
      {auth.session && <button onClick={() => void auth.logout()}>Log out</button>}
      {auth.session && <button onClick={() => void auth.authenticatedFetch("/protected")}>Load protected</button>}
    </div>
  );
}

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  sessionStorage.clear();
  localStorage.clear();
});

describe("secure frontend authentication", () => {
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
    expect(fetchMock).toHaveBeenNthCalledWith(3, "http://127.0.0.1:8000/api/v1/protected", expect.objectContaining({
      headers: expect.objectContaining({ Authorization: "Bearer fake-bearer-token" })
    }));
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
