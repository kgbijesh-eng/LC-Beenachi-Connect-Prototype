import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import AccountActivationScreen from "../AccountActivationScreen";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  window.history.replaceState(null, "", "/");
  localStorage.clear();
  sessionStorage.clear();
});

function openActivation(token = "synthetic-activation-token-with-sufficient-test-length") {
  window.history.replaceState(null, "", `/activate#token=${token}`);
  const onReturnToLogin = vi.fn();
  render(<AccountActivationScreen onReturnToLogin={onReturnToLogin} />);

  return onReturnToLogin;
}

describe("account activation screen", () => {
  it("reads and immediately removes the fragment token without persisting it", async () => {
    const storageSet = vi.spyOn(Storage.prototype, "setItem");
    openActivation();

    expect(screen.getByTestId("activation-page")).toBeInTheDocument();
    await waitFor(() => expect(window.location.hash).toBe(""));
    expect(window.location.pathname).toBe("/activate");
    expect(storageSet).not.toHaveBeenCalled();
    expect(document.cookie).toBe("");
  });

  it("renders operational email and password confirmation controls", () => {
    openActivation();
    expect(screen.getByLabelText("Operational email")).toHaveAttribute("type", "email");
    expect(screen.getByLabelText("New password")).toHaveAttribute("type", "password");
    expect(screen.getByLabelText("Confirm new password")).toHaveAttribute("type", "password");
  });

  it("submits the token only in the POST body, blocks duplicates, and clears passwords", async () => {
    let resolveRequest!: (response: Response) => void;
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(() => new Promise(resolve => {
      resolveRequest = resolve;
    }));
    openActivation("synthetic-body-token-with-sufficient-test-length");
    fireEvent.change(screen.getByLabelText("Operational email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("New password"), { target: { value: "Synthetic!Password123" } });
    fireEvent.change(screen.getByLabelText("Confirm new password"), { target: { value: "Synthetic!Password123" } });
    fireEvent.click(screen.getByRole("button", { name: "Activate account" }));
    fireEvent.click(screen.getByRole("button", { name: "Activating..." }));

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock.mock.calls[0][0]).not.toContain("token");
    expect(JSON.parse(String((fetchMock.mock.calls[0][1] as RequestInit).body))).toEqual({
      token: "synthetic-body-token-with-sufficient-test-length",
      email: "member@example.test",
      password: "Synthetic!Password123",
      password_confirmation: "Synthetic!Password123"
    });
    resolveRequest(new Response("{}", { status: 200 }));
    await screen.findByText("Account activated");
    expect(screen.queryByDisplayValue("Synthetic!Password123")).not.toBeInTheDocument();
  });

  it("uses a generic terminal failure and never authenticates the user", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(JSON.stringify({ message: "sensitive detail" }), { status: 422 }));
    openActivation();
    fireEvent.change(screen.getByLabelText("Operational email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("New password"), { target: { value: "Synthetic!Password123" } });
    fireEvent.change(screen.getByLabelText("Confirm new password"), { target: { value: "Synthetic!Password123" } });
    fireEvent.click(screen.getByRole("button", { name: "Activate account" }));

    await screen.findByText("Account activation could not be completed.");
    expect(screen.queryByText("sensitive detail")).not.toBeInTheDocument();
    expect(screen.getByLabelText("New password")).toHaveValue("");
    expect(screen.getByLabelText("Confirm new password")).toHaveValue("");
  });

  it("returns from success to the normal unauthenticated login screen", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response("{}", { status: 200 }));
    const onReturnToLogin = openActivation();
    fireEvent.change(screen.getByLabelText("Operational email"), { target: { value: "member@example.test" } });
    fireEvent.change(screen.getByLabelText("New password"), { target: { value: "Synthetic!Password123" } });
    fireEvent.change(screen.getByLabelText("Confirm new password"), { target: { value: "Synthetic!Password123" } });
    fireEvent.click(screen.getByRole("button", { name: "Activate account" }));
    fireEvent.click(await screen.findByRole("button", { name: "Return to sign in" }));

    expect(onReturnToLogin).toHaveBeenCalledOnce();
  });
});
