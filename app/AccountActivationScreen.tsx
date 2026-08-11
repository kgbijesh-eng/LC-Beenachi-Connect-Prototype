"use client";

import { type FormEvent, useEffect, useRef, useState } from "react";
import { getApiBaseUrl } from "./apiBaseUrl";

const ACTIVATION_ENDPOINT = `${getApiBaseUrl()}/auth/activate`;
const GENERIC_FAILURE = "Account activation could not be completed.";

export default function AccountActivationScreen({ onReturnToLogin }: { onReturnToLogin: () => void }) {
  const token = useRef<string | null>(null);
  const initialized = useRef(false);
  const pending = useRef(false);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [status, setStatus] = useState<"ready" | "submitting" | "success" | "error">("ready");
  const [message, setMessage] = useState<string | null>(null);

  useEffect(() => {
    if (initialized.current) return;
    initialized.current = true;
    const fragment = new URLSearchParams(window.location.hash.replace(/^#/, ""));
    token.current = fragment.get("token");
    window.history.replaceState(null, "", window.location.pathname);
    if (!token.current) {
      setStatus("error");
      setMessage(GENERIC_FAILURE);
    }
  }, []);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (pending.current || !token.current) return;
    pending.current = true;
    setStatus("submitting");
    setMessage(null);

    try {
      const response = await fetch(ACTIVATION_ENDPOINT, {
        method: "POST",
        headers: { Accept: "application/json", "Content-Type": "application/json" },
        body: JSON.stringify({
          token: token.current,
          email,
          password,
          password_confirmation: passwordConfirmation
        })
      });
      if (!response.ok) throw new Error("Activation failed.");
      token.current = null;
      setEmail("");
      setStatus("success");
      setMessage("Your account is ready. Sign in with your operational credentials.");
    } catch {
      token.current = null;
      setStatus("error");
      setMessage(GENERIC_FAILURE);
    } finally {
      pending.current = false;
      setPassword("");
      setPasswordConfirmation("");
    }
  }

  if (status === "success") {
    return (
      <main className="loginPage" data-testid="activation-page">
        <section className="loginCard">
          <p className="eyebrow">LC BEENACHI CONNECT</p>
          <h2>Account activated</h2>
          <p className="loginIntro" role="status">{message}</p>
          <button type="button" onClick={onReturnToLogin}>Return to sign in</button>
        </section>
      </main>
    );
  }

  return (
    <main className="loginPage" data-testid="activation-page">
      <section className="loginCard">
        <p className="eyebrow">LC BEENACHI CONNECT</p>
        <h2>Set up your account</h2>
        <p className="loginIntro">Confirm your operational email and choose a new password.</p>
        <form className="loginForm" onSubmit={submit}>
          <label htmlFor="activation-email">Operational email</label>
          <input id="activation-email" type="email" autoComplete="username" required value={email} onChange={(event) => setEmail(event.target.value)} />
          <label htmlFor="activation-password">New password</label>
          <input id="activation-password" type="password" autoComplete="new-password" required value={password} onChange={(event) => setPassword(event.target.value)} />
          <label htmlFor="activation-password-confirmation">Confirm new password</label>
          <input id="activation-password-confirmation" type="password" autoComplete="new-password" required value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} />
          {message && <div className="notice" role="alert">{message}</div>}
          <button type="submit" disabled={status === "submitting" || status === "error"}>
            {status === "submitting" ? "Activating..." : "Activate account"}
          </button>
          {status === "error" && <button type="button" className="secondaryButton" onClick={onReturnToLogin}>Return to sign in</button>}
        </form>
      </section>
    </main>
  );
}
