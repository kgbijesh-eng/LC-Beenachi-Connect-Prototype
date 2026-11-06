import { act, cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { useState } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { DevAuthProvider, useDevAuth } from "../DevAuthSession";

const membership = (id: number, code: string) => ({
  id, status: "active", club_id: id, club: { id, code, name: code },
  role: { slug: "member" }, permissions: [], member: null
});
const assigned = {
  user: { id: 10, email: "synthetic@example.test", name: "Synthetic" },
  instance_organisation: { id: 2, code: "PILOT-B", name: "Assigned" },
  club_memberships: [membership(1, "LCB"), membership(2, "PILOT-B")]
};
const json = (body: unknown) => new Response(JSON.stringify(body), { status: 200 });
function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>(done => { resolve = done; });
  return { promise, resolve };
}
function Probe() {
  const auth = useDevAuth();
  const [data, setData] = useState("empty");
  const [result, setResult] = useState("");
  const load = async (path: string) => {
    try {
      const response = await auth.authenticatedFetch(path, { headers: { Authorization: "Bearer forged" } });
      const body = await response.json();
      setData(body.value);
    } catch { setResult("rejected"); }
  };
  return <>
    <span data-testid="status">{auth.status}</span>
    <span data-testid="club">{auth.session?.organisation.id ?? "none"}</span>
    <span data-testid="membership">{auth.session?.membership.club.code ?? "none"}</span>
    <span data-testid="data">{data}</span><span data-testid="result">{result}</span>
    <button onClick={() => void auth.login("synthetic@example.test", "Synthetic!Password123")}>Login</button>
    <button onClick={() => void auth.logout()}>Logout</button>
    <button onClick={() => void load("/clubs/2/members")}>Load</button>
    <button onClick={() => void load("/clubs/1/members")}>Foreign</button>
    <button onClick={() => void load("https://untrusted.example/api/v1/clubs/2/members")}>External</button>
  </>;
}
function mount() { render(<DevAuthProvider><Probe /></DevAuthProvider>); }
async function login() {
  fireEvent.click(screen.getByText("Login"));
  await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("authenticated"));
}
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe("authoritative instance organisation", () => {
  it.each([false, true])("uses verified identity regardless of LCB or membership order (reverse=%s)", async reverse => {
    const payload = { ...assigned, club_memberships: reverse ? [...assigned.club_memberships].reverse() : assigned.club_memberships };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValueOnce(json({ token: "synthetic" }))
      .mockResolvedValueOnce(json(payload)).mockResolvedValueOnce(json({ value: "assigned-only" }));
    mount(); await login();
    expect(screen.getByTestId("club")).toHaveTextContent("2");
    expect(screen.getByTestId("membership")).toHaveTextContent("PILOT-B");
    fireEvent.click(screen.getByText("Load"));
    await screen.findByText("assigned-only");
    expect(String(fetchMock.mock.calls[2][0])).toContain("/clubs/2/members");
    expect(new Headers(fetchMock.mock.calls[2][1]?.headers).get("Authorization")).toBe("Bearer synthetic");
  });

  const badSessions = [
    { ...assigned, instance_organisation: undefined },
    { ...assigned, instance_organisation: { ...assigned.instance_organisation, id: 99 } },
    { ...assigned, club_memberships: [membership(2, "PILOT-B"), membership(2, "PILOT-B")] },
    { ...assigned, club_memberships: [{ ...membership(2, "PILOT-B"), status: "inactive" }] },
    { ...assigned, club_memberships: [{ ...membership(2, "PILOT-B"), club_id: 1 }] }
  ];
  it.each(badSessions)("fails closed for absent, inconsistent, inactive or ambiguous session context %#", async payload => {
    vi.spyOn(globalThis, "fetch").mockResolvedValueOnce(json({ token: "synthetic" })).mockResolvedValueOnce(json(payload));
    mount(); fireEvent.click(screen.getByText("Login"));
    await waitFor(() => expect(screen.getByTestId("status")).toHaveTextContent("unauthenticated"));
    expect(screen.getByTestId("club")).toHaveTextContent("none");
  });

  it.each(["Foreign", "External"])("rejects %s requests without sending a bearer token", async button => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValueOnce(json({ token: "synthetic" })).mockResolvedValueOnce(json(assigned));
    mount(); await login(); fireEvent.click(screen.getByText(button));
    await screen.findByText("rejected"); expect(fetchMock).toHaveBeenCalledTimes(2);
  });

  it("invalidates an in-flight login on logout even when fetch ignores abort", async () => {
    const verification = deferred<Response>();
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValueOnce(json({ token: "late" })).mockReturnValueOnce(verification.promise);
    mount(); fireEvent.click(screen.getByText("Login"));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
    const signal = fetchMock.mock.calls[1][1]?.signal;
    fireEvent.click(screen.getByText("Logout"));
    expect(signal?.aborted).toBe(true);
    await act(async () => { verification.resolve(json(assigned)); });
    expect(screen.getByTestId("status")).toHaveTextContent("unauthenticated");
    expect(screen.getByTestId("club")).toHaveTextContent("none");
  });

  it("clears organisation state and ignores an old response body after re-login", async () => {
    const body = deferred<{ value: string }>();
    const response = json({});
    vi.spyOn(response, "json").mockReturnValue(body.promise);
    vi.spyOn(globalThis, "fetch").mockResolvedValueOnce(json({ token: "old" })).mockResolvedValueOnce(json(assigned))
      .mockResolvedValueOnce(response).mockResolvedValueOnce(json({}))
      .mockResolvedValueOnce(json({ token: "new" })).mockResolvedValueOnce(json(assigned));
    mount(); await login(); fireEvent.click(screen.getByText("Load"));
    await waitFor(() => expect(response.json).toHaveBeenCalled());
    fireEvent.click(screen.getByText("Logout")); await login();
    await act(async () => { body.resolve({ value: "old-secret" }); });
    expect(screen.getByTestId("data")).toHaveTextContent("empty");
    expect(screen.queryByText("old-secret")).not.toBeInTheDocument();
  });

  it("aborts pending protected requests and rejects a late fetch response after logout", async () => {
    const request = deferred<Response>();
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValueOnce(json({ token: "old" })).mockResolvedValueOnce(json(assigned))
      .mockReturnValueOnce(request.promise).mockResolvedValueOnce(json({}));
    mount(); await login(); fireEvent.click(screen.getByText("Load"));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(3));
    const signal = fetchMock.mock.calls[2][1]?.signal;
    fireEvent.click(screen.getByText("Logout")); expect(signal?.aborted).toBe(true);
    await act(async () => { request.resolve(json({ value: "old-secret" })); });
    expect(screen.getByTestId("data")).toHaveTextContent("empty");
  });
});
