"use client";

import { type FormEvent, useState } from "react";

export default function LoginScreen({ login, error, submitting }: { login: (email: string, password: string) => Promise<boolean>; error: string | null; submitting: boolean }) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const submittedPassword = password;
    setPassword("");
    await login(email.trim(), submittedPassword);
  }

  return (
    <section className="loginGrid loginCard" data-testid="login-card">
      <div className="loginPanel">
        <div className="loginBrand">
          <span className="brandMark" aria-hidden="true">LC</span>
          <div>
            <p className="eyebrow">LC BEENACHI CONNECT</p>
            <h2>Sign in</h2>
          </div>
        </div>
        <p className="loginIntro">Use your LC Connect account credentials.</p>
        <form className="loginForm" onSubmit={submit}>
          <label htmlFor="login-email">Email</label>
          <input id="login-email" name="email" type="email" autoComplete="username" required value={email} onChange={(event) => setEmail(event.target.value)} />
          <label htmlFor="login-password">Password</label>
          <input id="login-password" name="password" type="password" autoComplete="current-password" required value={password} onChange={(event) => setPassword(event.target.value)} />
          {error && <div className="notice" role="alert">{error}</div>}
          <button type="submit" disabled={submitting}>{submitting ? "Signing in..." : "Sign in"}</button>
        </form>
      </div>
    </section>
  );
}
